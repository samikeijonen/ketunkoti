<?php
/**
 * Tests for the Salkku (portfolio) abilities: MCP-only, administrators only,
 * Finnish numbers accepted, one bad value refuses everything, writes logged.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Registrar;

require_once __DIR__ . '/bootstrap.php';

require_once ABSPATH . 'wp-admin/includes/post.php';

$get_ability    = wp_get_ability( 'ketunkoti/get-portfolio' );
$create_ability = wp_get_ability( 'ketunkoti/create-portfolio-apartment' );
$update_ability = wp_get_ability( 'ketunkoti/update-portfolio-apartment' );
if ( ! $get_ability || ! $create_ability || ! $update_ability ) {
    check( 'portfolio abilities registered', true, false );
    finish();
    return;
}

$owner_name = 'Testiyhtiö ' . wp_generate_password( 5, false );

make_user( 'editor' );
check( 'editor refused', false, $get_ability->check_permissions( [] ) );
check( 'editor refused create', false, $create_ability->check_permissions( [ 'title' => 'x', 'owner' => 'y' ] ) );

make_user( 'administrator' );
check( 'admin allowed', true, $get_ability->check_permissions( [] ) );

$new = $create_ability->execute(
    [
        'title'  => 'Testi A',
        'owner'  => $owner_name,
        'fields' => [
            'portfolio_estimated_value' => '150 000',
            'portfolio_company_loan'    => 100000,
        ],
    ]
);
if ( is_wp_error( $new ) ) {
    check( 'create works', 'array', code( $new ) );
    finish();
    return;
}
track_post( $new['id'] );
check( 'created published', 'publish', $new['status'] );
check( 'finnish number saved', 150000.0, $new['fields']['portfolio_estimated_value'] );
check( 'owner returned', $owner_name, $new['owner'] );
check( 'owner term created', true, (bool) get_term_by( 'name', $owner_name, 'portfolio-owner' ) );
check( 'create logged', 1, count( (array) get_post_meta( $new['id'], '_ketunkoti_ai_portfolio_log', true ) ) );

$bad = $create_ability->execute( [ 'title' => 'Testi B', 'owner' => $owner_name, 'fields' => [ 'portfolio_area' => 'iso' ] ] );
check( 'bad value refuses create', 'ketunkoti_portfolio_invalid_value', code( $bad ) );
check( 'nothing created on refusal', 0, count( get_posts( [ 'post_type' => 'portfolio', 'title' => 'Testi B', 'post_status' => 'any' ] ) ) );

$unknown = $create_ability->execute( [ 'title' => 'Testi B', 'owner' => $owner_name, 'fields' => [ 'portfolio_nope' => 1 ] ] );
check( 'unknown field refused by schema', 'ability_invalid_input', code( $unknown ) );

$empty_owner = $create_ability->execute( [ 'title' => 'Testi B', 'owner' => '   ' ] );
check( 'empty owner refuses create', 'ketunkoti_portfolio_invalid_owner', code( $empty_owner ) );
check( 'nothing created on empty owner', 0, count( get_posts( [ 'post_type' => 'portfolio', 'title' => 'Testi B', 'post_status' => 'any' ] ) ) );

$draft = $create_ability->execute( [ 'title' => 'Testi C', 'owner' => $owner_name, 'status' => 'draft' ] );
track_post( (int) ( $draft['id'] ?? 0 ) );
check( 'draft created', 'draft', $draft['status'] ?? '' );

check( 'draft read has labelled fields', true, isset( $get_ability->execute( [ 'owner' => $owner_name ] )['groups'][0]['drafts'][0]['fields']['portfolio_bank_loan']['label'] ) );
$draft_update = $update_ability->execute( [ 'id' => $draft['id'], 'fields' => [ 'portfolio_bank_loan' => '7 000' ] ] );
$draft_read   = $get_ability->execute( [ 'owner' => $owner_name ] )['groups'][0]['drafts'][0] ?? [];
check( 'draft field readable', 7000.0, $draft_read['fields']['portfolio_bank_loan']['value'] ?? null );
check( 'draft has results', true, isset( $draft_read['results'] ) );

$upd = $update_ability->execute( [ 'id' => $new['id'], 'fields' => [ 'portfolio_bank_loan' => 5000 ] ] );
check( 'update saved', 5000.0, $upd['fields']['portfolio_bank_loan'] ?? null );
$log = (array) get_post_meta( $new['id'], '_ketunkoti_ai_portfolio_log', true );
check( 'update logged', 2, count( $log ) );
$last = end( $log );
check( 'log after value', 5000.0, (float) ( $last['after']['portfolio_bank_loan'] ?? 0 ) );
check( 'log before empty', null, $last['before']['portfolio_bank_loan'] ?? null );

$retitled = $update_ability->execute( [ 'id' => $new['id'], 'title' => 'Testi A2' ] );
check( 'title updated', 'Testi A2', get_the_title( $new['id'] ) );

// Update never creates an owner, so the second owner is created first.
wp_insert_term( 'Toinen ' . $owner_name, 'portfolio-owner' );
$moved = $update_ability->execute( [ 'id' => $new['id'], 'owner' => 'Toinen ' . $owner_name ] );
check( 'owner changed', 'Toinen ' . $owner_name, $moved['owner'] ?? '' );
check( 'one owner only', 1, count( wp_get_object_terms( $new['id'], 'portfolio-owner' ) ) );

$bad_update = $update_ability->execute( [ 'id' => $new['id'], 'title' => 'Ei saa muuttua', 'fields' => [ 'portfolio_area' => 'iso' ] ] );
check( 'bad update refused', 'ketunkoti_portfolio_invalid_value', code( $bad_update ) );
check( 'bad update changed nothing', 'Testi A2', get_the_title( $new['id'] ) );

check( 'not portfolio refused', 'ketunkoti_ai_not_portfolio', code( $update_ability->execute( [ 'id' => self_page() ] ) ) );

// Another user's edit lock refuses the update.
$other = make_user( 'administrator' );
$admin = make_user( 'administrator' );
update_post_meta( $new['id'], '_edit_lock', time() . ':' . $other );
check( 'locked refused', 'ketunkoti_ai_locked', code( $update_ability->execute( [ 'id' => $new['id'], 'fields' => [ 'portfolio_bank_loan' => 1 ] ] ) ) );
delete_post_meta( $new['id'], '_edit_lock' );

$get = $get_ability->execute( [ 'owner' => 'Toinen ' . $owner_name ] );
check( 'get owner filter count', 1, $get['combined']['count'] ?? null );
check( 'get owner filter groups', 1, count( $get['groups'] ?? [] ) );
$apartment = $get['groups'][0]['apartments'][0] ?? [];
check( 'fields labelled', true, isset( $apartment['fields']['portfolio_bank_loan']['label'] ) );
check( 'labelled value', 5000.0, $apartment['fields']['portfolio_bank_loan']['value'] ?? null );

$by_slug = $get_ability->execute( [ 'owner' => sanitize_title( 'Toinen ' . $owner_name ) ] );
check( 'owner matched by slug', 1, $by_slug['combined']['count'] ?? null );
check( 'unknown owner', 'ketunkoti_ai_unknown_owner', code( $get_ability->execute( [ 'owner' => 'Ei ole olemassa' ] ) ) );

$all = $get_ability->execute( [] );
check( 'get without owner has groups', true, count( $all['groups'] ) >= 1 );

// Owner typo: update refuses an unknown owner, create still creates one.
$typo = $update_ability->execute( [ 'id' => $new['id'], 'owner' => 'Toinen ' . $owner_name . 'x' ] );
check( 'update unknown owner refused', 'ketunkoti_ai_unknown_owner', code( $typo ) );
check( 'unknown owner creates no term', false, (bool) get_term_by( 'name', 'Toinen ' . $owner_name . 'x', 'portfolio-owner' ) );
check( 'unknown owner changed nothing', 'Toinen ' . $owner_name, $get_ability->execute( [ 'owner' => 'Toinen ' . $owner_name ] )['groups'][0]['name'] ?? '' );
$by_slug_owner = $update_ability->execute( [ 'id' => $new['id'], 'owner' => sanitize_title( $owner_name ) ] );
check( 'update owner by slug works', $owner_name, $by_slug_owner['owner'] ?? '' );
check( 'update description mentions unknown owner', true, str_contains( $update_ability->get_description(), 'unknown owner is refused' ) );

// Titles are returned raw, in results and in the log.
$dash = $create_ability->execute( [ 'title' => 'Kuja - Katu 5', 'owner' => $owner_name ] );
track_post( (int) ( $dash['id'] ?? 0 ) );
check( 'create result title unchanged', 'Kuja - Katu 5', $dash['title'] ?? '' );
$dash_up = $update_ability->execute( [ 'id' => $dash['id'], 'title' => 'Kuja - Katu 6' ] );
check( 'update result title unchanged', 'Kuja - Katu 6', $dash_up['title'] ?? '' );
$dash_log = (array) get_post_meta( $dash['id'], '_ketunkoti_ai_portfolio_log', true );
check( 'log before title unchanged', 'Kuja - Katu 5', end( $dash_log )['before']['post_title'] ?? '' );

// null clears a field through the update ability.
$update_ability->execute( [ 'id' => $new['id'], 'fields' => [ 'portfolio_bank_loan' => 4000 ] ] );
$cleared = $update_ability->execute( [ 'id' => $new['id'], 'fields' => [ 'portfolio_bank_loan' => null ] ] );
check( 'null clears field result', true, array_key_exists( 'portfolio_bank_loan', $cleared['fields'] ?? [] ) && null === $cleared['fields']['portfolio_bank_loan'] );
check( 'null clears field stored', null, \Ketunkoti\get_portfolio_field_values( $new['id'] )['portfolio_bank_loan'] );

// A pending apartment is read as a draft row with a status.
$pending_id = (int) $draft['id'];
wp_update_post( [ 'ID' => $pending_id, 'post_status' => 'pending' ] );
$pending_row = $get_ability->execute( [ 'owner' => $owner_name ] )['groups'][0]['drafts'][0] ?? [];
check( 'pending listed with status', 'pending', $pending_row['status'] ?? '' );
check( 'pending read in full', true, isset( $pending_row['fields']['portfolio_bank_loan']['label'], $pending_row['results'] ) );

// A trashed apartment cannot be updated.
$trash_id = (int) $dash['id'];
wp_trash_post( $trash_id );
check( 'trashed update refused', 'ketunkoti_ai_trashed', code( $update_ability->execute( [ 'id' => $trash_id, 'title' => 'Ei' ] ) ) );
check( 'trashed title unchanged', 'Kuja - Katu 6', get_post_field( 'post_title', $trash_id ) );

// Exposure: MCP only, never a chat tool or REST ability.
foreach ( [ 'get-portfolio', 'create-portfolio-apartment', 'update-portfolio-apartment' ] as $slug ) {
    $ability = wp_get_ability( 'ketunkoti/' . $slug );
    $meta    = $ability->get_meta();
    check( "$slug not public", false, $meta['public'] );
    check( "$slug not in rest", false, $meta['show_in_rest'] );
    check( "$slug in MCP tools", true, in_array( 'ketunkoti/' . $slug, Registrar::add_tools( [] )['tools'], true ) );
}
check( 'not in chat list', false, array_key_exists( 'ketunkoti/get-portfolio', wp_get_abilities( [ 'meta' => [ 'public' => true ] ] ) ) );

$annotations = static fn( string $slug ): array => [
    wp_get_ability( 'ketunkoti/' . $slug )->get_meta()['annotations']['readonly'],
    wp_get_ability( 'ketunkoti/' . $slug )->get_meta()['annotations']['destructive'],
    wp_get_ability( 'ketunkoti/' . $slug )->get_meta()['annotations']['idempotent'],
];
check( 'get annotations', [ true, false, true ], $annotations( 'get-portfolio' ) );
check( 'create annotations', [ false, false, false ], $annotations( 'create-portfolio-apartment' ) );
check( 'update annotations', [ false, true, true ], $annotations( 'update-portfolio-apartment' ) );

// Clean up the owner terms the test created.
foreach ( [ $owner_name, 'Toinen ' . $owner_name ] as $name ) {
    $term = get_term_by( 'name', $name, 'portfolio-owner' );
    if ( $term ) {
        wp_delete_term( $term->term_id, 'portfolio-owner' );
    }
}

finish();
