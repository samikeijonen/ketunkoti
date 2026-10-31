<?php
/**
 * Markdown responses for AI agents.
 *
 * Requests with `Accept: text/markdown` get the homepage and the 404 page as
 * Markdown at the same URL. Everyone else keeps getting HTML. Both responses
 * send `Vary: Accept` so caches keep the two versions apart, and a missing
 * page keeps its 404 status.
 *
 * Check with:
 *
 *     curl -sS -L -i -H 'Accept: text/markdown' https://ketunkoti.fi/
 *     curl -sS -L -i -H 'Accept: text/markdown' https://ketunkoti.fi/ei-ole-olemassa
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Answers the homepage and 404 page with Markdown when the client asks for it.
 *
 * Runs late on `template_redirect` so canonical redirects happen first.
 *
 * @return void
 */
function send_markdown_response() {
    if ( ! is_front_page() && ! is_404() ) {
        return;
    }

    header( 'Vary: Accept', false );

    $accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';

    if ( ! str_contains( strtolower( $accept ), 'text/markdown' ) ) {
        return;
    }

    header( 'Content-Type: text/markdown; charset=utf-8' );

    // Markdown is plain text, not HTML, so there is nothing to escape here.
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo is_404() ? not_found_markdown() : front_page_markdown();
    exit;
}
add_action( 'template_redirect', __NAMESPACE__ . '\send_markdown_response', 1000 );

/**
 * Builds the homepage as Markdown from the front page's rendered content.
 *
 * Rendering through `the_content` expands patterns and block bindings, the
 * headings become Markdown headings and the rest is reduced to plain text.
 *
 * @return string Markdown document.
 */
function front_page_markdown(): string {
    $post = get_post( (int) get_option( 'page_on_front' ) );

    if ( ! $post ) {
        return '# ' . get_bloginfo( 'name' ) . "\n\n" . get_bloginfo( 'description' ) . "\n\n" . site_links_markdown();
    }

    setup_postdata( $post );
    $html = apply_filters( 'the_content', $post->post_content );
    wp_reset_postdata();

    $html = preg_replace_callback(
        '/<h([1-6])[^>]*>(.*?)<\/h\1>/is',
        static fn( $matches ) => "\n\n" . str_repeat( '#', (int) $matches[1] ) . ' ' . trim( wp_strip_all_tags( $matches[2] ) ) . "\n\n",
        $html
    );

    $text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text = preg_replace( '/^[ \t]+|[ \t]+$/m', '', $text );
    $text = preg_replace( "/\n{3,}/", "\n\n", $text );

    return trim( $text ) . "\n\n" . site_links_markdown() . "\n";
}

/**
 * Builds the 404 response body.
 *
 * @return string Markdown document.
 */
function not_found_markdown(): string {
    return '# ' . __( 'Sivua ei löytynyt', 'ketunkoti-features' ) . "\n\n"
        . __( 'Hakemaasi sivua ei löytynyt. Osoite voi olla vanha tai kirjoitettu väärin.', 'ketunkoti-features' ) . "\n\n"
        . site_links_markdown() . "\n";
}

/**
 * Lists the entry points that describe the whole site.
 *
 * @return string Markdown list.
 */
function site_links_markdown(): string {
    $sitemap = get_sitemap_url( 'index' );

    return '- [' . __( 'Sivuston kuvaus tekoälylle', 'ketunkoti-features' ) . '](' . home_url( '/llms.txt' ) . ")\n"
        . '- [' . __( 'Sivukartta', 'ketunkoti-features' ) . '](' . ( $sitemap ? $sitemap : home_url( '/sitemap.xml' ) ) . ")\n"
        . '- [' . __( 'Etusivu', 'ketunkoti-features' ) . '](' . home_url( '/' ) . ')';
}
