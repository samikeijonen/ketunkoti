<?php
/**
 * Server render of the yield calculator.
 *
 * Renders the home's locked figures, the visitor's four fields and the first
 * results. The Interactivity API store in `view.js` recalculates the results
 * as the visitor types. Renders nothing for a home without a rent or a selling
 * price, which also covers rentals.
 *
 * @package Ketunkoti\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

namespace Ketunkoti\Blocks;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ketunkoti_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$ketunkoti_home    = $ketunkoti_post_id ? get_calculator_home( $ketunkoti_post_id ) : null;

if ( null === $ketunkoti_home ) {
    return;
}

$ketunkoti_fields = get_calculator_defaults( $ketunkoti_home );

wp_interactivity_state( 'ketunkoti/yield-calculator', [ 'messages' => get_calculator_messages() ] );

$ketunkoti_context = [
    'home'    => [
        'rent'          => $ketunkoti_home['rent'],
        'maintenance'   => $ketunkoti_home['maintenance'],
        'sellingPrice'  => $ketunkoti_home['selling_price'],
        'debtFreePrice' => $ketunkoti_home['debt_free_price'],
    ],
    'fields'  => $ketunkoti_fields,
    'results' => calculate_calculator_results( $ketunkoti_home, $ketunkoti_fields ),
];

/*
 * Locked figures from the home: label => formatted value, listed after the
 * omarahoitus in euros, which follows the visitor's share. An empty hoitovastike
 * is left out. Myyntihinta is not shown: the calculation starts from the
 * debt-free price. Pääomavastike and the taloyhtiö loan rate are not shown: the
 * calculation pays the taloyhtiölaina share off with the bank loan.
 */
$ketunkoti_figures = [
    __( 'Velaton hinta', 'ketunkoti-blocks' ) => number_format_i18n( $ketunkoti_home['debt_free_price'] ) . "\u{00A0}€",
];

if ( $ketunkoti_home['maintenance'] > 0 ) {
    $ketunkoti_figures[ __( 'Hoitovastike', 'ketunkoti-blocks' ) ] = number_format_i18n( $ketunkoti_home['maintenance'], 2 ) . "\u{00A0}€/kk";
}

// The visitor's fields: context key => label and number input attributes.
$ketunkoti_inputs = [
    'rent'            => [
        'label' => __( 'Vuokra (€/kk)', 'ketunkoti-blocks' ),
        'min'   => '0',
        'max'   => '',
        'step'  => 'any',
    ],
    'years'           => [
        'label' => __( 'Laina-aika (vuotta)', 'ketunkoti-blocks' ),
        'min'   => '1',
        'max'   => '',
        'step'  => '1',
    ],
    'rate'            => [
        'label' => __( 'Pankkilainan korko (%)', 'ketunkoti-blocks' ),
        'min'   => '0',
        'max'   => '',
        'step'  => '0.1',
    ],
    'ownCapitalShare' => [
        'label' => __( 'Omarahoitusosuus (%)', 'ketunkoti-blocks' ),
        'min'   => '0',
        'max'   => '100',
        'step'  => 'any',
    ],
];

// Results: context key => label.
$ketunkoti_results = [
    'rentalYield'  => __( 'Vuokratuotto', 'ketunkoti-blocks' ),
    'cashFlow'     => __( 'Kassavirta', 'ketunkoti-blocks' ),
    'equityReturn' => __( 'Oman pääoman tuotto', 'ketunkoti-blocks' ),
];

?>
<div
    <?php echo get_block_wrapper_attributes( [ 'class' => 'yield-calculator' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>
    data-wp-interactive="ketunkoti/yield-calculator"
    <?php echo wp_interactivity_data_wp_context( $ketunkoti_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>
>
    <div class="yield-calculator__grid">
        <form class="yield-calculator__fields" data-wp-on--submit="actions.preventSubmit">
            <?php foreach ( $ketunkoti_inputs as $ketunkoti_key => $ketunkoti_input ) : ?>
                <?php $ketunkoti_input_id = wp_unique_id( 'yield-calculator-' . $ketunkoti_key . '-' ); ?>
                <div class="yield-calculator__field">
                    <label class="yield-calculator__label" for="<?php echo esc_attr( $ketunkoti_input_id ); ?>">
                        <?php echo esc_html( $ketunkoti_input['label'] ); ?>
                    </label>
                    <input
                        class="yield-calculator__input"
                        id="<?php echo esc_attr( $ketunkoti_input_id ); ?>"
                        name="<?php echo esc_attr( $ketunkoti_key ); ?>"
                        type="number"
                        min="<?php echo esc_attr( $ketunkoti_input['min'] ); ?>"
                        step="<?php echo esc_attr( $ketunkoti_input['step'] ); ?>"
                        <?php if ( '' !== $ketunkoti_input['max'] ) : ?>
                            max="<?php echo esc_attr( $ketunkoti_input['max'] ); ?>"
                        <?php endif; ?>
                        autocomplete="off"
                        value="<?php echo esc_attr( $ketunkoti_fields[ $ketunkoti_key ] ); ?>"
                        data-wp-bind--value="context.fields.<?php echo esc_attr( $ketunkoti_key ); ?>"
                        data-wp-on--input="actions.update"
                    />
                </div>
            <?php endforeach; ?>
        </form>

        <div class="yield-calculator__summary">
            <p class="yield-calculator__note">
                <?php esc_html_e( 'Laskelmassa koko velaton hinta rahoitetaan omarahoituksella ja pankkilainalla, joten taloyhtiölaina maksetaan pois eikä pääomavastiketta jää.', 'ketunkoti-blocks' ); ?>
            </p>
            <dl class="yield-calculator__figures">
                <div class="yield-calculator__figure">
                    <dt><?php esc_html_e( 'Omarahoitus', 'ketunkoti-blocks' ); ?></dt>
                    <dd data-wp-text="context.results.ownCapital"><?php echo esc_html( $ketunkoti_context['results']['ownCapital'] ); ?></dd>
                </div>
                <?php foreach ( $ketunkoti_figures as $ketunkoti_label => $ketunkoti_value ) : ?>
                    <div class="yield-calculator__figure">
                        <dt><?php echo esc_html( $ketunkoti_label ); ?></dt>
                        <dd><?php echo esc_html( $ketunkoti_value ); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <div aria-live="polite">
                <dl class="yield-calculator__results">
                    <?php foreach ( $ketunkoti_results as $ketunkoti_key => $ketunkoti_label ) : ?>
                        <div class="yield-calculator__result">
                            <dt><?php echo esc_html( $ketunkoti_label ); ?></dt>
                            <dd data-wp-text="context.results.<?php echo esc_attr( $ketunkoti_key ); ?>"><?php echo esc_html( $ketunkoti_context['results'][ $ketunkoti_key ] ); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <p
                    class="yield-calculator__notice"
                    data-wp-text="context.results.notice"
                    data-wp-bind--hidden="!context.results.notice"
                    <?php echo '' === $ketunkoti_context['results']['notice'] ? 'hidden' : ''; ?>
                ><?php echo esc_html( $ketunkoti_context['results']['notice'] ); ?></p>
            </div>
        </div>
    </div>
</div>
