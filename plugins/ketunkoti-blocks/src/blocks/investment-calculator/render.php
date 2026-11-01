<?php
/**
 * Server render of the investment calculator.
 *
 * Renders the investor's fields with example values (or the values of a shared
 * link's URL parameters), the first-year results, ratios and two sensitivity
 * tables. The Interactivity API store in `view.js`
 * recalculates everything as the investor types. Needs no post data.
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

// A shared link carries the fields in the URL; read-only display, so no nonce.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, every value is validated.
$ketunkoti_fields  = get_investment_fields_from_query( wp_unslash( $_GET ) );
$ketunkoti_results = calculate_investment_results( $ketunkoti_fields );

wp_interactivity_state( 'ketunkoti/investment-calculator', [ 'messages' => get_investment_messages() ] );

/*
 * The investor's fields in visual groups: group title => [ field => input attributes ].
 * The `method` field is a select and is rendered separately.
 */
$ketunkoti_groups = [
    __( 'Kohde', 'ketunkoti-blocks' )          => [
        'debtFreePrice'        => [ __( 'Velaton hinta (€)', 'ketunkoti-blocks' ), '0', '', 'any' ],
        'rent'                 => [ __( 'Vuokra (€/kk)', 'ketunkoti-blocks' ), '0', '', 'any' ],
        'area'                 => [ __( 'Pinta-ala (m²)', 'ketunkoti-blocks' ), '0', '', 'any' ],
        'maintenance'          => [ __( 'Hoitovastike (€/kk)', 'ketunkoti-blocks' ), '0', '', '0.01' ],
        'vacantMonths'         => [ __( 'Tyhjät kuukaudet vuodessa', 'ketunkoti-blocks' ), '0', '12', '1' ],
    ],
    __( 'Rahoitus', 'ketunkoti-blocks' )       => [
        'ownCapitalShare' => [ __( 'Omarahoitusosuus (%)', 'ketunkoti-blocks' ), '0', '100', 'any' ],
        'rate'            => [ __( 'Pankkilainan korko (%)', 'ketunkoti-blocks' ), '0', '', '0.1' ],
        'years'           => [ __( 'Laina-aika (vuotta)', 'ketunkoti-blocks' ), '1', '', '1' ],
        'method'          => null,
    ],
    __( 'Kulut ja verot', 'ketunkoti-blocks' ) => [
        'transferTaxRate' => [ __( 'Varainsiirtovero (%)', 'ketunkoti-blocks' ), '0', '100', '0.1' ],
        'renovation'      => [ __( 'Remonttivara (€)', 'ketunkoti-blocks' ), '0', '', 'any' ],
        'taxRate'         => [ __( 'Vuokratulon vero (%)', 'ketunkoti-blocks' ), '0', '100', '0.1' ],
    ],
];

$ketunkoti_methods = [
    'annuity'  => __( 'Annuiteetti', 'ketunkoti-blocks' ),
    'fixed'    => __( 'Kiinteä tasaerä', 'ketunkoti-blocks' ),
    'straight' => __( 'Tasalyhennys', 'ketunkoti-blocks' ),
];

// Result lists: list class => [ result key => label ].
$ketunkoti_lists = [
    'financing' => [
        'ownCapital'     => __( 'Omarahoitus', 'ketunkoti-blocks' ),
        'bankLoan'       => __( 'Pankkilaina', 'ketunkoti-blocks' ),
        'monthlyPayment' => __( 'Kuukausierä', 'ketunkoti-blocks' ),
    ],
    'results'   => [
        'rentalYield'       => __( 'Vuokratuotto (netto)', 'ketunkoti-blocks' ),
        'cashFlowBeforeTax' => __( 'Kassavirta ennen veroja', 'ketunkoti-blocks' ),
        'cashFlowAfterTax'  => __( 'Kassavirta verojen jälkeen', 'ketunkoti-blocks' ),
        'equityReturn'      => __( 'Oman pääoman tuotto', 'ketunkoti-blocks' ),
    ],
    'ratios'    => [
        'pricePerSquare'       => __( 'Neliöhinta', 'ketunkoti-blocks' ),
        'rentPerSquare'        => __( 'Neliövuokra', 'ketunkoti-blocks' ),
        'maintenancePerSquare' => __( 'Hoitovastike neliöltä', 'ketunkoti-blocks' ),
    ],
];

// Sensitivity tables: result key => [ caption, value column header, row labels ].
$ketunkoti_tables = [
    'rateTable'    => [
        __( 'Kassavirta verojen jälkeen, jos korko nousee', 'ketunkoti-blocks' ),
        __( 'Kassavirta', 'ketunkoti-blocks' ),
        [
            __( 'Nykyinen korko', 'ketunkoti-blocks' ),
            __( '+1 prosenttiyksikkö', 'ketunkoti-blocks' ),
            __( '+2 prosenttiyksikköä', 'ketunkoti-blocks' ),
            __( '+4 prosenttiyksikköä', 'ketunkoti-blocks' ),
        ],
    ],
    'vacancyTable' => [
        __( 'Vuokratuotto tyhjillä kuukausilla', 'ketunkoti-blocks' ),
        __( 'Vuokratuotto', 'ketunkoti-blocks' ),
        [
            __( 'Ei tyhjiä kuukausia', 'ketunkoti-blocks' ),
            __( '1 tyhjä kuukausi', 'ketunkoti-blocks' ),
            __( '2 tyhjää kuukautta', 'ketunkoti-blocks' ),
            __( '3 tyhjää kuukautta', 'ketunkoti-blocks' ),
        ],
    ],
];
?>
<div
    <?php echo get_block_wrapper_attributes( [ 'class' => 'investment-calculator' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>
    data-wp-interactive="ketunkoti/investment-calculator"
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core.
    echo wp_interactivity_data_wp_context(
        [
            'fields'     => $ketunkoti_fields,
            'results'    => $ketunkoti_results,
            'copyStatus' => '',
        ]
    );
    ?>
>
    <div class="investment-calculator__grid">
        <form class="investment-calculator__form" data-wp-on--submit="actions.preventSubmit">
            <?php foreach ( $ketunkoti_groups as $ketunkoti_group_title => $ketunkoti_inputs ) : ?>
                <div class="investment-calculator__group">
                    <p class="investment-calculator__group-title"><?php echo esc_html( $ketunkoti_group_title ); ?></p>
                    <?php foreach ( $ketunkoti_inputs as $ketunkoti_key => $ketunkoti_input ) : ?>
                        <?php $ketunkoti_input_id = wp_unique_id( 'investment-calculator-' . $ketunkoti_key . '-' ); ?>
                        <div class="investment-calculator__field">
                            <?php if ( 'method' === $ketunkoti_key ) : ?>
                                <label class="investment-calculator__label" for="<?php echo esc_attr( $ketunkoti_input_id ); ?>">
                                    <?php esc_html_e( 'Lyhennystapa', 'ketunkoti-blocks' ); ?>
                                </label>
                                <select
                                    class="investment-calculator__input"
                                    id="<?php echo esc_attr( $ketunkoti_input_id ); ?>"
                                    name="method"
                                    data-wp-bind--value="context.fields.method"
                                    data-wp-on--input="actions.update"
                                >
                                    <?php foreach ( $ketunkoti_methods as $ketunkoti_value => $ketunkoti_label ) : ?>
                                        <option value="<?php echo esc_attr( $ketunkoti_value ); ?>" <?php selected( $ketunkoti_fields['method'], $ketunkoti_value ); ?>>
                                            <?php echo esc_html( $ketunkoti_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <?php [ $ketunkoti_label, $ketunkoti_min, $ketunkoti_max, $ketunkoti_step ] = $ketunkoti_input; ?>
                                <label class="investment-calculator__label" for="<?php echo esc_attr( $ketunkoti_input_id ); ?>">
                                    <?php echo esc_html( $ketunkoti_label ); ?>
                                </label>
                                <input
                                    class="investment-calculator__input"
                                    id="<?php echo esc_attr( $ketunkoti_input_id ); ?>"
                                    name="<?php echo esc_attr( $ketunkoti_key ); ?>"
                                    type="number"
                                    min="<?php echo esc_attr( $ketunkoti_min ); ?>"
                                    step="<?php echo esc_attr( $ketunkoti_step ); ?>"
                                    <?php if ( '' !== $ketunkoti_max ) : ?>
                                        max="<?php echo esc_attr( $ketunkoti_max ); ?>"
                                    <?php endif; ?>
                                    autocomplete="off"
                                    value="<?php echo esc_attr( $ketunkoti_fields[ $ketunkoti_key ] ); ?>"
                                    data-wp-bind--value="context.fields.<?php echo esc_attr( $ketunkoti_key ); ?>"
                                    data-wp-on--input="actions.update"
                                />
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </form>

        <div class="investment-calculator__summary">
            <p class="investment-calculator__note">
                <?php esc_html_e( 'Koko velaton hinta rahoitetaan omarahoituksella ja pankkilainalla, eikä taloyhtiölainaa tai pääomavastiketta oleteta. Luvut ovat suuntaa antava arvio ensimmäiseltä vuodelta, eivät sitova tarjous.', 'ketunkoti-blocks' ); ?>
            </p>

            <div aria-live="polite">
                <?php foreach ( $ketunkoti_lists as $ketunkoti_list => $ketunkoti_items ) : ?>
                    <dl class="investment-calculator__list investment-calculator__list--<?php echo esc_attr( $ketunkoti_list ); ?>">
                        <?php foreach ( $ketunkoti_items as $ketunkoti_key => $ketunkoti_label ) : ?>
                            <div class="investment-calculator__row">
                                <dt><?php echo esc_html( $ketunkoti_label ); ?></dt>
                                <dd data-wp-text="context.results.<?php echo esc_attr( $ketunkoti_key ); ?>"><?php echo esc_html( $ketunkoti_results[ $ketunkoti_key ] ); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endforeach; ?>

                <p
                    class="investment-calculator__notice"
                    data-wp-text="context.results.notice"
                    data-wp-bind--hidden="!context.results.notice"
                    <?php echo '' === $ketunkoti_results['notice'] ? 'hidden' : ''; ?>
                ><?php echo esc_html( $ketunkoti_results['notice'] ); ?></p>
            </div>

            <?php foreach ( $ketunkoti_tables as $ketunkoti_key => [ $ketunkoti_caption, $ketunkoti_header, $ketunkoti_rows ] ) : ?>
                <table class="investment-calculator__table">
                    <caption><?php echo esc_html( $ketunkoti_caption ); ?></caption>
                    <thead>
                        <tr>
                            <td></td>
                            <th scope="col"><?php echo esc_html( $ketunkoti_header ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $ketunkoti_rows as $ketunkoti_index => $ketunkoti_row_label ) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html( $ketunkoti_row_label ); ?></th>
                                <td data-wp-text="context.results.<?php echo esc_attr( $ketunkoti_key . '.' . $ketunkoti_index ); ?>"><?php echo esc_html( $ketunkoti_results[ $ketunkoti_key ][ $ketunkoti_index ] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>

            <div class="investment-calculator__share">
                <button type="button" class="wp-element-button investment-calculator__copy" data-wp-on--click="actions.copyLink">
                    <?php esc_html_e( 'Kopioi laskurin linkki', 'ketunkoti-blocks' ); ?>
                </button>
                <p class="investment-calculator__copy-status" role="status" data-wp-text="context.copyStatus"></p>
            </div>
        </div>
    </div>
</div>
