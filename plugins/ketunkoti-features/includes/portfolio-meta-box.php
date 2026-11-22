<?php
/**
 * Salkku edit screen: the "Asunnon tiedot" meta box.
 *
 * One classic meta box holds every field, grouped under small headings, the
 * owner as radio buttons and a read-only line with the calculated values.
 * Saving is gated by a nonce and `manage_options`.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the "Asunnon tiedot" meta box.
 *
 * @return void
 */
function add_portfolio_meta_box(): void {
    add_meta_box(
        'ketunkoti-portfolio-details',
        __( 'Asunnon tiedot', 'ketunkoti-features' ),
        __NAMESPACE__ . '\render_portfolio_meta_box',
        PORTFOLIO_POST_TYPE,
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes_' . PORTFOLIO_POST_TYPE, __NAMESPACE__ . '\add_portfolio_meta_box' );

/**
 * Formats a stored number for an input in Finnish notation.
 *
 * A decimal comma, no thousands separator and no trailing zeros: 46.5 becomes
 * "46,5" and 189000.0 becomes "189000".
 *
 * @param float|int|string|null $value Stored value.
 * @return string Input value, empty when nothing is stored.
 */
function format_portfolio_input_number( $value ): string {
    if ( null === $value ) {
        return '';
    }

    $number = (float) $value;

    if ( floor( $number ) === $number ) {
        return (string) (int) $number;
    }

    return rtrim( rtrim( number_format( $number, 2, ',', '' ), '0' ), ',' );
}

/**
 * Prints the input control of one field.
 *
 * @param string                  $meta_key Meta key, also the input name and ID.
 * @param array<string, string>   $field    Field definition.
 * @param float|int|string|null   $value    Stored value.
 * @return void
 */
function render_portfolio_field( string $meta_key, array $field, $value ): void {
    if ( 'portfolio_home_id' === $meta_key ) {
        $homes = get_posts(
            [
                'post_type'      => PORTFOLIO_HOME_POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );
        ?>
        <select name="<?php echo esc_attr( $meta_key ); ?>" id="<?php echo esc_attr( $meta_key ); ?>">
            <option value="0"><?php esc_html_e( 'Ei ilmoitusta', 'ketunkoti-features' ); ?></option>
            <?php foreach ( $homes as $home ) : ?>
                <option value="<?php echo esc_attr( (string) $home->ID ); ?>" <?php selected( (int) $value, $home->ID ); ?>>
                    <?php echo esc_html( get_the_title( $home ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return;
    }

    if ( 'date' === $field['type'] ) {
        $type = 'date';
        $mode = '';
    } elseif ( 'number' === $field['type'] ) {
        $type = 'text';
        $mode = ' inputmode="decimal"';
    } else {
        $type = 'text';
        $mode = 'integer' === $field['type'] ? ' inputmode="numeric"' : '';
    }

    $display = 'number' === $field['type'] ? format_portfolio_input_number( $value ) : (string) ( $value ?? '' );
    ?>
    <input type="<?php echo esc_attr( $type ); ?>"<?php echo $mode; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed literal. ?> name="<?php echo esc_attr( $meta_key ); ?>" id="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( $display ); ?>" class="regular-text">
    <?php
}

/**
 * Renders the "Asunnon tiedot" meta box.
 *
 * @param \WP_Post $post Portfolio post being edited.
 * @return void
 */
function render_portfolio_meta_box( \WP_Post $post ): void {
    $values = get_portfolio_field_values( $post->ID );
    $groups = [];

    // Collect by group in first-seen order, so each heading appears once.
    foreach ( get_portfolio_fields() as $meta_key => $field ) {
        $groups[ $field['group'] ][ $meta_key ] = $field;
    }

    wp_nonce_field( 'ketunkoti_portfolio_save', 'ketunkoti_portfolio_nonce' );

    foreach ( $groups as $group => $fields ) {
        ?>
        <h3><?php echo esc_html( $group ); ?></h3>
        <table class="form-table" role="presentation">
            <?php foreach ( $fields as $meta_key => $field ) : ?>
                <tr>
                    <th scope="row"><label for="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
                    <td><?php render_portfolio_field( $meta_key, $field, $values[ $meta_key ] ); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php

        if ( array_key_first( $groups ) === $group ) {
            render_portfolio_owner_fieldset( $post->ID );
        }
    }

    $results = calculate_portfolio_apartment( $values );
    ?>
    <p class="ketunkoti-portfolio-results">
        <strong><?php esc_html_e( 'Nettoarvo', 'ketunkoti-features' ); ?>:</strong>
        <?php echo esc_html( format_portfolio_euros( $results['net_value'] ) ); ?>
        &middot;
        <strong><?php esc_html_e( 'Vakuusarvo', 'ketunkoti-features' ); ?>:</strong>
        <?php echo esc_html( format_portfolio_euros( $results['collateral_value'] ) ); ?>
        &middot;
        <strong><?php esc_html_e( 'Vapaata vakuutta', 'ketunkoti-features' ); ?>:</strong>
        <?php echo esc_html( format_portfolio_euros( $results['free_collateral'] ) ); ?>
    </p>
    <?php
}

/**
 * Prints the owner radio buttons and the "new owner" input.
 *
 * Existing owner terms are radio buttons, so an apartment can only have one.
 * A typed new owner wins over the radio selection on save.
 *
 * @param int $post_id Portfolio post ID.
 * @return void
 */
function render_portfolio_owner_fieldset( int $post_id ): void {
    $terms   = get_terms(
        [
            'taxonomy'   => PORTFOLIO_OWNER_TAXONOMY,
            'hide_empty' => false,
        ]
    );
    $current = wp_get_object_terms( $post_id, PORTFOLIO_OWNER_TAXONOMY, [ 'fields' => 'ids' ] );
    $current = is_array( $current ) ? array_map( 'intval', $current ) : [];
    $terms   = is_array( $terms ) ? $terms : [];
    ?>
    <fieldset class="ketunkoti-portfolio-owner">
        <legend><strong><?php esc_html_e( 'Omistaja', 'ketunkoti-features' ); ?></strong></legend>
        <?php foreach ( $terms as $term ) : ?>
            <p>
                <label>
                    <input type="radio" name="portfolio_owner" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( (int) $term->term_id, $current, true ) ); ?>>
                    <?php echo esc_html( $term->name ); ?>
                </label>
            </p>
        <?php endforeach; ?>
        <p>
            <label for="portfolio_owner_new"><?php esc_html_e( 'Uusi omistaja', 'ketunkoti-features' ); ?></label>
            <input type="text" name="portfolio_owner_new" id="portfolio_owner_new" value="" class="regular-text">
        </p>
    </fieldset>
    <?php
}

/**
 * Saves the meta box fields and the owner.
 *
 * Returns early on autosaves, revisions, a missing or invalid nonce and users
 * without `manage_options`. All posted fields are validated first: on an error
 * nothing is written and the message is kept for one notice on the next load.
 *
 * @param int $post_id Portfolio post ID.
 * @return void
 */
function save_portfolio_meta_box( int $post_id ): void {
    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    $nonce = isset( $_POST['ketunkoti_portfolio_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ketunkoti_portfolio_nonce'] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, 'ketunkoti_portfolio_save' ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $raw = [];

    foreach ( array_keys( get_portfolio_fields() ) as $meta_key ) {
        if ( isset( $_POST[ $meta_key ] ) && is_scalar( $_POST[ $meta_key ] ) ) {
            $raw[ $meta_key ] = wp_unslash( $_POST[ $meta_key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by validate_portfolio_fields().
        }
    }

    $values = validate_portfolio_fields( $raw );
    $owner  = '';

    foreach ( [ 'portfolio_owner_new', 'portfolio_owner' ] as $owner_key ) {
        if ( '' === $owner && isset( $_POST[ $owner_key ] ) && is_scalar( $_POST[ $owner_key ] ) ) {
            $owner = sanitize_text_field( wp_unslash( (string) $_POST[ $owner_key ] ) );
        }
    }

    if ( is_wp_error( $values ) ) {
        set_transient( 'ketunkoti_portfolio_error_' . get_current_user_id(), $values->get_error_message(), 5 * MINUTE_IN_SECONDS );
        return;
    }

    write_portfolio_fields( $post_id, $values );

    if ( '' !== $owner ) {
        $owner_result = set_portfolio_owner( $post_id, $owner );

        if ( is_wp_error( $owner_result ) ) {
            set_transient( 'ketunkoti_portfolio_error_' . get_current_user_id(), $owner_result->get_error_message(), 5 * MINUTE_IN_SECONDS );
        }
    }
}
add_action( 'save_post_' . PORTFOLIO_POST_TYPE, __NAMESPACE__ . '\save_portfolio_meta_box' );

/**
 * Shows the stored save error once on the portfolio edit screen.
 *
 * @return void
 */
function show_portfolio_save_error(): void {
    $screen = get_current_screen();

    if ( ! $screen || PORTFOLIO_POST_TYPE !== $screen->post_type || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $key     = 'ketunkoti_portfolio_error_' . get_current_user_id();
    $message = get_transient( $key );

    if ( ! is_string( $message ) || '' === $message ) {
        return;
    }

    delete_transient( $key );
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s: validation error message. */
                    __( 'Asunnon tietoja ei tallennettu: %s', 'ketunkoti-features' ),
                    $message
                )
            );
            ?>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', __NAMESPACE__ . '\show_portfolio_save_error' );
