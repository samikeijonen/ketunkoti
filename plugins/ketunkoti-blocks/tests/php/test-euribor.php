<?php
/**
 * Tests for the Euribor fetch and cache.
 *
 * Run inside the wp-env CLI container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-euribor.php
 *
 * HTTP is stubbed with the `pre_http_request` filter, so no request leaves the
 * container. Prints one PASS/FAIL line per check and exits with status 1 if any
 * failed.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks\Tests;

use function Ketunkoti\Blocks\get_default_interest_rate;
use function Ketunkoti\Blocks\get_euribor_12m;
use function Ketunkoti\Blocks\parse_ecb_euribor;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

// wp eval-file runs this file inside a method, so the counter lives in
// $GLOBALS rather than in a file-level variable.
$GLOBALS['ketunkoti_blocks_test_failures'] = 0;

/**
 * Compares an expected and an actual value and prints the result.
 *
 * Null and booleans are compared strictly, floats rounded to three decimals
 * and everything else exactly.
 *
 * @param string $name     Check name.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function check( string $name, $expected, $actual ): void {
    if ( is_float( $expected ) && ( is_float( $actual ) || is_int( $actual ) ) ) {
        $passed = round( $expected, 3 ) === round( (float) $actual, 3 );
    } else {
        $passed = $expected === $actual;
    }

    if ( $passed ) {
        echo 'PASS ', $name, PHP_EOL;
        return;
    }

    ++$GLOBALS['ketunkoti_blocks_test_failures'];
    echo 'FAIL ', $name, ': expected ', var_export( $expected, true ), ', got ', var_export( $actual, true ), PHP_EOL;
}

/**
 * Stubs every HTTP request with a canned response.
 *
 * @param int    $code HTTP status code.
 * @param string $body Response body.
 * @return void
 */
function stub_http( int $code, string $body ): void {
    remove_all_filters( 'pre_http_request' );
    add_filter(
        'pre_http_request',
        static fn() => [
            'headers'  => [],
            'body'     => $body,
            'response' => [
                'code'    => $code,
                'message' => '',
            ],
            'cookies'  => [],
            'filename' => null,
        ]
    );
}

/**
 * Clears the cached rate and the stored fallback.
 *
 * @return void
 */
function reset_cache(): void {
    delete_transient( 'ketunkoti_euribor_12m' );
    delete_option( 'ketunkoti_euribor_12m_last' );
}

// Keep the site's real cached rate and fallback, and put them back at the end,
// so running the tests never leaves the calculators without a rate.
$GLOBALS['ketunkoti_euribor_saved'] = [
    'transient' => get_transient( 'ketunkoti_euribor_12m' ),
    'option'    => get_option( 'ketunkoti_euribor_12m_last' ),
];

$ecb = '{"dataSets":[{"series":{"0:0:0:0:0:0:0":{"observations":{"0":[3.247,0,0,null,null]}}}}]}';

// Parsing.
check( 'parse ok', 3.247, parse_ecb_euribor( $ecb ) );
check( 'malformed json', null, parse_ecb_euribor( '<html>' ) );
check( 'missing path', null, parse_ecb_euribor( '{"dataSets":[]}' ) );
check( 'out of range', null, parse_ecb_euribor( str_replace( '3.247', '42', $ecb ) ) );
check( 'non numeric', null, parse_ecb_euribor( str_replace( '3.247', '"x"', $ecb ) ) );
check(
    'latest observation',
    3.1,
    parse_ecb_euribor( '{"dataSets":[{"series":{"0:0:0:0:0:0:0":{"observations":{"0":[2.9,0],"1":[3.1,0]}}}}]}' )
);

// Successful fetch.
reset_cache();
stub_http( 200, $ecb );
check( 'fetch caches', 3.247, get_euribor_12m() );
check( 'option stored', 3.247, (float) get_option( 'ketunkoti_euribor_12m_last' ) );
check( 'transient stored', 3.247, (float) get_transient( 'ketunkoti_euribor_12m' ) );
check( 'default rate', 3.8, get_default_interest_rate() );

// The ECB firewall rejects WordPress's default user agent with a 503, so the
// fetch identifies itself as the site instead.
reset_cache();
remove_all_filters( 'pre_http_request' );
$GLOBALS['ketunkoti_euribor_user_agent'] = '';
add_filter(
    'pre_http_request',
    static function ( $response, array $args ) use ( $ecb ) {
        $GLOBALS['ketunkoti_euribor_user_agent'] = (string) ( $args['user-agent'] ?? '' );

        return [
            'headers'  => [],
            'body'     => $ecb,
            'response' => [
                'code'    => 200,
                'message' => '',
            ],
            'cookies'  => [],
            'filename' => null,
        ];
    },
    10,
    2
);
get_euribor_12m();
check( 'user agent is not WordPress default', true, str_starts_with( $GLOBALS['ketunkoti_euribor_user_agent'], 'Ketunkoti/' ) );

// Cached value is used without a request.
stub_http( 500, '' );
check( 'cached without request', 3.247, get_euribor_12m() );

// Failed fetch falls back to the stored value.
reset_cache();
update_option( 'ketunkoti_euribor_12m_last', 3.1, false );
stub_http( 500, '' );
check( 'http error uses fallback', 3.1, get_euribor_12m() );

// No value has ever been fetched.
reset_cache();
stub_http( 500, '' );
check( 'no value ever', null, get_euribor_12m() );
check( 'no value cached as none', 'none', get_transient( 'ketunkoti_euribor_12m' ) );
check( 'no euribor uses 3 % fallback', 3.6, get_default_interest_rate() );

// Put the site's real cached rate and fallback back.
remove_all_filters( 'pre_http_request' );
reset_cache();

if ( false !== $GLOBALS['ketunkoti_euribor_saved']['option'] ) {
    update_option( 'ketunkoti_euribor_12m_last', $GLOBALS['ketunkoti_euribor_saved']['option'], false );
}

if ( false !== $GLOBALS['ketunkoti_euribor_saved']['transient'] ) {
    set_transient( 'ketunkoti_euribor_12m', $GLOBALS['ketunkoti_euribor_saved']['transient'], HOUR_IN_SECONDS );
}

if ( $GLOBALS['ketunkoti_blocks_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_blocks_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
