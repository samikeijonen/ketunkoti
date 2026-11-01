<?php
/**
 * Tests for the shared number helpers.
 *
 * Run inside the wp-env CLI container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-numbers.php
 *
 * Mirrors `src/shared/numbers.test.js` one to one.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks\Tests;

use function Ketunkoti\Blocks\format_euros;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

// number_format_i18n() must use the Finnish separators.
switch_to_locale( 'fi' );

$GLOBALS['ketunkoti_blocks_test_failures'] = 0;

/**
 * Compares an expected and an actual value strictly and prints the result.
 *
 * @param string $name     Check name.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function check( string $name, $expected, $actual ): void {
    if ( $expected === $actual ) {
        echo 'PASS ', $name, PHP_EOL;
        return;
    }

    ++$GLOBALS['ketunkoti_blocks_test_failures'];
    echo 'FAIL ', $name, ': expected ', var_export( $expected, true ), ', got ', var_export( $actual, true ), PHP_EOL;
}

check( 'whole euros', "27\u{00A0}000\u{00A0}€", format_euros( 27000.0 ) );
check( 'per square metre and month', "22,69\u{00A0}€/m²/kk", format_euros( 22.6923, 2, '/m²/kk' ) );
check( 'per square metre', "3\u{00A0}462\u{00A0}€/m²", format_euros( 3461.54, 0, '/m²' ) );
check( 'missing', '—', format_euros( null ) );
check( 'not finite', '—', format_euros( INF ) );

if ( $GLOBALS['ketunkoti_blocks_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_blocks_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
