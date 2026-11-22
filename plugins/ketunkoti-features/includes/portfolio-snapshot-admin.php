<?php
/**
 * Salkku snapshots in wp-admin: a read-only meta box and list table columns.
 *
 * Everything is rendered from the stored `portfolio_snapshot_data` only. A
 * snapshot is never recalculated, and the box has no form fields.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the stored data of a snapshot if it has a usable shape.
 *
 * @param int $post_id Snapshot post ID.
 * @return array<string, mixed>|null Data, or null when missing or malformed.
 */
function get_valid_portfolio_snapshot_data( int $post_id ): ?array {
	$data = get_post_meta( $post_id, PORTFOLIO_SNAPSHOT_DATA_META, true );

	if ( ! is_array( $data ) || ! isset( $data['combined'], $data['groups'], $data['apartments'] )
		|| ! is_array( $data['combined'] ) || ! is_array( $data['groups'] ) || ! is_array( $data['apartments'] ) ) {
		return null;
	}

	return $data;
}

/**
 * Formats a stored euro amount for a cell: "–" when missing or not numeric.
 *
 * @param array<string, mixed> $source Stored array.
 * @param string               $key    Key of the amount.
 * @return string Formatted amount or an en dash.
 */
function format_portfolio_snapshot_euros( array $source, string $key ): string {
	return isset( $source[ $key ] ) && is_numeric( $source[ $key ] )
		? format_portfolio_euros( (float) $source[ $key ] )
		: '–';
}

/**
 * Formats a stored leverage ratio for a cell: "–" when missing or not numeric.
 *
 * @param array<string, mixed> $source Stored totals.
 * @return string Percentage or an en dash.
 */
function format_portfolio_snapshot_leverage( array $source ): string {
	return isset( $source['leverage'] ) && is_numeric( $source['leverage'] )
		? format_portfolio_percent( (float) $source['leverage'] )
		: '–';
}

/**
 * Registers the "Tilannekuvan luvut" meta box.
 *
 * @return void
 */
function add_portfolio_snapshot_meta_box(): void {
	add_meta_box(
		'ketunkoti-portfolio-snapshot-figures',
		__( 'Tilannekuvan luvut', 'ketunkoti-features' ),
		__NAMESPACE__ . '\render_portfolio_snapshot_meta_box',
		PORTFOLIO_SNAPSHOT_POST_TYPE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_' . PORTFOLIO_SNAPSHOT_POST_TYPE, __NAMESPACE__ . '\add_portfolio_snapshot_meta_box' );

/**
 * Renders the meta box.
 *
 * @param \WP_Post $post Snapshot post.
 * @return void
 */
function render_portfolio_snapshot_meta_box( \WP_Post $post ): void {
	echo get_portfolio_snapshot_box_html( (int) $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built.
}

/**
 * Returns the summary table of a snapshot: stored groups, then the stored total.
 *
 * @param array<string, mixed> $data Valid snapshot data.
 * @return string Escaped HTML.
 */
function get_portfolio_snapshot_summary_html( array $data ): string {
	$rows = [];

	foreach ( $data['groups'] as $group ) {
		if ( is_array( $group ) ) {
			$rows[] = [ (string) ( $group['name'] ?? '–' ), (array) ( $group['totals'] ?? [] ), false ];
		}
	}

	$rows[] = [ __( 'Yhteensä', 'ketunkoti-features' ), $data['combined'], true ];

	$header = [
		__( 'Asuntoja', 'ketunkoti-features' ),
		__( 'Vapaata vakuutta', 'ketunkoti-features' ),
		__( 'Velat', 'ketunkoti-features' ),
		__( 'Nettovarallisuus', 'ketunkoti-features' ),
		__( 'Asuntojen arvo', 'ketunkoti-features' ),
		__( 'Velkavipu', 'ketunkoti-features' ),
	];

	$html  = '<div class="ketunkoti-portfolio-scroll" style="overflow-x: auto;" tabindex="0" role="region" aria-labelledby="ketunkoti-portfolio-caption-snapshot-summary">';
	$html .= '<table class="ketunkoti-portfolio-table ketunkoti-portfolio-summary">';
	$html .= '<caption id="ketunkoti-portfolio-caption-snapshot-summary">' . esc_html__( 'Tilannekuvan yhteenveto', 'ketunkoti-features' ) . '</caption>';
	$html .= '<thead><tr><td><span class="screen-reader-text">' . esc_html__( 'Omistaja', 'ketunkoti-features' ) . '</span></td>';

	foreach ( $header as $label ) {
		$html .= '<th scope="col">' . esc_html( $label ) . '</th>';
	}

	$html .= '</tr></thead><tbody>';

	foreach ( $rows as [ $name, $totals, $is_total ] ) {
		$count = isset( $totals['count'] ) && is_numeric( $totals['count'] ) ? (string) (int) $totals['count'] : '–';

		$html .= '<tr' . ( $is_total ? ' class="ketunkoti-portfolio-total"' : '' ) . '><th scope="row">' . esc_html( $name ) . '</th>';
		$html .= '<td>' . esc_html( $count ) . '</td>';
		$html .= '<td>' . esc_html( format_portfolio_snapshot_euros( $totals, 'free_collateral' ) ) . '</td>';
		$html .= '<td>' . esc_html( format_portfolio_snapshot_euros( $totals, 'debts' ) ) . '</td>';
		$html .= '<td>' . esc_html( format_portfolio_snapshot_euros( $totals, 'net_worth' ) ) . '</td>';
		$html .= '<td>' . esc_html( format_portfolio_snapshot_euros( $totals, 'value' ) ) . '</td>';
		$html .= '<td>' . esc_html( format_portfolio_snapshot_leverage( $totals ) ) . '</td></tr>';
	}

	return $html . '</tbody></table></div>';
}

/**
 * Returns one owner's block: heading, stored counted apartments and the uncounted line.
 *
 * @param string                           $owner      Owner name.
 * @param array<int, array<string, mixed>> $apartments Stored apartments of the owner.
 * @param int                              $index      Position, for a unique caption ID.
 * @return string Escaped HTML.
 */
function get_portfolio_snapshot_group_html( string $owner, array $apartments, int $index ): string {
	$columns = [
		'title'            => __( 'Asunto', 'ketunkoti-features' ),
		'estimated_value'  => __( 'Arvioitu arvo', 'ketunkoti-features' ),
		'company_loan'     => __( 'Yhtiölaina', 'ketunkoti-features' ),
		'bank_loan'        => __( 'Pankkilaina', 'ketunkoti-features' ),
		'debts'            => __( 'Velat', 'ketunkoti-features' ),
		'rent'             => __( 'Vuokra', 'ketunkoti-features' ),
		'charges'          => __( 'Vastikkeet', 'ketunkoti-features' ),
		'collateral_rate'  => __( 'Vakuus-%', 'ketunkoti-features' ),
		'collateral_value' => __( 'Vakuusarvo', 'ketunkoti-features' ),
		'used_collateral'  => __( 'Käytetyt vakuudet', 'ketunkoti-features' ),
		'free_collateral'  => __( 'Vapaata vakuutta', 'ketunkoti-features' ),
		'net_value'        => __( 'Nettoarvo', 'ketunkoti-features' ),
	];

	$counted   = [];
	$uncounted = [];

	foreach ( $apartments as $apartment ) {
		if ( ! empty( $apartment['counted'] ) ) {
			$counted[] = $apartment;
		} else {
			$uncounted[] = $apartment;
		}
	}

	$html = '<h3>' . esc_html( $owner ) . '</h3>';

	if ( ! empty( $counted ) ) {
		$caption_id = 'ketunkoti-portfolio-caption-snapshot-' . $index;

		$html .= '<div class="ketunkoti-portfolio-scroll" style="overflow-x: auto;" tabindex="0" role="region" aria-labelledby="' . esc_attr( $caption_id ) . '">';
		$html .= '<table class="ketunkoti-portfolio-table">';
		/* translators: %s: owner name. */
		$html .= '<caption id="' . esc_attr( $caption_id ) . '">' . esc_html( sprintf( __( 'Asunnot: %s', 'ketunkoti-features' ), $owner ) ) . '</caption><thead><tr>';

		foreach ( $columns as $label ) {
			$html .= '<th scope="col">' . esc_html( $label ) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $counted as $apartment ) {
			$title = isset( $apartment['title'] ) && '' !== $apartment['title'] ? (string) $apartment['title'] : __( '(ei nimeä)', 'ketunkoti-features' );
			$rate  = isset( $apartment['collateral_rate'] ) && is_numeric( $apartment['collateral_rate'] )
				? format_portfolio_cell_number( (float) $apartment['collateral_rate'] ) . "\u{00A0}%"
				: '–';

			$html .= '<tr><th scope="row">' . esc_html( $title ) . '</th>';

			foreach ( array_slice( array_keys( $columns ), 1 ) as $key ) {
				$cell  = 'collateral_rate' === $key ? $rate : format_portfolio_snapshot_euros( $apartment, $key );
				$html .= '<td>' . esc_html( $cell ) . '</td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
	}

	if ( ! empty( $uncounted ) ) {
		$items = [];

		foreach ( $uncounted as $apartment ) {
			$title   = isset( $apartment['title'] ) && '' !== $apartment['title'] ? (string) $apartment['title'] : __( '(ei nimeä)', 'ketunkoti-features' );
			$items[] = $title . ' (' . get_portfolio_status_label( (string) ( $apartment['status'] ?? 'draft' ) ) . ')';
		}

		$html .= '<p class="ketunkoti-portfolio-drafts">' . esc_html( __( 'Ei mukana summissa: ', 'ketunkoti-features' ) . implode( ', ', $items ) ) . '</p>';
	}

	return $html;
}

/**
 * Returns the HTML of the snapshot meta box, built from the stored data only.
 *
 * @param int $post_id Snapshot post ID.
 * @return string Escaped HTML.
 */
function get_portfolio_snapshot_box_html( int $post_id ): string {
	$data = get_valid_portfolio_snapshot_data( $post_id );

	if ( null === $data ) {
		return '<div class="notice notice-warning inline"><p>' . esc_html__( 'Tilannekuvan tiedot puuttuvat tai ovat virheelliset.', 'ketunkoti-features' ) . '</p></div>';
	}

	$taken   = strtotime( (string) ( $data['taken_at'] ?? '' ) );
	$trigger = 'manual' === ( $data['trigger'] ?? '' ) ? __( 'Painikkeella', 'ketunkoti-features' ) : __( 'Automaattinen', 'ketunkoti-features' );
	$when    = false === $taken ? '–' : wp_date( 'd.m.Y H.i', $taken );

	$html  = '<p>' . esc_html( __( 'Tallennettu: ', 'ketunkoti-features' ) . $when . ' · ' . $trigger ) . '</p>';
	$html .= get_portfolio_snapshot_summary_html( $data );

	foreach ( array_values( $data['groups'] ) as $index => $group ) {
		$name       = is_array( $group ) ? (string) ( $group['name'] ?? '' ) : '';
		$apartments = array_filter(
			$data['apartments'],
			static fn ( $apartment ): bool => is_array( $apartment ) && (string) ( $apartment['owner'] ?? '' ) === $name
		);

		$html .= get_portfolio_snapshot_group_html( $name, array_values( $apartments ), (int) $index );
	}

	return $html;
}

/**
 * Adds the stored-total columns to the snapshots list table.
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string> Columns with the figures after the title.
 */
function add_portfolio_snapshot_columns( array $columns ): array {
	$result = [];

	foreach ( $columns as $key => $label ) {
		$result[ $key ] = $label;

		if ( 'title' === $key ) {
			$result['portfolio_snapshot_value']     = __( 'Asuntojen arvo', 'ketunkoti-features' );
			$result['portfolio_snapshot_debts']     = __( 'Velat', 'ketunkoti-features' );
			$result['portfolio_snapshot_net_worth'] = __( 'Nettovarallisuus', 'ketunkoti-features' );
			$result['portfolio_snapshot_leverage']  = __( 'Velkavipu', 'ketunkoti-features' );
		}
	}

	return $result;
}
add_filter( 'manage_' . PORTFOLIO_SNAPSHOT_POST_TYPE . '_posts_columns', __NAMESPACE__ . '\add_portfolio_snapshot_columns' );

/**
 * Returns the escaped content of one stored-total column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Snapshot post ID.
 * @return string Escaped text, "–" for malformed data, empty for other columns.
 */
function get_portfolio_snapshot_column_html( string $column, int $post_id ): string {
	$keys = [
		'portfolio_snapshot_value'     => 'value',
		'portfolio_snapshot_debts'     => 'debts',
		'portfolio_snapshot_net_worth' => 'net_worth',
		'portfolio_snapshot_leverage'  => 'leverage',
	];

	if ( ! isset( $keys[ $column ] ) ) {
		return '';
	}

	$data = get_valid_portfolio_snapshot_data( $post_id );

	if ( null === $data ) {
		return '–';
	}

	return esc_html(
		'leverage' === $keys[ $column ]
			? format_portfolio_snapshot_leverage( $data['combined'] )
			: format_portfolio_snapshot_euros( $data['combined'], $keys[ $column ] )
	);
}

/**
 * Prints one stored-total column cell.
 *
 * @param string $column  Column key.
 * @param int    $post_id Snapshot post ID.
 * @return void
 */
function render_portfolio_snapshot_column( string $column, int $post_id ): void {
	echo get_portfolio_snapshot_column_html( $column, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built.
}
add_action( 'manage_' . PORTFOLIO_SNAPSHOT_POST_TYPE . '_posts_custom_column', __NAMESPACE__ . '\render_portfolio_snapshot_column', 10, 2 );

/**
 * Removes Quick Edit from the snapshot rows, so a month can't be hidden by accident.
 *
 * @param array<string, string> $actions Row actions.
 * @param \WP_Post              $post    Row post.
 * @return array<string, string> Row actions.
 */
function remove_portfolio_snapshot_quick_edit( array $actions, \WP_Post $post ): array {
	if ( PORTFOLIO_SNAPSHOT_POST_TYPE === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'] );
	}

	return $actions;
}
add_filter( 'post_row_actions', __NAMESPACE__ . '\remove_portfolio_snapshot_quick_edit', 10, 2 );

/**
 * Removes the bulk "Edit" action from the snapshots list.
 *
 * @param array<string, string> $actions Bulk actions.
 * @return array<string, string> Bulk actions.
 */
function remove_portfolio_snapshot_bulk_edit( array $actions ): array {
	unset( $actions['edit'] );

	return $actions;
}
add_filter( 'bulk_actions-edit-' . PORTFOLIO_SNAPSHOT_POST_TYPE, __NAMESPACE__ . '\remove_portfolio_snapshot_bulk_edit' );
