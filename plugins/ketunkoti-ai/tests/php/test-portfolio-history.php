<?php
/**
 * Tests for the ketunkoti/get-portfolio-history ability: MCP-only,
 * administrators only, range and owner filters, owner history survives a
 * rename, apartments only on request.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Registrar;

require_once __DIR__ . '/bootstrap.php';

$history = wp_get_ability( 'ketunkoti/get-portfolio-history' );
$create  = wp_get_ability( 'ketunkoti/create-portfolio-apartment' );
if ( ! $history || ! $create ) {
    check( 'history ability registered', true, false );
    finish();
    return;
}

// The months below must not collide with snapshots that already exist.
if ( \Ketunkoti\get_portfolio_snapshots() ) {
    check( 'tests site has no snapshots to start with', 0, count( \Ketunkoti\get_portfolio_snapshots() ) );
    finish();
    return;
}

make_user( 'editor' );
check( 'editor refused', false, $history->check_permissions( [] ) );

make_user( 'administrator' );
check( 'admin allowed', true, $history->check_permissions( [] ) );

$old_name = 'Vanha omistaja ' . wp_generate_password( 5, false );
$new_name = 'Uusi omistaja ' . wp_generate_password( 5, false );
$other    = 'Toinen omistaja ' . wp_generate_password( 5, false );

$a = $create->execute( [ 'title' => 'Historia A', 'owner' => $old_name, 'fields' => [ 'portfolio_estimated_value' => 100000 ] ] );
$b = $create->execute( [ 'title' => 'Historia B', 'owner' => $other, 'fields' => [ 'portfolio_estimated_value' => 50000 ] ] );
if ( is_wp_error( $a ) || is_wp_error( $b ) ) {
    check( 'fixtures created', 'array', code( $a ) . ' ' . code( $b ) );
    finish();
    return;
}
track_post( $a['id'] );
track_post( $b['id'] );

$snapshot_ids   = [];
$snapshot_ids[] = \Ketunkoti\save_portfolio_snapshot( 'manual', strtotime( '2026-10-05 10:00:00 UTC' ) );

// Rename the first owner, so the second month has the new name.
$term = get_term_by( 'name', $old_name, \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY );
wp_update_term( $term->term_id, \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY, [ 'name' => $new_name ] );
$snapshot_ids[] = \Ketunkoti\save_portfolio_snapshot( 'cron', strtotime( '2026-11-01 00:30:00 UTC' ) );
foreach ( $snapshot_ids as $snapshot_id ) {
    track_post( (int) $snapshot_id );
}

$r = $history->execute( [] );
check( 'two months', 2, is_array( $r ) ? count( $r ) : -1 );
check( 'oldest first', '2026-10', $r[0]['month'] ?? null );
check( 'second month', '2026-11', $r[1]['month'] ?? null );
check( 'trigger returned', 'cron', $r[1]['trigger'] ?? null );
check( 'taken_at returned', true, isset( $r[0]['taken_at'] ) );
check( 'combined returned', 2, $r[0]['combined']['count'] ?? null );
check( 'groups returned', 2, count( $r[0]['groups'] ?? [] ) );
check( 'no apartments by default', false, array_key_exists( 'apartments', $r[0] ?? [] ) );

$with = $history->execute( [ 'include_apartments' => true ] );
check( 'apartments on request', 2, count( $with[0]['apartments'] ?? [] ) );

check( 'range filter', 1, count( $history->execute( [ 'from' => '2026-11', 'to' => '2026-11' ] ) ) );
check( 'from only', '2026-11', $history->execute( [ 'from' => '2026-11' ] )[0]['month'] ?? null );
check( 'to only', 1, count( $history->execute( [ 'to' => '2026-10' ] ) ) );
check( 'empty range gives empty list', [], $history->execute( [ 'from' => '2030-01' ] ) );
check( 'invalid range', 'ketunkoti_ai_invalid_range', code( $history->execute( [ 'from' => '2026-12', 'to' => '2026-10' ] ) ) );
check( 'bad format refused', true, is_wp_error( $history->execute( [ 'from' => '2026-13' ] ) ) );

// Owner filter by the old, stored name.
$old = $history->execute( [ 'owner' => $old_name, 'include_apartments' => true ] );
check( 'renamed owner reachable', 2, is_array( $old ) ? count( $old ) : -1 );
check( 'old name: first month combined is the group', 1, $old[0]['combined']['count'] ?? null );
check( 'old name: first month one group', 1, count( $old[0]['groups'] ?? [] ) );
check( 'old name: first month apartment filtered', 1, count( $old[0]['apartments'] ?? [] ) );
check( 'old name: second month zero totals', 0, $old[1]['combined']['count'] ?? null );
check( 'old name: second month no groups', [], $old[1]['groups'] ?? null );
check( 'old name: second month no apartments', [], $old[1]['apartments'] ?? null );

// Owner filter by the new name and by the current slug (maps to the new name).
$new = $history->execute( [ 'owner' => $new_name ] );
check( 'new name: first month zero', 0, $new[0]['combined']['count'] ?? null );
check( 'new name: second month has group', 1, $new[1]['combined']['count'] ?? null );
$by_slug = $history->execute( [ 'owner' => sanitize_title( $old_name ) ] );
check( 'slug maps to current name', 1, $by_slug[1]['combined']['count'] ?? null );

check( 'unknown owner', 'ketunkoti_ai_unknown_owner', code( $history->execute( [ 'owner' => 'Ei ole' ] ) ) );

// Registration is guarded for an older features plugin without snapshots.
check( 'history support detected', true, \Ketunkoti\AI\Portfolio_Abilities::has_history_support() );
$ability_source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-portfolio-abilities.php' );
check( 'registration is guarded by function_exists', 1, preg_match( "/if \( self::has_history_support\(\) \) \{\s+Registrar::register\(\s+'ketunkoti\/get-portfolio-history'/", $ability_source ) );
check( 'guard checks get_portfolio_snapshots', true, str_contains( $ability_source, "function_exists( 'Ketunkoti\\get_portfolio_snapshots' )" ) );

// MCP only.
check( 'not in chat list', false, array_key_exists( 'ketunkoti/get-portfolio-history', wp_get_abilities( [ 'meta' => [ 'public' => true ] ] ) ) );
check( 'in MCP tools', true, in_array( 'ketunkoti/get-portfolio-history', Registrar::add_tools( [] )['tools'], true ) );

// Clean up the owner terms.
foreach ( [ $new_name, $other ] as $name ) {
    $t = get_term_by( 'name', $name, \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY );
    if ( $t ) {
        wp_delete_term( $t->term_id, \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY );
    }
}

finish();
