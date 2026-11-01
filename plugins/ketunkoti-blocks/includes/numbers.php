<?php
/**
 * Number helpers shared by the calculator blocks.
 *
 * The PHP twin of `src/shared/numbers.js`: parsing what Finnish visitors type,
 * and formatting results exactly as `Intl.NumberFormat( 'fi' )` does in the
 * browser, so the server's first paint never changes on hydration.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks;

use function Ketunkoti\format_percentage;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shown in place of a result that cannot be calculated, as the listing does.
 */
const CALCULATOR_DASH = '—';

/**
 * Parses a number the way Finnish visitors type it.
 *
 * Spaces (including non-breaking ones) are thousands separators and a comma is
 * the decimal point: "6 500" is 6500 and "3,85" is 3.85.
 *
 * @param string $value Typed value.
 * @return float|null The number, or null when empty or not a number.
 */
function parse_decimal( string $value ): ?float {
    $normalised = str_replace( [ ' ', "\u{00A0}", "\u{202F}", "\t" ], '', $value );
    $normalised = preg_replace( '/,/', '.', $normalised, 1 );

    if ( '' === $normalised || ! is_numeric( $normalised ) ) {
        return null;
    }

    return (float) $normalised;
}

/**
 * Normalises a formatted number to what `Intl.NumberFormat( 'fi' )` gives.
 *
 * The Finnish locale's thousands separator comes as an HTML entity from
 * number_format_i18n(), and negative numbers use a hyphen. The browser uses a
 * non-breaking space and a real minus sign. Matching them keeps the first paint
 * identical to the live update.
 *
 * @param string $formatted Formatted value.
 * @return string Normalised value.
 */
function normalise_formatted_number( string $formatted ): string {
    return str_replace( [ '&nbsp;', '&#160;', '-' ], [ "\u{00A0}", "\u{00A0}", "\u{2212}" ], $formatted );
}

/**
 * Drops a value that is not a finite number, so INF never reaches the screen.
 *
 * A vanishingly small rate can round the annuity's denominator to zero.
 *
 * @param float|null $value Calculated value.
 * @return float|null The value, or null when not finite.
 */
function finite_or_null( ?float $value ): ?float {
    return null !== $value && is_finite( $value ) ? $value : null;
}

/**
 * Formats a percentage, or a dash when there is none.
 *
 * @param float|null $value Percentage, or null.
 * @return string Formatted percentage, or a dash.
 */
function format_percentage_or_dash( ?float $value ): string {
    return null === $value ? CALCULATOR_DASH : normalise_formatted_number( format_percentage( $value ) );
}

/**
 * Formats a number for a `type="number"` field: decimal point, no grouping.
 *
 * The browser shows it in the visitor's locale, but the value itself must
 * use a point.
 *
 * @param float $value Number.
 * @return string For example "780" or "151.3".
 */
function format_field_number( float $value ): string {
    return (string) round( $value, 2 );
}

/**
 * Formats a sum in euros, or a dash when there is none.
 *
 * @param float|null $value    Euros.
 * @param int        $decimals Decimal places to show.
 * @param string     $suffix   Unit after the euro sign, for example "/kk".
 * @return string For example "27 000 €" or "22,69 €/m²/kk".
 */
function format_euros( ?float $value, int $decimals = 0, string $suffix = '' ): string {
    $value = finite_or_null( $value );

    if ( null === $value ) {
        return CALCULATOR_DASH;
    }

    return normalise_formatted_number( number_format_i18n( $value, $decimals ) ) . "\u{00A0}€" . $suffix;
}
