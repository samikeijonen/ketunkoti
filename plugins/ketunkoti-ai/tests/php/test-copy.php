<?php
/**
 * Tests for copying homes and writing meta on Claude's own drafts.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Home_Fields;
use MEOM\AI_Assistant\Ownership;

require_once __DIR__ . '/bootstrap.php';

$copy_home   = wp_get_ability( 'ketunkoti/copy-home' );
$update_meta = wp_get_ability( 'ketunkoti/update-home-meta' );
if ( ! $copy_home || ! $update_meta ) {
    check( 'copy abilities registered', true, false );
    finish();
    return;
}

$user   = make_user( 'editor' );
$source = make_home(
    'home',
    [
        'home_area'            => 54.5,
        'home_debt_free_price' => 138725,
        'home_floor'           => '3/5',
    ],
    [
        'home-city'  => [ 'Lahti' ],
        'home-rooms' => [ '2h' ],
        'home-plot'  => [ 'Oma tontti' ],
    ]
);
update_post_meta( $source, '_genesis_title', 'SEO title' ); // The SEO Framework meta must not be copied.
wp_update_post(
    [
        'ID'         => $source,
        'menu_order' => 3,
    ]
);
// After wp_update_post(), which resets a template the theme does not list.
update_post_meta( $source, '_wp_page_template', 'test-template' );
$image = wp_insert_attachment(
    [
        'post_title'     => 'Testikuva',
        'post_mime_type' => 'image/jpeg',
        'post_status'    => 'inherit',
    ]
);
track_post( $image );
update_post_meta( $source, '_thumbnail_id', $image );

// Copy a home.
$copy = $copy_home->execute( [ 'id' => $source ] );
$id   = is_array( $copy ) ? (int) $copy['id'] : 0;
track_post( $id );

check( 'copy is draft', 'draft', get_post_status( $id ) );
check( 'copy type', 'home', get_post_type( $id ) );
check( 'copy author', $user, (int) get_post_field( 'post_author', $id ) );
check( 'copy title', get_the_title( $source ) . ' (kopio)', get_the_title( $id ) );
check( 'copy content', get_post_field( 'post_content', $source ), get_post_field( 'post_content', $id ) );
check( 'copy excerpt', get_post_field( 'post_excerpt', $source ), get_post_field( 'post_excerpt', $id ) );
check( 'copy menu order', 3, (int) get_post_field( 'menu_order', $id ) );
check( 'copy template', 'test-template', get_post_meta( $id, '_wp_page_template', true ) );
check( 'copy featured image', $image, (int) get_post_thumbnail_id( $id ) );
check( 'copy every field', Home_Fields::values( $source ), Home_Fields::values( $id ) );
foreach ( get_object_taxonomies( 'home' ) as $taxonomy ) {
    check(
        "copy terms $taxonomy",
        wp_get_object_terms( $source, $taxonomy, [ 'fields' => 'ids' ] ),
        wp_get_object_terms( $id, $taxonomy, [ 'fields' => 'ids' ] )
    );
}
check( 'seo meta not copied', '', get_post_meta( $id, '_genesis_title', true ) );
check( 'copy is ai owned', true, Ownership::is_ai_owned( $id, $user ) );
check( 'copy response home', $id, $copy['home']['id'] ?? 0 );
check( 'copy response edit link', true, ! empty( $copy['edit_link'] ) );
check( 'copy of a page refused', true, is_wp_error( $copy_home->execute( [ 'id' => self_page() ] ) ) );

// Copy a rental with an own title.
$rental      = make_home( 'rental', [ 'home_rent' => 700, 'home_pets' => 'Sovittavissa' ], [ 'home-city' => [ 'Espoo' ] ] );
$rental_copy = $copy_home->execute(
    [
        'id'    => $rental,
        'title' => 'Uusi vuokrakoti',
    ]
);
$rental_id   = is_array( $rental_copy ) ? (int) $rental_copy['id'] : 0;
track_post( $rental_id );

check( 'rental copy title', 'Uusi vuokrakoti', get_the_title( $rental_id ) );
check( 'rental copy pets', 'Sovittavissa', get_post_meta( $rental_id, 'home_pets', true ) );
check( 'rental copy skips home-only', false, metadata_exists( 'post', $rental_id, 'home_debt_free_price' ) );
check( 'rental copy skips home-only taxonomy', [], wp_get_object_terms( $rental_id, 'home-plot', [ 'fields' => 'ids' ] ) );

// Update meta on Claude's draft.
$res = $update_meta->execute(
    [
        'id'   => $id,
        'meta' => [
            'home_floor' => '1/3',
            'home_area'  => null,
        ],
    ]
);
check( 'update writes', '1/3', get_post_meta( $id, 'home_floor', true ) );
check( 'update deletes', true, is_array( $res ) && array_key_exists( 'home_area', $res['fields'] ) && null === $res['fields']['home_area'] );
check( 'still ai owned', true, Ownership::is_ai_owned( $id, $user ) );
$update_meta->execute( [ 'id' => $id, 'meta' => [ 'home_capital_charge' => 0, 'home_debt_free_price' => '142 500' ] ] );
check( 'update saves zero', '0', get_post_meta( $id, 'home_capital_charge', true ) );
check( 'update saves finnish price', '142500', get_post_meta( $id, 'home_debt_free_price', true ) );
check( 'update bad value refused', 'ketunkoti_ai_invalid_value', code( $update_meta->execute( [ 'id' => $id, 'meta' => [ 'home_year_built' => 'vanha' ] ] ) ) );
check( 'published refused', 'ketunkoti_ai_not_ai_owned', code( $update_meta->execute( [ 'id' => $source, 'meta' => [ 'home_floor' => '1/3' ] ] ) ) );
check( 'published unchanged', '3/5', get_post_meta( $source, 'home_floor', true ) );

// The editor saves the draft by hand: it is no longer Claude's own.
wp_update_post(
    [
        'ID'           => $id,
        'post_content' => 'Käsin muokattu',
    ]
);
check( 'hand-saved draft refused', 'ketunkoti_ai_not_ai_owned', code( $update_meta->execute( [ 'id' => $id, 'meta' => [ 'home_floor' => '2/3' ] ] ) ) );

// Notes: a title another home has, and fields that usually change together.
check( 'unique title no notes', [], $copy['notes'] ?? null );
$same_title = $copy_home->execute(
    [
        'id'    => $source,
        'title' => get_the_title( $source ),
    ]
);
$same_id    = is_array( $same_title ) ? (int) $same_title['id'] : 0;
track_post( $same_id );
check( 'same title noted', true, 1 === count( $same_title['notes'] ?? [] ) && str_contains( $same_title['notes'][0], (string) $source ) );

$res = $update_meta->execute( [ 'id' => $same_id, 'meta' => [ 'home_rent' => 810 ] ] );
check( 'rent without deposit noted', true, 1 === count( $res['notes'] ?? [] ) && str_contains( $res['notes'][0], 'Vakuus' ) );
$res = $update_meta->execute( [ 'id' => $same_id, 'meta' => [ 'home_rent' => 820, 'home_deposit' => 820 ] ] );
check( 'rent with deposit no notes', [], $res['notes'] ?? null );
$res = $update_meta->execute( [ 'id' => $same_id, 'meta' => [ 'home_debt_free_price' => 145000 ] ] );
check( 'price without selling price noted', 1, count( $res['notes'] ?? [] ) );
$res = $update_meta->execute( [ 'id' => $same_id, 'meta' => [ 'home_floor' => '4/8' ] ] );
check( 'unrelated field no notes', [], $res['notes'] ?? null );

// Trash: only Claude's own drafts, and only to the trash.
$trash_home = wp_get_ability( 'ketunkoti/trash-home' );
check( 'trash ability registered', true, (bool) $trash_home );
if ( $trash_home ) {
    $res = $trash_home->execute( [ 'id' => $same_id ] );
    check( 'own draft trashed', 'trash', is_array( $res ) ? $res['status'] : code( $res ) );
    check( 'trashed post still exists', true, (bool) get_post( $same_id ) );
    check( 'published not trashed', 'ketunkoti_ai_not_ai_owned', code( $trash_home->execute( [ 'id' => $source ] ) ) );
    check( 'published still published', 'publish', get_post_status( $source ) );
    check( 'hand-saved draft not trashed', 'ketunkoti_ai_not_ai_owned', code( $trash_home->execute( [ 'id' => $id ] ) ) );
    check( 'page not trashed', 'ketunkoti_ai_not_a_home', code( $trash_home->execute( [ 'id' => self_page() ] ) ) );
}

finish();
