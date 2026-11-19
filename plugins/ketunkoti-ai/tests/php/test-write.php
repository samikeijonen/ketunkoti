<?php
/**
 * Tests for writing home_* fields and trashing homes: any home the user may
 * edit or delete, published ones included, with edit locks respected and
 * every field change logged.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Home_Fields;

require_once __DIR__ . '/bootstrap.php';

require_once ABSPATH . 'wp-admin/includes/post.php';

$update_meta = wp_get_ability( 'ketunkoti/update-home-meta' );
$trash_home  = wp_get_ability( 'ketunkoti/trash-home' );
$copy_home   = wp_get_ability( 'ketunkoti/copy-home' );
if ( ! $update_meta || ! $trash_home || ! $copy_home ) {
    check( 'write abilities registered', true, false );
    finish();
    return;
}

$other  = make_user( 'editor' );
$editor = make_user( 'editor' );

// Update a published home.
$home = make_home( 'home', [ 'home_floor' => '2/5' ] );
$res  = $update_meta->execute(
    [
        'id'   => $home,
        'meta' => [ 'home_floor' => '3/5' ],
    ]
);
check( 'published home updated', true, is_array( $res ) );
check( 'published home meta written', '3/5', get_post_meta( $home, 'home_floor', true ) );

$log = get_post_meta( $home, Home_Fields::LOG_META );
check( 'one log row', 1, count( $log ) );
check( 'log before', '2/5', $log[0]['before']['home_floor'] ?? null );
check( 'log after', '3/5', $log[0]['after']['home_floor'] ?? null );
check( 'log user', $editor, $log[0]['user_id'] ?? null );
check( 'log time', true, is_int( $log[0]['changed'] ?? null ) && abs( time() - $log[0]['changed'] ) < 60 );

// One bad field refuses the whole call and logs nothing.
$res = $update_meta->execute(
    [
        'id'   => $home,
        'meta' => [
            'home_floor'      => '4/5',
            'home_year_built' => 'vanha',
        ],
    ]
);
check( 'bad value refused', 'ketunkoti_ai_invalid_value', code( $res ) );
check( 'bad value writes nothing', '3/5', get_post_meta( $home, 'home_floor', true ) );
check( 'bad value not logged', 1, count( get_post_meta( $home, Home_Fields::LOG_META ) ) );

// Another user has the home open in the editor.
update_post_meta( $home, '_edit_lock', time() . ':' . $other );
$res = $update_meta->execute(
    [
        'id'   => $home,
        'meta' => [ 'home_floor' => '5/5' ],
    ]
);
check( 'locked home refused', 'ketunkoti_ai_post_locked', code( $res ) );
check( 'locked home unchanged', '3/5', get_post_meta( $home, 'home_floor', true ) );
check( 'locked home not logged', 1, count( get_post_meta( $home, Home_Fields::LOG_META ) ) );

// The user's own lock does not block.
update_post_meta( $home, '_edit_lock', time() . ':' . $editor );
$res = $update_meta->execute(
    [
        'id'   => $home,
        'meta' => [ 'home_floor' => '5/5' ],
    ]
);
check( 'own lock allows write', true, is_array( $res ) );
check( 'own lock write saved', '5/5', get_post_meta( $home, 'home_floor', true ) );
check( 'own lock write logged', 2, count( get_post_meta( $home, Home_Fields::LOG_META ) ) );

// An author may not touch another author's published home.
$author_a = make_user( 'author' );
$author_b = make_user( 'author' );
$b_home   = make_home( 'home', [ 'home_floor' => '1/2' ] );
wp_update_post(
    [
        'ID'          => $b_home,
        'post_author' => $author_b,
    ]
);
wp_set_current_user( $author_a );
check(
    'other author update refused',
    true,
    is_wp_error(
        $update_meta->execute(
            [
                'id'   => $b_home,
                'meta' => [ 'home_floor' => '2/2' ],
            ]
        )
    )
);
check( 'other author meta unchanged', '1/2', get_post_meta( $b_home, 'home_floor', true ) );
check( 'other author trash refused', true, is_wp_error( $trash_home->execute( [ 'id' => $b_home ] ) ) );
check( 'other author home still published', 'publish', get_post_status( $b_home ) );

// Trash a published home.
wp_set_current_user( $editor );
$trash_target = make_home( 'home' );
$res          = $trash_home->execute( [ 'id' => $trash_target ] );
check( 'published home trashed', 'trash', is_array( $res ) ? $res['status'] : code( $res ) );
check( 'trashed home still exists', true, (bool) get_post( $trash_target ) );
$again = $trash_home->execute( [ 'id' => $trash_target ] );
check( 'second trash call succeeds with status trash', 'trash', is_array( $again ) ? $again['status'] : code( $again ) );
check( 'second trash call returns the id', $trash_target, is_array( $again ) ? $again['id'] : 0 );

// Another user's lock blocks trashing.
$locked = make_home( 'home' );
update_post_meta( $locked, '_edit_lock', time() . ':' . $other );
check( 'locked home not trashed', 'ketunkoti_ai_post_locked', code( $trash_home->execute( [ 'id' => $locked ] ) ) );
check( 'locked home still published', 'publish', get_post_status( $locked ) );

// Annotations and exposure.
foreach (
    [
        'update-home-meta' => [ $update_meta, [ false, true, true ] ],
        'trash-home'       => [ $trash_home, [ false, true, true ] ],
        'copy-home'        => [ $copy_home, [ false, false, false ] ],
    ] as $name => [ $ability, $expected ]
) {
    $meta = $ability->get_meta();
    check( "$name annotations", $expected, array_values( $meta['annotations'] ?? [] ) );
    check( "$name mcp not public", false, $meta['mcp']['public'] ?? null );
    check( "$name public", true, $meta['public'] ?? null );
}

// The proposal flow is gone.
foreach ( [ 'propose', 'apply', 'discard' ] as $verb ) {
    check( "no $verb-home-meta", null, wp_get_ability( "ketunkoti/$verb-home-meta" ) );
}

finish();
