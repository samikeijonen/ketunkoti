<?php
/**
 * Security hardening.
 *
 * Sends security headers, disables XML-RPC and stops public user
 * enumeration. The site has a single
 * administrator and no author pages, so anything that reveals the user's
 * login name (REST users endpoint, author archives, `?author=N`) is turned
 * off for logged out visitors.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * XML-RPC is also blocked by the host, this keeps it off if that rule ever
 * goes away.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Empties the list of XML-RPC methods so the endpoint answers nothing.
 *
 * @return array
 */
function remove_xmlrpc_methods() {
    return array();
}
add_filter( 'xmlrpc_methods', __NAMESPACE__ . '\remove_xmlrpc_methods' );

/**
 * Removes the `X-Pingback` header that advertises the XML-RPC endpoint.
 *
 * @param array $headers HTTP headers.
 * @return array
 */
function remove_pingback_header( $headers ) {
    unset( $headers['X-Pingback'] );

    return $headers;
}
add_filter( 'wp_headers', __NAMESPACE__ . '\remove_pingback_header' );

/**
 * Sends security headers with every response WordPress builds.
 *
 * Static files are served by the web server without PHP, so they do not get
 * these. HSTS is enough on the HTML pages because the browser applies it to
 * the whole host, and framing and referrer rules only matter for documents.
 * HSTS is sent only over HTTPS and without `includeSubDomains` or `preload`,
 * which would be hard to undo.
 *
 * @return void
 */
function send_security_headers() {
    if ( headers_sent() ) {
        return;
    }

    if ( is_ssl() ) {
        header( 'Strict-Transport-Security: max-age=31536000' );
    }

    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
}
add_action( 'send_headers', __NAMESPACE__ . '\send_security_headers' );

/**
 * Removes the REST API user endpoints for logged out visitors.
 *
 * Logged in users keep them, the block editor needs them for example for the
 * author selector.
 *
 * @param array $endpoints Registered REST endpoints.
 * @return array
 */
function disable_user_endpoints( $endpoints ) {
    if ( is_user_logged_in() ) {
        return $endpoints;
    }

    unset(
        $endpoints['/wp/v2/users'],
        $endpoints['/wp/v2/users/(?P<id>[\d]+)']
    );

    return $endpoints;
}
add_filter( 'rest_endpoints', __NAMESPACE__ . '\disable_user_endpoints', 1000 );

/**
 * Turns author archives and `?author=N` into a 404.
 *
 * Runs before `redirect_canonical()` (priority 10), which would otherwise
 * redirect `?author=1` to `/author/<login name>/` and reveal the name.
 *
 * @return void
 */
function disable_author_archives() {
    if ( ! is_author() ) {
        return;
    }

    global $wp_query;

    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();
}
add_action( 'template_redirect', __NAMESPACE__ . '\disable_author_archives', 1 );
