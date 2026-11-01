<?php
/**
 * 12 kk Euribor from the ECB Data Portal.
 *
 * The yield calculator prefills the visitor's bank loan rate with the latest
 * 12 kk Euribor plus an estimated marginaali. The ECB publishes the monthly
 * average only (daily values are licensed), which is plenty for an estimate.
 *
 * The rate is cached in a transient for a day. The last good value is also
 * kept in an option, so a failed fetch falls back to it and only retries after
 * an hour instead of on every page view.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Estimated bank marginaali added on top of Euribor, in percentage points.
 */
const EURIBOR_MARGIN = 0.6;

/**
 * 12 kk Euribor used when the ECB has never answered and no earlier rate is
 * stored, so the calculators always open with a rate.
 */
const EURIBOR_FALLBACK = 3.0;

/**
 * ECB Data Portal URL of the latest monthly average of 12 kk Euribor.
 */
const EURIBOR_URL = 'https://data-api.ecb.europa.eu/service/data/FM/M.U2.EUR.RT.MM.EURIBOR1YD_.HSTA?lastNObservations=1&format=jsondata';

/**
 * Transient holding the cached rate, or `none` when no rate is available.
 */
const EURIBOR_TRANSIENT = 'ketunkoti_euribor_12m';

/**
 * Option holding the last successfully fetched rate.
 */
const EURIBOR_OPTION = 'ketunkoti_euribor_12m_last';

/**
 * Reads the latest Euribor value from an ECB SDMX-JSON response.
 *
 * The value is the first element of the observation with the highest index in
 * the first series. Anything outside −5…20 % is treated as broken data rather
 * than a real rate.
 *
 * @param string $body Response body.
 * @return float|null Rate in percent, or null when the body is not usable.
 */
function parse_ecb_euribor( string $body ): ?float {
    $data   = json_decode( $body, true );
    $series = $data['dataSets'][0]['series'] ?? null;

    if ( ! is_array( $series ) || ! $series ) {
        return null;
    }

    $observations = reset( $series )['observations'] ?? null;

    if ( ! is_array( $observations ) || ! $observations ) {
        return null;
    }

    $keys  = array_map( 'intval', array_keys( $observations ) );
    $value = $observations[ (string) max( $keys ) ][0] ?? null;

    if ( ! is_int( $value ) && ! is_float( $value ) ) {
        return null;
    }

    $value = (float) $value;

    return $value >= -5 && $value <= 20 ? $value : null;
}

/**
 * Logs a failed fetch when debug logging is on.
 *
 * @param string $message What went wrong.
 * @return void
 */
function log_euribor_error( string $message ): void {
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
        error_log( 'Ketunkoti Euribor: ' . $message );
    }
}

/**
 * Fetches the latest 12 kk Euribor from the ECB.
 *
 * @return float|null Rate in percent, or null on any failure.
 */
function fetch_euribor_12m(): ?float {
    // The ECB firewall answers WordPress's default user agent with a 503
    // "protection exception", so identify as the site instead.
    $response = wp_remote_get(
        EURIBOR_URL,
        [
            'timeout'    => 5,
            'user-agent' => 'Ketunkoti/1.0 (+' . home_url( '/' ) . ')',
        ]
    );

    if ( is_wp_error( $response ) ) {
        log_euribor_error( $response->get_error_message() );
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );

    if ( 200 !== $code ) {
        log_euribor_error( 'HTTP ' . $code );
        return null;
    }

    $rate = parse_ecb_euribor( (string) wp_remote_retrieve_body( $response ) );

    if ( null === $rate ) {
        log_euribor_error( 'Unexpected response body.' );
    }

    return $rate;
}

/**
 * Returns the 12 kk Euribor, cached for a day.
 *
 * @return float|null Rate in percent, or null when none has ever been fetched.
 */
function get_euribor_12m(): ?float {
    $cached = get_transient( EURIBOR_TRANSIENT );

    if ( false !== $cached ) {
        return is_numeric( $cached ) ? (float) $cached : null;
    }

    $rate = fetch_euribor_12m();

    if ( null !== $rate ) {
        set_transient( EURIBOR_TRANSIENT, $rate, DAY_IN_SECONDS );
        update_option( EURIBOR_OPTION, $rate, false );

        return $rate;
    }

    $fallback = get_option( EURIBOR_OPTION );
    $fallback = is_numeric( $fallback ) ? (float) $fallback : null;

    // Retry within the hour rather than on every page view.
    set_transient( EURIBOR_TRANSIENT, $fallback ?? 'none', HOUR_IN_SECONDS );

    return $fallback;
}

/**
 * Returns the default bank loan rate: 12 kk Euribor plus the marginaali.
 *
 * Without any Euribor (the ECB has never answered and no earlier rate is
 * stored), EURIBOR_FALLBACK stands in for it. Rounded to one decimal to match
 * the rate field's step, so the prefilled value is never an invalid step for
 * the browser.
 *
 * @return float Rate in percent rounded to one decimal.
 */
function get_default_interest_rate(): float {
    return round( ( get_euribor_12m() ?? EURIBOR_FALLBACK ) + EURIBOR_MARGIN, 1 );
}
