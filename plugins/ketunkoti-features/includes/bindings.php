<?php
/**
 * Block bindings registration.
 *
 * Exposes the "home" meta fields to core blocks so their values can be output
 * from templates and patterns without custom blocks or shortcodes.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Names of the block bindings sources registered by this plugin.
 *
 * Split into several sources - rather than one - purely so the "connect to a
 * field" picker in the editor groups them under separate headings, matching
 * the sidebar's "Kodin tiedot" / "Hinnat ja vastikkeet" / "Vuokratiedot" /
 * "Sijoituslaskelma" panels: WordPress's bindings UI only ever groups by
 * registered source name, with no sub-category within a single source. All
 * four share the same get_value_callback() below, since it already resolves
 * any key generically regardless of which source name reached it.
 */
const BINDINGS_SOURCE           = 'ketunkoti/home-details';
const PRICING_BINDINGS_SOURCE   = 'ketunkoti/home-pricing';
const RENTAL_BINDINGS_SOURCE    = 'ketunkoti/home-rental';
const INVESTMENT_BINDINGS_SOURCE = 'ketunkoti/home-investment';

/**
 * Binding keys that read a taxonomy rather than post meta.
 *
 * Keyed by the binding key, valued by the taxonomy it reads.
 */
const TAXONOMY_FIELDS = [
    'home_city'    => 'home-city',
    'home_rooms'   => 'home-rooms',
    'home_purpose' => PURPOSE_TAXONOMY,
    'home_status'  => 'home-status',
];

/**
 * Binding keys that are derived rather than stored.
 *
 * These have no post meta row of their own, so they must be resolved before the
 * meta lookup in get_binding_value(), which returns early on a missing value.
 */
const COMPUTED_FIELDS = [ 'home_rental_yield' ];

/**
 * Registers the block bindings source for the "home" meta fields.
 *
 * @return void
 */
function register_bindings() {
    register_block_bindings_source(
        BINDINGS_SOURCE,
        [
            'label'              => __( 'Kodin tiedot', 'ketunkoti-features' ),
            'get_value_callback' => __NAMESPACE__ . '\get_binding_value',
            'uses_context'       => [ 'postId', 'postType' ],
        ]
    );

    register_block_bindings_source(
        PRICING_BINDINGS_SOURCE,
        [
            'label'              => __( 'Hinnat ja vastikkeet', 'ketunkoti-features' ),
            'get_value_callback' => __NAMESPACE__ . '\get_binding_value',
            'uses_context'       => [ 'postId', 'postType' ],
        ]
    );

    register_block_bindings_source(
        RENTAL_BINDINGS_SOURCE,
        [
            'label'              => __( 'Vuokratiedot', 'ketunkoti-features' ),
            'get_value_callback' => __NAMESPACE__ . '\get_binding_value',
            'uses_context'       => [ 'postId', 'postType' ],
        ]
    );

    register_block_bindings_source(
        INVESTMENT_BINDINGS_SOURCE,
        [
            'label'              => __( 'Sijoituslaskelma', 'ketunkoti-features' ),
            'get_value_callback' => __NAMESPACE__ . '\get_binding_value',
            'uses_context'       => [ 'postId', 'postType' ],
        ]
    );
}
add_action( 'init', __NAMESPACE__ . '\register_bindings' );

/**
 * Returns a post's term names for a taxonomy as a plain, separated list.
 *
 * The Post Terms block always renders terms as links, which is not wanted
 * everywhere. Returning the names as plain text lets a bound paragraph show
 * the same information without a link.
 *
 * @param int    $post_id  Post to read the terms from.
 * @param string $taxonomy Taxonomy to read.
 * @return string|null Separated term names, or null when the post has none.
 */
function get_term_names( int $post_id, string $taxonomy ): ?string {
    $terms = get_the_terms( $post_id, $taxonomy );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return null;
    }

    return implode( ', ', wp_list_pluck( $terms, 'name' ) );
}

/**
 * Formats a whole euro amount.
 *
 * Used for prices and deposits, which are quoted in round euros. Zero stands in
 * for "nothing entered", so it yields null rather than "0 €".
 *
 * @param mixed  $value  Raw meta value.
 * @param string $format Translated sprintf format taking one %s, for example "%s €".
 * @return string|null Formatted amount, or null when there is nothing to show.
 */
function format_price( $value, string $format ): ?string {
    // round() rather than a bare (int) cast: truncating would render an entered
    // 1250,90 as 1250, quietly losing the euro rather than the cents.
    $price = (int) round( (float) $value );

    if ( $price <= 0 ) {
        return null;
    }

    return sprintf( $format, number_format_i18n( $price ) );
}

/**
 * Formats a recurring charge that may carry cents.
 *
 * @param mixed  $value  Raw meta value.
 * @param string $format Translated sprintf format taking one %s, for example "%s €/kk".
 * @return string|null Formatted charge, or null when there is nothing to show.
 */
function format_charge( $value, string $format ): ?string {
    $charge = (float) $value;

    if ( $charge <= 0 ) {
        return null;
    }

    return sprintf( $format, number_format_i18n( $charge, 2 ) );
}

/**
 * Formats the availability date.
 *
 * This is the one field where an empty value carries meaning: a home with no
 * date set is available now, so it renders as text rather than as nothing.
 *
 * The date is formatted straight off the parsed object rather than through
 * wp_date(), which would apply the site timezone to a value that has no time of
 * day and could shift it across midnight.
 *
 * @param mixed $value Raw meta value, an ISO `YYYY-MM-DD` string.
 * @return string Finnish formatted date, or the "available now" label.
 */
function format_availability( $value ): string {
    $date   = trim( (string) $value );
    $parsed = '' === $date ? false : \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );

    if ( false === $parsed ) {
        return __( 'Heti vapaa', 'ketunkoti-features' );
    }

    return $parsed->format( 'j.n.Y' );
}

/**
 * Calculates a home's net rental yield as a formatted percentage.
 *
 * The Finnish "vuokratuotto":
 *
 *     ((vuokra - hoitovastike) * 12) / velaton hinta * 100
 *
 * Two amounts are deliberately left out. Paaomavastike is not subtracted
 * because the denominator is the debt-free price, which already covers the
 * flat's share of the taloyhtio debt; subtracting the charge that services that
 * debt would count it twice. Vesimaksu is not subtracted because the tenant
 * pays it on top of the rent, so it is not a cost to the owner.
 *
 * A negative result is returned as it stands. It means the maintenance charge
 * exceeds the rent, which is real information about the listing.
 *
 * Keep in sync with the same calculation in `src/index.js`, which renders the
 * live figure in the editor sidebar.
 *
 * @param int $post_id Post to calculate for.
 * @return string|null Formatted percentage, or null when the inputs are missing.
 */
function get_rental_yield( int $post_id ): ?string {
    $rent  = (float) get_post_meta( $post_id, 'home_rent', true );
    $price = (float) get_post_meta( $post_id, 'home_debt_free_price', true );

    // Without a rent and a price there is no yield to state, and dividing by a
    // zero price would be a fatal error rather than a missing value.
    if ( $rent <= 0 || $price <= 0 ) {
        return null;
    }

    $maintenance = (float) get_post_meta( $post_id, 'home_maintenance_charge', true );
    $yield       = ( ( $rent - $maintenance ) * 12 ) / $price * 100;

    // Non-breaking space so the number never wraps away from its percent sign.
    return number_format_i18n( $yield, 1 ) . "\u{00A0}%";
}

/**
 * Returns a display-formatted meta value for a bound block attribute.
 *
 * The post ID comes from the block's context where available, falling back to
 * the main query. Relying on get_the_ID() alone breaks when a bound block is
 * rendered outside the main loop, such as inside a Query Loop.
 *
 * Values are returned as plain text rather than markup: these are numbers that
 * need no HTML, and plain text avoids any ambiguity about escaping in bound
 * block attributes.
 *
 * @param array    $source_args    Arguments passed to the binding, expects a 'key'.
 * @param WP_Block $block_instance The block instance being rendered.
 * @return string|null Formatted value, or null when there is nothing to show.
 */
function get_binding_value( array $source_args, $block_instance = null ): ?string {
    if ( empty( $source_args['key'] ) ) {
        return null;
    }

    $key     = $source_args['key'];
    $post_id = $block_instance->context['postId'] ?? get_the_ID();

    if ( ! $post_id ) {
        return null;
    }

    // Taxonomy backed values are not post meta, so they are resolved before
    // the meta lookup below.
    if ( isset( TAXONOMY_FIELDS[ $key ] ) ) {
        return get_term_names( $post_id, TAXONOMY_FIELDS[ $key ] );
    }

    // Derived values have no meta row to read, so they resolve before the
    // lookup below.
    if ( in_array( $key, COMPUTED_FIELDS, true ) ) {
        return 'home_rental_yield' === $key ? get_rental_yield( $post_id ) : null;
    }

    $value = get_post_meta( $post_id, $key, true );

    // The availability date is the one field where an empty value is meaningful
    // rather than absent, so it is formatted before the early return below.
    if ( 'home_available_from' === $key ) {
        return format_availability( $value );
    }

    if ( '' === $value || null === $value ) {
        return null;
    }

    switch ( $key ) {
        case 'home_area':
            $area = (float) $value;

            if ( $area <= 0 ) {
                return null;
            }

            /* translators: %s: Living area in square metres, for example "85,5". */
            return sprintf( __( '%s m²', 'ketunkoti-features' ), number_format_i18n( $area, 1 ) );

        case 'home_year_built':
            $year = (int) $value;

            if ( $year <= 0 ) {
                return null;
            }

            // Deliberately not run through number_format_i18n(): a year takes
            // no thousands separator, which would render 1987 as "1 987".
            return (string) $year;

        case 'home_floor':
        case 'home_address':
        case 'home_room_layout':
        case 'home_other_charges':
            // Free text fields are shown as entered.
            return (string) $value;

        case 'home_maintenance_charge':
        case 'home_capital_charge':
            /* translators: %s: Monthly charge in euros, for example "285,50". */
            return format_charge( $value, __( '%s €/kk', 'ketunkoti-features' ) );

        case 'home_water_charge':
            /* translators: %s: Water charge in euros per person per month, for example "25,00". */
            return format_charge( $value, __( '%s €/hlö/kk', 'ketunkoti-features' ) );

        case 'home_rent':
            // Rents are quoted in round euros, unlike the vastike charges above.
            /* translators: %s: Monthly rent in euros, for example "1 250". */
            return format_price( $value, __( '%s €/kk', 'ketunkoti-features' ) );

        case 'home_debt_free_price':
        case 'home_selling_price':
        case 'home_deposit':
            /* translators: %s: Amount in euros, for example "285 000". */
            return format_price( $value, __( '%s €', 'ketunkoti-features' ) );

        default:
            return null;
    }
}
