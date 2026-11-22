<?php
/**
 * Salkku overview page: "Yhteenveto" under the Salkku menu.
 *
 * Shows a summary table (combined and per owner) and one apartment table per
 * owner. Totals use published apartments only; drafts are listed under the
 * table. Capability `manage_options`, wp-admin only.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Slug of the overview page.
 */
const PORTFOLIO_OVERVIEW_SLUG = 'portfolio-overview';

/**
 * Returns whether the housing company loan check date is stale.
 *
 * The loan changes every month, so a date empty or more than 365 days before
 * `$now` needs a new check.
 *
 * @param string|null $date Check date as `Y-m-d`, or null when unset.
 * @param int         $now  Current Unix timestamp.
 * @return bool True when the check is stale.
 */
function is_portfolio_loan_check_stale( ?string $date, int $now ): bool {
    if ( null === $date || '' === $date ) {
        return true;
    }

    $timestamp = strtotime( $date );

    if ( false === $timestamp ) {
        return true;
    }

    return $timestamp < $now - 365 * DAY_IN_SECONDS;
}

/**
 * Collects the portfolio for the overview page.
 *
 * Published and draft apartments are grouped by owner term name (sorted), with
 * a "no owner" group last when it has posts. Totals and the combined totals
 * count published apartments only.
 *
 * @return array{combined: array<string, mixed>, groups: array<int, array<string, mixed>>} Overview data.
 */
function get_portfolio_overview(): array {
    $posts = get_posts(
        [
            'post_type'              => PORTFOLIO_POST_TYPE,
            'post_status'            => [ 'publish', 'draft' ],
            'posts_per_page'         => -1,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_term_cache' => true,
        ]
    );

    $now      = time();
    $by_owner = [];

    foreach ( $posts as $post ) {
        $terms = get_the_terms( $post->ID, PORTFOLIO_OWNER_TAXONOMY );
        $owner = ( is_array( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : PORTFOLIO_NO_OWNER;

        if ( ! isset( $by_owner[ $owner ] ) ) {
            $by_owner[ $owner ] = [
                'apartments' => [],
                'drafts'     => [],
            ];
        }

        if ( 'publish' !== $post->post_status ) {
            $by_owner[ $owner ]['drafts'][] = [
                'id'    => (int) $post->ID,
                'title' => get_the_title( $post ),
            ];
            continue;
        }

        $fields = get_portfolio_field_values( $post->ID );

        $by_owner[ $owner ]['apartments'][] = [
            'id'               => (int) $post->ID,
            'title'            => get_the_title( $post ),
            'fields'           => $fields,
            'results'          => calculate_portfolio_apartment( $fields ),
            'loan_check_stale' => is_portfolio_loan_check_stale( $fields['portfolio_company_loan_checked'], $now ),
        ];
    }

    $names = array_keys( $by_owner );
    $names = array_values( array_diff( $names, [ PORTFOLIO_NO_OWNER ] ) );
    natcasesort( $names );

    if ( isset( $by_owner[ PORTFOLIO_NO_OWNER ] ) ) {
        $names[] = PORTFOLIO_NO_OWNER;
    }

    $groups    = [];
    $all_field = [];

    foreach ( $names as $name ) {
        $apartment_fields = wp_list_pluck( $by_owner[ $name ]['apartments'], 'fields' );
        $all_field        = array_merge( $all_field, $apartment_fields );

        $groups[] = [
            'name'       => (string) $name,
            'apartments' => $by_owner[ $name ]['apartments'],
            'drafts'     => $by_owner[ $name ]['drafts'],
            'totals'     => calculate_portfolio_totals( $apartment_fields ),
        ];
    }

    return [
        'combined' => calculate_portfolio_totals( $all_field ),
        'groups'   => $groups,
    ];
}

/**
 * Formats a nullable euro amount for a table cell.
 *
 * @param float|null $amount Amount, or null when unset.
 * @return string Formatted amount or an en dash.
 */
function format_portfolio_cell_euros( ?float $amount ): string {
    return null === $amount ? '–' : format_portfolio_euros( $amount );
}

/**
 * Formats a nullable plain number for a table cell.
 *
 * @param float|int|null $number Number, or null when unset.
 * @param int            $decimals Maximum decimals.
 * @return string Formatted number or an en dash.
 */
function format_portfolio_cell_number( $number, int $decimals = 1 ): string {
    if ( null === $number ) {
        return '–';
    }

    $formatted = number_format( (float) $number, $decimals, ',', '' );

    return false !== strpos( $formatted, ',' ) ? rtrim( rtrim( $formatted, '0' ), ',' ) : $formatted;
}

/**
 * Returns the HTML of a free collateral amount, red when negative.
 *
 * @param float $amount Free collateral.
 * @return string Escaped HTML.
 */
function get_portfolio_free_collateral_html( float $amount ): string {
    $text = esc_html( format_portfolio_euros( $amount ) );

    if ( round( $amount ) < 0 ) {
        return '<span class="ketunkoti-portfolio-negative">' . $text . '</span>';
    }

    return $text;
}

/**
 * Returns the HTML of the summary table.
 *
 * @param array<string, mixed> $overview Overview data.
 * @return string Escaped HTML.
 */
function get_portfolio_summary_html( array $overview ): string {
    $rows   = [ __( 'Yhteensä', 'ketunkoti-features' ) => $overview['combined'] ];
    $header = [
        __( 'Vapaata vakuutta', 'ketunkoti-features' ),
        __( 'Velat', 'ketunkoti-features' ),
        __( 'Nettovarallisuus', 'ketunkoti-features' ),
        __( 'Asuntojen arvo', 'ketunkoti-features' ),
        __( 'Velkavipu', 'ketunkoti-features' ),
    ];

    foreach ( $overview['groups'] as $group ) {
        $rows[ $group['name'] ] = $group['totals'];
    }

    $html  = '<div class="ketunkoti-portfolio-scroll" style="overflow-x: auto;">';
    $html .= '<table class="ketunkoti-portfolio-table ketunkoti-portfolio-summary">';
    $html .= '<caption>' . esc_html__( 'Salkun yhteenveto', 'ketunkoti-features' ) . '</caption>';
    $html .= '<thead><tr><td><span class="screen-reader-text">' . esc_html__( 'Omistaja', 'ketunkoti-features' ) . '</span></td>';

    foreach ( $header as $label ) {
        $html .= '<th scope="col">' . esc_html( $label ) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ( $rows as $name => $totals ) {
        $html .= '<tr><th scope="row">' . esc_html( (string) $name ) . '</th>';
        $html .= '<td>' . get_portfolio_free_collateral_html( $totals['free_collateral'] ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['debts'] ) ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['net_worth'] ) ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['value'] ) ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_percent( $totals['leverage'] ) ) . '</td></tr>';
    }

    return $html . '</tbody></table></div>';
}

/**
 * Returns the HTML of one apartment row.
 *
 * @param array<string, mixed> $apartment Apartment data from the overview.
 * @return string Escaped HTML.
 */
function get_portfolio_apartment_row_html( array $apartment ): string {
    $f = $apartment['fields'];
    $r = $apartment['results'];

    $title     = '' !== $apartment['title'] ? $apartment['title'] : __( '(ei nimeä)', 'ketunkoti-features' );
    $edit_link = get_edit_post_link( $apartment['id'] );
    $name_html = $edit_link
        ? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>'
        : esc_html( $title );

    $loan_html = esc_html( format_portfolio_cell_euros( $f['portfolio_company_loan'] ) );

    if ( ! empty( $f['portfolio_company_loan_checked'] ) ) {
        $checked = strtotime( $f['portfolio_company_loan_checked'] );

        if ( false !== $checked ) {
            $loan_html .= ' (' . esc_html( date_i18n( 'j.n.Y', $checked ) ) . ')';
        }
    }

    if ( $apartment['loan_check_stale'] && (float) $f['portfolio_company_loan'] > 0 ) {
        $loan_html .= ' <span class="ketunkoti-portfolio-stale">' . esc_html__( 'tarkista', 'ketunkoti-features' ) . '</span>';
    }

    $rate = $f['portfolio_collateral_rate'];

    $html  = '<tr><th scope="row">' . $name_html . '</th>';
    $html .= '<td class="ketunkoti-portfolio-text">' . esc_html( (string) ( $f['portfolio_address'] ?? '–' ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_cell_number( $f['portfolio_area'] ) ) . '</td>';
    $html .= '<td>' . esc_html( null === $f['portfolio_year_built'] ? '–' : (string) $f['portfolio_year_built'] ) . '</td>';
    $html .= '<td class="ketunkoti-portfolio-text">' . esc_html( (string) ( $f['portfolio_floor'] ?? '–' ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_cell_euros( $f['portfolio_debt_free_price'] ) ) . '</td>';
    $html .= '<td>' . $loan_html . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_cell_euros( $f['portfolio_bank_loan'] ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_euros( $r['debts'] ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_cell_euros( $f['portfolio_rent'] ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_cell_euros( $f['portfolio_charges'] ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_cell_euros( $f['portfolio_estimated_value'] ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_euros( $r['net_value'] ) ) . '</td>';
    $html .= '<td>' . esc_html( ( null !== $rate && $rate > 0 ? format_portfolio_cell_number( $rate ) : format_portfolio_cell_number( PORTFOLIO_DEFAULT_COLLATERAL_RATE ) ) . "\u{00A0}%" ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_euros( $r['collateral_value'] ) ) . '</td>';
    $html .= '<td>' . esc_html( format_portfolio_cell_euros( $f['portfolio_used_collateral'] ) ) . '</td>';
    $html .= '<td>' . get_portfolio_free_collateral_html( $r['free_collateral'] ) . '</td></tr>';

    return $html;
}

/**
 * Returns the HTML of one owner group: heading, table and drafts line.
 *
 * @param array<string, mixed> $group Group data from the overview.
 * @return string Escaped HTML.
 */
function get_portfolio_group_html( array $group ): string {
    $columns = [
        __( 'Asunto', 'ketunkoti-features' ),
        __( 'Osoite', 'ketunkoti-features' ),
        __( 'm²', 'ketunkoti-features' ),
        __( 'Valm.', 'ketunkoti-features' ),
        __( 'Kerros', 'ketunkoti-features' ),
        __( 'Ostohinta', 'ketunkoti-features' ),
        __( 'Yhtiölaina (tark.)', 'ketunkoti-features' ),
        __( 'Pankkilaina', 'ketunkoti-features' ),
        __( 'Velat', 'ketunkoti-features' ),
        __( 'Vuokra', 'ketunkoti-features' ),
        __( 'Vastikkeet', 'ketunkoti-features' ),
        __( 'Arvioitu arvo', 'ketunkoti-features' ),
        __( 'Nettoarvo', 'ketunkoti-features' ),
        __( 'Vakuus-%', 'ketunkoti-features' ),
        __( 'Vakuusarvo', 'ketunkoti-features' ),
        __( 'Käytetyt vakuudet', 'ketunkoti-features' ),
        __( 'Vapaata vakuutta', 'ketunkoti-features' ),
    ];

    $totals = $group['totals'];

    $html = '<h2>' . esc_html( $group['name'] ) . '</h2>';

    if ( ! empty( $group['apartments'] ) ) {
        $html .= '<div class="ketunkoti-portfolio-scroll" style="overflow-x: auto;">';
        $html .= '<table class="ketunkoti-portfolio-table">';
        /* translators: %s: owner name. */
        $html .= '<caption>' . esc_html( sprintf( __( 'Asunnot: %s', 'ketunkoti-features' ), $group['name'] ) ) . '</caption>';
        $html .= '<thead><tr>';

        foreach ( $columns as $label ) {
            $html .= '<th scope="col">' . esc_html( $label ) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ( $group['apartments'] as $apartment ) {
            $html .= get_portfolio_apartment_row_html( $apartment );
        }

        $html .= '</tbody><tfoot><tr><th scope="row">' . esc_html__( 'Yhteensä', 'ketunkoti-features' ) . '</th>';
        $html .= '<td></td><td></td><td></td><td></td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['debt_free_price'] ) ) . '</td>';
        $html .= '<td></td><td></td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['debts'] ) ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['rent'] ) ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['charges'] ) ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['value'] ) ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['net_worth'] ) ) . '</td>';
        $html .= '<td></td><td></td><td></td>';
        $html .= '<td>' . get_portfolio_free_collateral_html( $totals['free_collateral'] ) . '</td>';
        $html .= '</tr></tfoot></table></div>';
    }

    if ( ! empty( $group['drafts'] ) ) {
        $links = [];

        foreach ( $group['drafts'] as $draft ) {
            $title     = '' !== $draft['title'] ? $draft['title'] : __( '(ei nimeä)', 'ketunkoti-features' );
            $edit_link = get_edit_post_link( $draft['id'] );
            $links[]   = $edit_link
                ? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>'
                : esc_html( $title );
        }

        $html .= '<p class="ketunkoti-portfolio-drafts">' . esc_html__( 'Luonnokset (ei mukana summissa): ', 'ketunkoti-features' ) . implode( ', ', $links ) . '</p>';
    }

    return $html;
}

/**
 * Returns the full HTML of the overview page body.
 *
 * @param array<string, mixed> $overview Overview data from `get_portfolio_overview()`.
 * @return string Escaped HTML.
 */
function get_portfolio_overview_html( array $overview ): string {
    $html = get_portfolio_summary_html( $overview );

    if ( empty( $overview['groups'] ) ) {
        return $html . '<p>' . esc_html__( 'Salkussa ei ole vielä asuntoja.', 'ketunkoti-features' ) . '</p>';
    }

    foreach ( $overview['groups'] as $group ) {
        $html .= get_portfolio_group_html( $group );
    }

    return $html;
}

/**
 * Registers the overview page as the first Salkku submenu item.
 *
 * @return void
 */
function add_portfolio_overview_page(): void {
    $hook = add_submenu_page(
        'edit.php?post_type=' . PORTFOLIO_POST_TYPE,
        __( 'Salkku – yhteenveto', 'ketunkoti-features' ),
        __( 'Yhteenveto', 'ketunkoti-features' ),
        'manage_options',
        PORTFOLIO_OVERVIEW_SLUG,
        __NAMESPACE__ . '\render_portfolio_overview_page',
        0
    );

    get_portfolio_overview_hook( $hook ?: null );
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_portfolio_overview_page' );

/**
 * Stores and returns the hook suffix of the overview page.
 *
 * @param string|null $set Hook suffix to remember, or null to only read.
 * @return string Remembered hook suffix, empty before registration.
 */
function get_portfolio_overview_hook( ?string $set = null ): string {
    static $hook = '';

    if ( null !== $set ) {
        $hook = $set;
    }

    return $hook;
}

/**
 * Renders the overview page.
 *
 * @return void
 */
function render_portfolio_overview_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Ei oikeuksia tämän sivun katsomiseen.', 'ketunkoti-features' ), '', [ 'response' => 403 ] );
    }

    echo '<div class="wrap"><h1>' . esc_html__( 'Salkku – yhteenveto', 'ketunkoti-features' ) . '</h1>';
    echo '<p>' . esc_html__( 'Päivitetty: tiedot tallennushetken mukaan.', 'ketunkoti-features' ) . '</p>';
    echo get_portfolio_overview_html( get_portfolio_overview() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built.
    echo '</div>';
}

/**
 * Enqueues the overview styles on the overview page only.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function enqueue_portfolio_overview_styles( string $hook_suffix ): void {
    $hook = get_portfolio_overview_hook();

    if ( '' === $hook || $hook !== $hook_suffix ) {
        return;
    }

    wp_enqueue_style(
        'ketunkoti-portfolio-admin',
        KETUNKOTI_FEATURES_URL . 'assets/portfolio-admin.css',
        [],
        KETUNKOTI_FEATURES_VERSION
    );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_portfolio_overview_styles' );
