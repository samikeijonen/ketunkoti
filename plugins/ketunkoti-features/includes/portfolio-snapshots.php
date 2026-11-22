<?php
/**
 * Salkku snapshots: monthly frozen copies of the portfolio.
 *
 * A snapshot stores the money figures of every apartment and the stored totals
 * as they were when it was taken. History is never recalculated on read. The
 * post type is closed exactly like `portfolio` and gated by `manage_options`.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post type key of one portfolio snapshot.
 */
const PORTFOLIO_SNAPSHOT_POST_TYPE = 'portfolio_snapshot';

/**
 * Meta key of the snapshot month (`YYYY-MM`), used to find a month's snapshot.
 */
const PORTFOLIO_SNAPSHOT_MONTH_META = 'portfolio_snapshot_month';

/**
 * Meta key of the stored snapshot data array.
 */
const PORTFOLIO_SNAPSHOT_DATA_META = 'portfolio_snapshot_data';

/**
 * Name of the WP-Cron event that takes the monthly snapshot.
 */
const PORTFOLIO_SNAPSHOT_EVENT = 'ketunkoti_portfolio_monthly_snapshot';

/**
 * Name of the option that holds the last failed cron save.
 */
const PORTFOLIO_SNAPSHOT_ERROR_OPTION = 'ketunkoti_portfolio_snapshot_last_error';

/**
 * Registers the snapshot post type and its two meta keys.
 *
 * Registered like `portfolio` in privacy terms: no public output of any kind.
 * Creating posts by hand is disabled, so snapshots only come from the code.
 *
 * @return void
 */
function register_portfolio_snapshots(): void {
	$capabilities = [];

	foreach (
		[
			'edit_post',
			'read_post',
			'delete_post',
			'edit_posts',
			'edit_others_posts',
			'delete_posts',
			'publish_posts',
			'read_private_posts',
			'delete_private_posts',
			'delete_published_posts',
			'delete_others_posts',
			'edit_private_posts',
			'edit_published_posts',
		] as $capability
	) {
		$capabilities[ $capability ] = 'manage_options';
	}

	$capabilities['create_posts'] = 'do_not_allow';

	register_post_type(
		PORTFOLIO_SNAPSHOT_POST_TYPE,
		[
			'labels'              => [
				'name'          => __( 'Tilannekuvat', 'ketunkoti-features' ),
				'singular_name' => __( 'Tilannekuva', 'ketunkoti-features' ),
				'menu_name'     => __( 'Tilannekuvat', 'ketunkoti-features' ),
				'all_items'     => __( 'Tilannekuvat', 'ketunkoti-features' ),
				'edit_item'     => __( 'Muokkaa tilannekuvaa', 'ketunkoti-features' ),
				'search_items'  => __( 'Etsi tilannekuvia', 'ketunkoti-features' ),
				'not_found'     => __( 'Tilannekuvia ei löytynyt', 'ketunkoti-features' ),
			],
			'description'         => __( 'Salkun kuukausittaiset tilannekuvat.', 'ketunkoti-features' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'query_var'           => false,
			'rewrite'             => false,
			'has_archive'         => false,
			'show_in_rest'        => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . PORTFOLIO_POST_TYPE,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'can_export'          => true,
			'hierarchical'        => false,
			'supports'            => [ 'title' ],
			'capabilities'        => $capabilities,
			'map_meta_cap'        => false,
		]
	);

	register_post_meta(
		PORTFOLIO_SNAPSHOT_POST_TYPE,
		PORTFOLIO_SNAPSHOT_MONTH_META,
		[
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => __NAMESPACE__ . '\portfolio_meta_auth_callback',
		]
	);

	register_post_meta(
		PORTFOLIO_SNAPSHOT_POST_TYPE,
		PORTFOLIO_SNAPSHOT_DATA_META,
		[
			'type'          => 'object',
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => __NAMESPACE__ . '\portfolio_meta_auth_callback',
		]
	);
}
add_action( 'init', __NAMESPACE__ . '\register_portfolio_snapshots', 11 );

/**
 * Restores a snapshot to its previous status when it is untrashed.
 *
 * WordPress untrashes to draft by default, which would hide the month from
 * the history. Only snapshots are affected.
 *
 * @param string $new_status      Status WordPress would restore to.
 * @param int    $post_id         Post ID.
 * @param string $previous_status Status the post had before it was trashed.
 * @return string Status to restore to.
 */
function restore_portfolio_snapshot_status( string $new_status, int $post_id, string $previous_status ): string {
	if ( PORTFOLIO_SNAPSHOT_POST_TYPE !== get_post_type( $post_id ) ) {
		return $new_status;
	}

	return $previous_status;
}
add_filter( 'wp_untrash_post_status', __NAMESPACE__ . '\restore_portfolio_snapshot_status', 10, 3 );

/**
 * Stores the last failed cron save so the summary page can show it.
 *
 * @param int    $now     Timestamp of the failed run.
 * @param string $message Error message.
 * @return void
 */
function record_portfolio_snapshot_failure( int $now, string $message ): void {
	update_option(
		PORTFOLIO_SNAPSHOT_ERROR_OPTION,
		[
			'time'    => gmdate( 'c', $now ),
			'month'   => get_portfolio_snapshot_month( $now ),
			'message' => $message,
		],
		false
	);
}

/**
 * Returns the month of a timestamp in the site timezone.
 *
 * @param int $timestamp Unix timestamp.
 * @return string Month as `YYYY-MM`.
 */
function get_portfolio_snapshot_month( int $timestamp ): string {
	return wp_date( 'Y-m', $timestamp );
}

/**
 * Builds the stored row of one apartment.
 *
 * Only money figures are kept: address, area, year built and floor are left
 * out on purpose, so history holds as little personal data as it can.
 *
 * @param int                  $id      Apartment post ID.
 * @param string               $title   Raw apartment title.
 * @param string               $owner   Owner name or the no-owner label.
 * @param array<string, mixed> $fields  Typed apartment fields.
 * @param array<string, float> $results Calculated figures.
 * @param bool                 $counted Whether the apartment feeds the totals.
 * @return array<string, mixed> Stored apartment row.
 */
function build_portfolio_snapshot_apartment( int $id, string $title, string $owner, array $fields, array $results, bool $counted ): array {
	return [
		'id'                   => $id,
		'title'                => $title,
		'owner'                => $owner,
		'status'               => (string) get_post_status( $id ),
		'estimated_value'      => $fields['portfolio_estimated_value'],
		'company_loan'         => $fields['portfolio_company_loan'],
		'bank_loan'            => $fields['portfolio_bank_loan'],
		'used_collateral'      => $fields['portfolio_used_collateral'],
		'collateral_rate'      => $fields['portfolio_collateral_rate'],
		'rent'                 => $fields['portfolio_rent'],
		'charges'              => $fields['portfolio_charges'],
		'debt_free_price'      => $fields['portfolio_debt_free_price'],
		'company_loan_checked' => $fields['portfolio_company_loan_checked'],
		'debts'                => $results['debts'],
		'collateral_value'     => $results['collateral_value'],
		'free_collateral'      => $results['free_collateral'],
		'net_value'            => $results['net_value'],
		'counted'              => $counted,
	];
}

/**
 * Builds the snapshot data of the portfolio as it is right now.
 *
 * Totals come from `get_portfolio_overview()`, so a snapshot equals what the
 * summary page showed. Apartments that the overview leaves out of the totals
 * (draft, pending, future) are read and calculated here and stored with
 * `counted => false`; they never feed the stored totals.
 *
 * @param string $trigger   What took the snapshot: `cron` or `manual`.
 * @param int    $timestamp Moment the snapshot is taken.
 * @return array<string, mixed> Snapshot data, shape version 1.
 */
function build_portfolio_snapshot_data( string $trigger, int $timestamp ): array {
	$overview   = get_portfolio_overview();
	$apartments = [];
	$groups     = [];

	foreach ( $overview['groups'] as $group ) {
		foreach ( $group['apartments'] as $apartment ) {
			$apartments[] = build_portfolio_snapshot_apartment(
				(int) $apartment['id'],
				(string) $apartment['title'],
				(string) $group['name'],
				$apartment['fields'],
				$apartment['results'],
				true
			);
		}

		foreach ( $group['drafts'] as $draft ) {
			$fields       = get_portfolio_field_values( (int) $draft['id'] );
			$apartments[] = build_portfolio_snapshot_apartment(
				(int) $draft['id'],
				(string) $draft['title'],
				(string) $group['name'],
				$fields,
				calculate_portfolio_apartment( $fields ),
				false
			);
		}

		$groups[] = [
			'name'   => (string) $group['name'],
			'totals' => $group['totals'],
		];
	}

	return [
		'version'    => 1,
		'month'      => get_portfolio_snapshot_month( $timestamp ),
		'taken_at'   => gmdate( 'c', $timestamp ),
		'trigger'    => $trigger,
		'apartments' => $apartments,
		'groups'     => $groups,
		'combined'   => $overview['combined'],
	];
}

/**
 * Returns the ID of the published snapshot of a month.
 *
 * Trashed and other non-published snapshots are ignored.
 *
 * @param string $month Month as `YYYY-MM`.
 * @return int Snapshot post ID, or 0 when the month has none.
 */
function get_portfolio_snapshot_id( string $month ): int {
	$ids = get_posts(
		[
			'post_type'              => PORTFOLIO_SNAPSHOT_POST_TYPE,
			'post_status'            => 'publish',
			'meta_key'               => PORTFOLIO_SNAPSHOT_MONTH_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A handful of posts.
			'meta_value'             => $month, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- A handful of posts.
			'posts_per_page'         => 1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		]
	);

	return empty( $ids ) ? 0 : (int) $ids[0];
}

/**
 * Saves the snapshot of the month a timestamp falls in.
 *
 * A month that already has a published snapshot is replaced in place: same
 * post, new data, date and trigger. A trashed snapshot is ignored, so its
 * month gets a new post.
 *
 * @param string   $trigger   What takes the snapshot: `cron` or `manual`.
 * @param int|null $timestamp Moment of the snapshot, now when null.
 * @return int|\WP_Error Snapshot post ID, or an error when saving failed.
 */
function save_portfolio_snapshot( string $trigger, ?int $timestamp = null ): int|\WP_Error {
	if ( ! in_array( $trigger, [ 'cron', 'manual' ], true ) ) {
		return new \WP_Error(
			'ketunkoti_portfolio_invalid_trigger',
			__( 'Tilannekuvan tallennustapa ei kelpaa.', 'ketunkoti-features' )
		);
	}

	$timestamp = $timestamp ?? time();
	$data      = build_portfolio_snapshot_data( $trigger, $timestamp );
	$month     = $data['month'];
	$existing  = get_portfolio_snapshot_id( $month );

	$post = [
		'post_type'     => PORTFOLIO_SNAPSHOT_POST_TYPE,
		'post_status'   => 'publish',
		'post_title'    => $month,
		'post_date'     => wp_date( 'Y-m-d H:i:s', $timestamp ),
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $timestamp ),
		'post_author'   => get_current_user_id(),
		// Stored before save_post hooks fire, so a failing hook can't leave a post without its month.
		'meta_input'    => [
			PORTFOLIO_SNAPSHOT_MONTH_META => $month,
			PORTFOLIO_SNAPSHOT_DATA_META  => $data,
		],
	];

	/*
	 * A snapshot is never scheduled. WordPress turns a publish post with a
	 * date ahead of now into `future`, so keep it published for the save.
	 */
	$keep_published = static function ( array $post_data ): array {
		if ( PORTFOLIO_SNAPSHOT_POST_TYPE === ( $post_data['post_type'] ?? '' ) && 'future' === ( $post_data['post_status'] ?? '' ) ) {
			$post_data['post_status'] = 'publish';
		}

		return $post_data;
	};
	add_filter( 'wp_insert_post_data', $keep_published );

	try {
		if ( $existing > 0 ) {
			$post['ID'] = $existing;
			$post_id    = wp_update_post( wp_slash( $post ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $post ), true );
		}
	} finally {
		// Always detach the filter, even when a save_post hook throws.
		remove_filter( 'wp_insert_post_data', $keep_published );
	}

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	return (int) $post_id;
}

/**
 * Returns the stored data of the published snapshots, oldest month first.
 *
 * Entries whose data is not an array with `month` and `combined` are skipped.
 * Figures are returned as stored, never recalculated.
 *
 * @param string|null $from First month to include (`YYYY-MM`), or null for no lower bound.
 * @param string|null $to   Last month to include (`YYYY-MM`), or null for no upper bound.
 * @return array<int, array<string, mixed>> Snapshot data arrays.
 */
function get_portfolio_snapshots( ?string $from = null, ?string $to = null ): array {
	$ids = get_posts(
		[
			'post_type'              => PORTFOLIO_SNAPSHOT_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		]
	);

	// At most one entry per month: when a race left two, the newest post_date wins.
	$by_month = [];

	foreach ( $ids as $id ) {
		$data = get_post_meta( (int) $id, PORTFOLIO_SNAPSHOT_DATA_META, true );

		if ( ! is_array( $data ) || ! isset( $data['month'], $data['combined'] ) ) {
			continue;
		}

		if ( ( null !== $from && $data['month'] < $from ) || ( null !== $to && $data['month'] > $to ) ) {
			continue;
		}

		// Compare post_date first and the higher post ID on a tie, so the pick is deterministic.
		$post_date = (string) get_post_field( 'post_date', (int) $id );
		$order_key = $post_date . ' ' . str_pad( (string) $id, 20, '0', STR_PAD_LEFT );

		if ( isset( $by_month[ $data['month'] ] ) && strcmp( $order_key, $by_month[ $data['month'] ]['date'] ) <= 0 ) {
			continue;
		}

		$by_month[ $data['month'] ] = [
			'date' => $order_key,
			'data' => $data,
		];
	}

	$snapshots = array_column( $by_month, 'data' );

	usort(
		$snapshots,
		static fn ( array $a, array $b ): int => strcmp( (string) $a['month'], (string) $b['month'] )
	);

	return $snapshots;
}

/**
 * Returns the time of the next monthly run.
 *
 * @param int $now Unix timestamp the next run is counted from.
 * @return int UTC timestamp of 00:30 site time on the 1st of the month after `$now`'s site-time month.
 */
function get_next_portfolio_snapshot_time( int $now ): int {
	return ( new \DateTimeImmutable( '@' . $now ) )
		->setTimezone( wp_timezone() )
		->modify( 'first day of next month' )
		->setTime( 0, 30 )
		->getTimestamp();
}

/**
 * Schedules the monthly event when none is pending.
 *
 * Cheap enough for `init`: it only checks `wp_next_scheduled()`. The first run
 * is scheduled for now, so the first snapshot is taken right after the code
 * goes live.
 *
 * @return void
 */
function schedule_portfolio_snapshot_event(): void {
	if ( false === wp_next_scheduled( PORTFOLIO_SNAPSHOT_EVENT ) ) {
		wp_schedule_single_event( time(), PORTFOLIO_SNAPSHOT_EVENT );
	}
}
add_action( 'init', __NAMESPACE__ . '\schedule_portfolio_snapshot_event' );

/**
 * Replaces the pending monthly event with a single one at a given time.
 *
 * @param int $timestamp UTC timestamp of the next run.
 * @return void
 */
function reschedule_portfolio_snapshot_event( int $timestamp ): void {
	wp_clear_scheduled_hook( PORTFOLIO_SNAPSHOT_EVENT );
	wp_schedule_single_event( $timestamp, PORTFOLIO_SNAPSHOT_EVENT );
}

/**
 * Cron callback: saves this month's snapshot if the month has none yet.
 *
 * Empty portfolio: nothing saved, retry in a day. Existing or saved: next run
 * on the 1st of next month. Error or exception: logged, retry in an hour so a
 * failed month is retried instead of skipped.
 *
 * @param int|null $now Current timestamp, time() when null (tests pass a fixed one).
 * @return string Outcome: `saved`, `exists`, `empty` or `failed`.
 */
function run_portfolio_snapshot_cron( ?int $now = null ): string {
	// WP-Cron passes no arguments, but guard against stray ones.
	$now = is_int( $now ) ? $now : time();

	$apartments = get_posts(
		[
			'post_type'              => PORTFOLIO_POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]
	);

	if ( empty( $apartments ) ) {
		reschedule_portfolio_snapshot_event( $now + DAY_IN_SECONDS );
		return 'empty';
	}

	if ( get_portfolio_snapshot_id( get_portfolio_snapshot_month( $now ) ) > 0 ) {
		delete_option( PORTFOLIO_SNAPSHOT_ERROR_OPTION );
		reschedule_portfolio_snapshot_event( get_next_portfolio_snapshot_time( $now ) );
		return 'exists';
	}

	try {
		$result = save_portfolio_snapshot( 'cron', $now );
	} catch ( \Throwable $error ) {
		$result = new \WP_Error( 'ketunkoti_portfolio_snapshot_exception', $error->getMessage() );
	}

	if ( is_wp_error( $result ) ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logged to WP_DEBUG_LOG on purpose.
		error_log( 'Ketunkoti portfolio snapshot failed: ' . $result->get_error_message() );
		record_portfolio_snapshot_failure( $now, $result->get_error_message() );
		reschedule_portfolio_snapshot_event( $now + HOUR_IN_SECONDS );
		return 'failed';
	}

	// The save must be findable by its month, otherwise the next run would duplicate it.
	if ( 0 === get_portfolio_snapshot_id( get_portfolio_snapshot_month( $now ) ) ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logged to WP_DEBUG_LOG on purpose.
		error_log( 'Ketunkoti portfolio snapshot failed: saved snapshot is not found by its month.' );
		record_portfolio_snapshot_failure( $now, 'saved snapshot is not found by its month' );
		reschedule_portfolio_snapshot_event( $now + HOUR_IN_SECONDS );
		return 'failed';
	}

	delete_option( PORTFOLIO_SNAPSHOT_ERROR_OPTION );
	reschedule_portfolio_snapshot_event( get_next_portfolio_snapshot_time( $now ) );
	return 'saved';
}
add_action( PORTFOLIO_SNAPSHOT_EVENT, __NAMESPACE__ . '\run_portfolio_snapshot_cron' );

/**
 * Handles the "save snapshot now" form post.
 *
 * Verifies the nonce and `manage_options`, saves a manual snapshot, stores a
 * one-time notice for the current user and redirects back to the overview.
 *
 * @return void
 */
function handle_portfolio_snapshot_request(): void {
	check_admin_referer( 'ketunkoti_portfolio_snapshot' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Ei oikeuksia tilannekuvan tallentamiseen.', 'ketunkoti-features' ), '', [ 'response' => 403 ] );
	}

	$result = save_portfolio_snapshot( 'manual' );

	if ( is_wp_error( $result ) ) {
		$notice = [
			'type'    => 'error',
			'message' => $result->get_error_message(),
		];
	} else {
		$month  = (string) get_post_meta( $result, PORTFOLIO_SNAPSHOT_MONTH_META, true );
		$notice = [
			'type'    => 'success',
			/* translators: %s: month as YYYY-MM. */
			'message' => sprintf( __( 'Tilannekuva tallennettu kuukaudelle %s.', 'ketunkoti-features' ), $month ),
		];
	}

	set_transient( 'ketunkoti_portfolio_snapshot_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );

	wp_safe_redirect( admin_url( 'edit.php?post_type=' . PORTFOLIO_POST_TYPE . '&page=' . PORTFOLIO_OVERVIEW_SLUG ) );
	exit;
}
add_action( 'admin_post_ketunkoti_portfolio_snapshot', __NAMESPACE__ . '\handle_portfolio_snapshot_request' );
