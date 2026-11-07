<?php
/**
 * Server render of a timeline step.
 *
 * The visible number is drawn by a CSS counter and hidden from screen readers,
 * which already announce the position from the surrounding ordered list.
 * Renders nothing for an empty step.
 *
 * @package Ketunkoti\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered step content.
 * @var WP_Block $block      Block instance.
 */

namespace Ketunkoti\Blocks;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
    return;
}

$ketunkoti_wrapper_attributes = get_block_wrapper_attributes( [ 'class' => 'timeline-item' ] );
?>
<li <?php echo $ketunkoti_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
    <span class="timeline-item__number" aria-hidden="true"></span>
    <div class="timeline-item__content">
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered inner blocks. ?>
    </div>
</li>
