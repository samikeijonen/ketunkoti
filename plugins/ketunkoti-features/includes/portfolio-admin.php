<?php
/**
 * Salkku overview page: "Yhteenveto" under the Salkku menu.
 *
 * Shows a summary table (combined and per owner) and one apartment table per
 * owner. Totals use published and private apartments only; drafts, pending and
 * scheduled apartments are listed under the table. Capability `manage_options`, wp-admin only.
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
 * Returns the statuses whose apartments count in the totals.
 *
 * @return string[] Post statuses: publish and private.
 */
function get_portfolio_counted_statuses(): array {
    return [ 'publish', 'private' ];
}

/**
 * Returns the Finnish label of a status that is listed but not counted.
 *
 * @param string $status Post status: draft, pending or future.
 * @return string Label: luonnos, odottaa or ajastettu.
 */
function get_portfolio_status_label( string $status ): string {
    switch ( $status ) {
        case 'pending':
            return __( 'odottaa', 'ketunkoti-features' );
        case 'future':
            return __( 'ajastettu', 'ketunkoti-features' );
        default:
            return __( 'luonnos', 'ketunkoti-features' );
    }
}

/**
 * Collects the portfolio for the overview page.
 *
 * Apartments are grouped by owner term name (sorted), with a "no owner" group
 * last when it has posts. Published and private apartments are counted in the
 * totals; draft, pending and scheduled ones are listed as `drafts` with their
 * `status` and left out of the totals.
 *
 * @return array{combined: array<string, mixed>, groups: array<int, array<string, mixed>>} Overview data.
 */
function get_portfolio_overview(): array {
    $posts = get_posts(
        [
            'post_type'              => PORTFOLIO_POST_TYPE,
            'post_status'            => array_merge( get_portfolio_counted_statuses(), [ 'draft', 'pending', 'future' ] ),
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

        // Raw title: get_the_title() would add the "Private:" prefix and run texturize.
        $title = (string) get_post_field( 'post_title', $post->ID );

        if ( ! in_array( $post->post_status, get_portfolio_counted_statuses(), true ) ) {
            $by_owner[ $owner ]['drafts'][] = [
                'id'     => (int) $post->ID,
                'title'  => $title,
                'status' => $post->post_status,
            ];
            continue;
        }

        $fields = get_portfolio_field_values( $post->ID );

        $by_owner[ $owner ]['apartments'][] = [
            'id'               => (int) $post->ID,
            'title'            => $title,
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
 * Returns the HTML of the summary table.
 *
 * @param array<string, mixed> $overview Overview data.
 * @return string Escaped HTML.
 */
function get_portfolio_summary_html( array $overview ): string {
    // A list of [ label, totals, is total ] triples, so an owner named like a fixed row cannot overwrite it.
    $rows   = [];
    $header = [
        __( 'Asuntoja', 'ketunkoti-features' ),
        __( 'Vapaata vakuutta', 'ketunkoti-features' ),
        __( 'Velat', 'ketunkoti-features' ),
        __( 'Nettovarallisuus', 'ketunkoti-features' ),
        __( 'Asuntojen arvo', 'ketunkoti-features' ),
        __( 'Velkavipu', 'ketunkoti-features' ),
    ];

    foreach ( $overview['groups'] as $group ) {
        $rows[] = [ $group['name'], $group['totals'], false ];
    }

    // The combined row comes last, as the total row.
    $rows[] = [ __( 'Yhteensä', 'ketunkoti-features' ), $overview['combined'], true ];

    $html  = '<div class="ketunkoti-portfolio-scroll" style="overflow-x: auto;" tabindex="0" role="region" aria-labelledby="ketunkoti-portfolio-caption-summary">';
    $html .= '<table class="ketunkoti-portfolio-table ketunkoti-portfolio-summary">';
    $html .= '<caption id="ketunkoti-portfolio-caption-summary">' . esc_html__( 'Salkun yhteenveto', 'ketunkoti-features' ) . '</caption>';
    $html .= '<thead><tr><td><span class="screen-reader-text">' . esc_html__( 'Omistaja', 'ketunkoti-features' ) . '</span></td>';

    foreach ( $header as $label ) {
        $html .= '<th scope="col">' . esc_html( $label ) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ( $rows as [ $name, $totals, $is_total ] ) {
        $html .= '<tr' . ( $is_total ? ' class="ketunkoti-portfolio-total"' : '' ) . '><th scope="row">' . esc_html( (string) $name ) . '</th>';
        $html .= '<td>' . esc_html( (string) (int) $totals['count'] ) . '</td>';
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['free_collateral'] ) ) . '</td>';
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
    $html .= '<td>' . esc_html( format_portfolio_euros( $r['free_collateral'] ) ) . '</td></tr>';

    return $html;
}

/**
 * Returns the HTML of one owner group: heading, table and drafts line.
 *
 * @param array<string, mixed> $group Group data from the overview.
 * @param int                  $index Position of the group, used for a unique caption ID.
 * @return string Escaped HTML.
 */
function get_portfolio_group_html( array $group, int $index = 0 ): string {
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
        $caption_id = 'ketunkoti-portfolio-caption-' . $index;

        $html .= '<div class="ketunkoti-portfolio-scroll" style="overflow-x: auto;" tabindex="0" role="region" aria-labelledby="' . esc_attr( $caption_id ) . '">';
        $html .= '<table class="ketunkoti-portfolio-table">';
        /* translators: %s: owner name. */
        $html .= '<caption id="' . esc_attr( $caption_id ) . '">' . esc_html( sprintf( __( 'Asunnot: %s', 'ketunkoti-features' ), $group['name'] ) ) . '</caption>';
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
        $html .= '<td>' . esc_html( format_portfolio_euros( $totals['free_collateral'] ) ) . '</td>';
        $html .= '</tr></tfoot></table></div>';
    }

    if ( ! empty( $group['drafts'] ) ) {
        $links = [];

        foreach ( $group['drafts'] as $draft ) {
            $title     = '' !== $draft['title'] ? $draft['title'] : __( '(ei nimeä)', 'ketunkoti-features' );
            $edit_link = get_edit_post_link( $draft['id'] );
            $label     = ' (' . esc_html( get_portfolio_status_label( (string) ( $draft['status'] ?? 'draft' ) ) ) . ')';
            $links[]   = ( $edit_link
                ? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>'
                : esc_html( $title ) ) . $label;
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
    $html = get_portfolio_snapshot_notice_html() . get_portfolio_summary_html( $overview );

    if ( empty( $overview['groups'] ) ) {
        $html .= '<p>' . esc_html__( 'Salkussa ei ole vielä asuntoja.', 'ketunkoti-features' ) . '</p>';
    }

    foreach ( $overview['groups'] as $index => $group ) {
        $html .= get_portfolio_group_html( $group, (int) $index );
    }

    return $html . get_portfolio_snapshot_form_html() . get_portfolio_history_html( get_portfolio_snapshots() );
}

/**
 * Returns the one-time notice of the last "save now" and deletes it.
 *
 * @return string Escaped notice HTML, or an empty string when there is none.
 */
function get_portfolio_snapshot_notice_html(): string {
    $key    = 'ketunkoti_portfolio_snapshot_notice_' . get_current_user_id();
    $notice = get_transient( $key );

    if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
        return '';
    }

    delete_transient( $key );

    $class = 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : 'notice-success';

    return '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
}

/**
 * Returns the "save snapshot now" form with its explanation line.
 *
 * @return string Escaped HTML.
 */
function get_portfolio_snapshot_form_html(): string {
    $html  = '<form class="ketunkoti-portfolio-snapshot-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    $html .= '<input type="hidden" name="action" value="ketunkoti_portfolio_snapshot" />';
    $html .= wp_nonce_field( 'ketunkoti_portfolio_snapshot', '_wpnonce', true, false );
    $html .= '<button type="submit" class="button button-primary">' . esc_html__( 'Tallenna tilannekuva nyt', 'ketunkoti-features' ) . '</button>';
    $html .= '<p class="description">' . esc_html__( 'Kuukauden tilannekuva tallentuu automaattisesti kuun 1. päivänä. Painike korvaa kuluvan kuukauden tilannekuvan.', 'ketunkoti-features' ) . '</p>';

    return $html . '</form>';
}

/**
 * Formats the change of a euro amount with a sign.
 *
 * @param float $delta Current minus previous amount.
 * @return string `+x €`, `−x €` or `±0 €`.
 */
function format_portfolio_euro_change( float $delta ): string {
    if ( 0.0 === round( $delta ) ) {
        return "±0\u{00A0}€";
    }

    return ( $delta > 0 ? '+' : '' ) . format_portfolio_euros( $delta );
}

/**
 * Formats the change of a percentage as percentage points.
 *
 * @param float $delta Current minus previous percentage.
 * @return string `+x,y %-yks.`, `−x,y %-yks.` or `±0 %-yks.`.
 */
function format_portfolio_points_change( float $delta ): string {
    $rounded = round( $delta, 1 );

    if ( 0.0 === $rounded ) {
        return "±0\u{00A0}%-yks.";
    }

    $sign = $rounded > 0 ? '+' : "\u{2212}";

    return $sign . format_portfolio_cell_number( abs( $rounded ) ) . "\u{00A0}%-yks.";
}

/**
 * Returns a money cell of the history table: value and change from the previous row.
 *
 * @param float      $value    Current value.
 * @param float|null $previous Previous snapshot's value, or null for the oldest row.
 * @return string Escaped cell HTML.
 */
function get_portfolio_history_money_cell_html( float $value, ?float $previous ): string {
    $html = '<td>' . esc_html( format_portfolio_euros( $value ) );

    if ( null !== $previous ) {
        $html .= ' <span class="ketunkoti-portfolio-change">(' . esc_html( format_portfolio_euro_change( $value - $previous ) ) . ')</span>';
    }

    return $html . '</td>';
}

/**
 * Returns the "Kehitys" section: heading and the table of snapshots, newest first.
 *
 * @param array<int, array<string, mixed>> $snapshots Snapshot data arrays, oldest first.
 * @return string Escaped HTML.
 */
function get_portfolio_history_html( array $snapshots ): string {
    $html = '<h2>' . esc_html__( 'Kehitys', 'ketunkoti-features' ) . '</h2>';

    if ( empty( $snapshots ) ) {
        return $html . '<p>' . esc_html__( 'Ei vielä tilannekuvia. Ensimmäinen tallentuu kuun 1. päivänä, tai tallenna se nyt painikkeesta.', 'ketunkoti-features' ) . '</p>';
    }

    $header = [
        __( 'Kuukausi', 'ketunkoti-features' ),
        __( 'Asuntoja', 'ketunkoti-features' ),
        __( 'Asuntojen arvo', 'ketunkoti-features' ),
        __( 'Velat', 'ketunkoti-features' ),
        __( 'Nettovarallisuus', 'ketunkoti-features' ),
        __( 'Vapaata vakuutta', 'ketunkoti-features' ),
        __( 'Velkavipu', 'ketunkoti-features' ),
    ];

    $html .= '<div class="ketunkoti-portfolio-scroll" style="overflow-x: auto;" tabindex="0" role="region" aria-labelledby="ketunkoti-portfolio-caption-history">';
    $html .= '<table class="ketunkoti-portfolio-table ketunkoti-portfolio-history">';
    $html .= '<caption id="ketunkoti-portfolio-caption-history">' . esc_html__( 'Salkun kehitys kuukausittain', 'ketunkoti-features' ) . '</caption>';
    $html .= '<thead><tr>';

    foreach ( $header as $label ) {
        $html .= '<th scope="col">' . esc_html( $label ) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    // Newest first; the change compares each row to the previous snapshot in the list.
    for ( $i = count( $snapshots ) - 1; $i >= 0; $i-- ) {
        // Stored data may be incomplete (malformed): missing figures read as zero.
        $totals   = (array) $snapshots[ $i ]['combined'];
        $previous = $i > 0 ? (array) $snapshots[ $i - 1 ]['combined'] : null;

        $html .= '<tr><th scope="row">' . esc_html( (string) $snapshots[ $i ]['month'] ) . '</th>';
        $html .= '<td>' . esc_html( (string) (int) ( $totals['count'] ?? 0 ) ) . '</td>';

        foreach ( [ 'value', 'debts', 'net_worth', 'free_collateral' ] as $key ) {
            $html .= get_portfolio_history_money_cell_html( (float) ( $totals[ $key ] ?? 0 ), null === $previous ? null : (float) ( $previous[ $key ] ?? 0 ) );
        }

        $leverage = $totals['leverage'] ?? null;

        if ( null === $leverage ) {
            $html .= '<td>–</td>';
        } else {
            $html .= '<td>' . esc_html( format_portfolio_percent( (float) $leverage ) );

            if ( null !== $previous && null !== ( $previous['leverage'] ?? null ) ) {
                $html .= ' <span class="ketunkoti-portfolio-change">(' . esc_html( format_portfolio_points_change( (float) $leverage - (float) $previous['leverage'] ) ) . ')</span>';
            }

            $html .= '</td>';
        }

        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
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
    $hook   = get_portfolio_overview_hook();
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    // The overview page and the snapshot edit screen share the table styles.
    $is_snapshot_edit = $screen && 'post' === $screen->base && PORTFOLIO_SNAPSHOT_POST_TYPE === $screen->post_type;

    if ( ! $is_snapshot_edit && ( '' === $hook || $hook !== $hook_suffix ) ) {
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
