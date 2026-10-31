<?php
/**
 * Tests for proposing, applying and discarding meta changes on homes that
 * Claude did not create.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Meta_Proposals;

require_once __DIR__ . '/bootstrap.php';

$propose_ability = wp_get_ability( 'ketunkoti/propose-home-meta' );
$apply_ability   = wp_get_ability( 'ketunkoti/apply-home-meta' );
$discard_ability = wp_get_ability( 'ketunkoti/discard-home-meta' );
if ( ! $propose_ability || ! $apply_ability || ! $discard_ability ) {
    check( 'proposal abilities registered', true, false );
    finish();
    return;
}

$user = make_user( 'editor' );
$home = make_home(
    'home',
    [
        'home_debt_free_price' => 138725,
        'home_floor'           => '3/5',
    ]
);

$propose = static fn( array $meta ) => $propose_ability->execute(
    [
        'id'   => $home,
        'meta' => $meta,
    ]
);
$apply   = static fn( string $proposal_id ) => $apply_ability->execute( [ 'proposal_id' => $proposal_id ] );

// The profile switch is off by default.
check( 'switch off refused', 'ketunkoti_ai_apply_disabled', code( $propose( [ 'home_floor' => '4/5' ] ) ) );
update_user_meta( $user, 'meom_ai_allow_mcp_apply', '1' );

// Propose shows before and after and changes nothing.
$p = $propose(
    [
        'home_debt_free_price' => 135000,
        'home_floor'           => '3/5',
    ]
);
check( 'unchanged field left out', [ 'home_debt_free_price' ], is_array( $p ) ? wp_list_pluck( $p['changes'], 'key' ) : $p );
check( 'before value', '138725', $p['changes'][0]['before'] ?? null );
check( 'after value', 135000, $p['changes'][0]['after'] ?? null );
check( 'label', 'Velaton hinta', $p['changes'][0]['label'] ?? null );
check( 'propose writes nothing', '138725', get_post_meta( $home, 'home_debt_free_price', true ) );
check( 'price without selling price noted', true, 1 === count( $p['notes'] ?? [] ) && str_contains( $p['notes'][0], 'Myyntihinta' ) );
check( 'no changes refused', 'ketunkoti_ai_no_changes', code( $propose( [ 'home_floor' => '3/5' ] ) ) );
check( 'bad value refused', 'ketunkoti_ai_invalid_value', code( $propose( [ 'home_year_built' => 'vanha' ] ) ) );

// Apply writes and logs.
$applied = $apply( $p['proposal_id'] ?? '' );
check( 'apply ok', '135000', is_array( $applied ) ? (string) $applied['applied']['home_debt_free_price'] : $applied );
check( 'applied', '135000', get_post_meta( $home, 'home_debt_free_price', true ) );
$log = get_post_meta( $home, Meta_Proposals::LOG_META );
check( 'log kept', '138725', $log[0]['before']['home_debt_free_price'] ?? null );
check( 'log user', $user, $log[0]['user_id'] ?? null );
check( 'formatted equal no changes', 'ketunkoti_ai_no_changes', code( $propose( [ 'home_debt_free_price' => '135 000' ] ) ) );
$zero = $propose( [ 'home_capital_charge' => '0,00' ] );
$apply( $zero['proposal_id'] ?? '' );
check( 'apply saves zero', '0', get_post_meta( $home, 'home_capital_charge', true ) );
check( 'apply twice refused', 'ketunkoti_ai_proposal_not_found', code( $apply( $p['proposal_id'] ?? '' ) ) );

// Someone edits the field in the editor between propose and apply.
$stale = $propose( [ 'home_floor' => '4/5' ] );
update_post_meta( $home, 'home_floor', '5/5' );
check( 'stale refused', 'ketunkoti_ai_stale', code( $apply( $stale['proposal_id'] ?? '' ) ) );
check( 'stale unchanged', '5/5', get_post_meta( $home, 'home_floor', true ) );

// Discard.
$d = $propose( [ 'home_floor' => '1/5' ] );
check( 'discard ok', $d['proposal_id'] ?? null, $discard_ability->execute( [ 'proposal_id' => $d['proposal_id'] ?? '' ] )['discarded'] ?? null );
check( 'apply after discard refused', 'ketunkoti_ai_proposal_not_found', code( $apply( $d['proposal_id'] ?? '' ) ) );

// Expiry, also when the transient outlives its age.
$old = $propose( [ 'home_floor' => '1/5' ] );
$row = get_transient( 'ketunkoti_ai_proposal_' . ( $old['proposal_id'] ?? '' ) );
if ( is_array( $row ) ) {
    $row['created'] -= Meta_Proposals::TTL + 1;
    set_transient( 'ketunkoti_ai_proposal_' . $old['proposal_id'], $row, Meta_Proposals::TTL );
}
check( 'expired refused', 'ketunkoti_ai_proposal_not_found', code( $apply( $old['proposal_id'] ?? '' ) ) );

// Another user may not apply or discard someone else's proposal.
$mine  = $propose( [ 'home_floor' => '2/5' ] );
$other = make_user( 'editor' );
update_user_meta( $other, 'meom_ai_allow_mcp_apply', '1' );
check( 'other user refused', 'ketunkoti_ai_not_your_proposal', code( $apply( $mine['proposal_id'] ?? '' ) ) );
check( 'other user discard refused', 'ketunkoti_ai_not_your_proposal', code( $discard_ability->execute( [ 'proposal_id' => $mine['proposal_id'] ?? '' ] ) ) );

// Edit rights are checked again at apply time.
wp_set_current_user( $user );
$revoked = $propose( [ 'home_floor' => '3/5' ] );
$deny    = static fn( $caps, $cap, $user_id, $args ) => 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === $home ? [ 'do_not_allow' ] : $caps;
add_filter( 'map_meta_cap', $deny, 10, 4 );
check( 'edit revoked refused', 'ketunkoti_ai_forbidden', code( $apply( $revoked['proposal_id'] ?? '' ) ) );
remove_filter( 'map_meta_cap', $deny, 10 );

// The switch is checked again at apply time.
wp_set_current_user( $user );
update_user_meta( $user, 'meom_ai_allow_mcp_apply', '0' );
check( 'switch off at apply', 'ketunkoti_ai_apply_disabled', code( $apply( $mine['proposal_id'] ?? '' ) ) );
check( 'switch off unchanged', '5/5', get_post_meta( $home, 'home_floor', true ) );

finish();
