<?php
/**
 * Tests for the ketunkoti/new-home MCP prompt.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\New_Home_Prompt;
use Ketunkoti\AI\Registrar;

require_once __DIR__ . '/bootstrap.php';

$prompt = wp_get_ability( New_Home_Prompt::NAME );
check( 'prompt registered', true, $prompt instanceof \WP_Ability );

$meta = $prompt ? $prompt->get_meta() : [];
check( 'prompt is an mcp prompt', 'prompt', $meta['mcp']['type'] ?? null );
check( 'prompt mcp public', true, $meta['mcp']['public'] ?? null );
check( 'prompt not in seravo chat', false, $meta['public'] ?? null );
check( 'prompt not in rest', false, $meta['show_in_rest'] ?? null );
check( 'prompt annotations', [ true, false, true ], array_values( $meta['annotations'] ?? [] ) );
check( 'prompt category', 'ketunkoti', $prompt ? $prompt->get_category() : null );
check( 'prompt not a tool', false, in_array( New_Home_Prompt::NAME, Registrar::add_tools( [] )['tools'], true ) );

if ( class_exists( \WP\MCP\Abilities\McpAbilityExposure::class ) && $prompt ) {
    check( 'mcp adapter sees it as public', true, \WP\MCP\Abilities\McpAbilityExposure::is_public( $prompt ) );
}

// With Seravo: every tool of the workflow is named, the inputs are passed on, nothing publishes.
$text = New_Home_Prompt::text( '123', 'Lahti, 3h+k, 72 m²', true );
foreach ( [
    'ketunkoti/list-homes',
    'ketunkoti/get-home',
    'ketunkoti/copy-home',
    'ketunkoti/update-home-meta',
    'seravo-ai-assistant/upload-media',
    'seravo-ai-assistant/list-taxonomies',
    'seravo-ai-assistant/list-terms',
    'seravo-ai-assistant/update-post',
    'seravo-ai-assistant/update-seo',
] as $tool ) {
    check( "names {$tool}", true, str_contains( $text, $tool ) );
}
check( 'source passed on', true, str_contains( $text, 'Source home: 123' ) );
check( 'details passed on', true, str_contains( $text, 'Lahti, 3h+k, 72 m²' ) );
check( 'never publishes', true, str_contains( $text, 'Never set the status to publish' ) );
check( 'stays a draft', true, str_contains( $text, 'leave the listing as a draft' ) );

// Without Seravo: no Seravo tools, wp-admin steps instead.
$plain = New_Home_Prompt::text( '', '', false );
check( 'no seravo tools without seravo', false, str_contains( $plain, 'seravo-ai-assistant/' ) );
check( 'wp-admin fallback', true, str_contains( $plain, 'wp-admin' ) );
check( 'asks for source when missing', true, str_contains( $plain, 'not given' ) );

// Executing the ability returns the text, and needs edit_posts.
$editor = make_user( 'editor' );
wp_set_current_user( $editor );
$result = $prompt ? $prompt->execute( [ 'source' => 'Testikoti' ] ) : null;
check( 'execute returns text', true, is_array( $result ) && str_contains( (string) ( $result['text'] ?? '' ), 'Source home: Testikoti' ) );

wp_set_current_user( make_user( 'subscriber' ) );
check( 'subscriber refused', true, is_wp_error( $prompt ? $prompt->execute( [] ) : null ) );

finish();
