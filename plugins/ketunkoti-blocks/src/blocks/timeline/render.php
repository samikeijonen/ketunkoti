<?php
/**
 * Server render of the timeline.
 *
 * Wraps the steps in an ordered list, so screen readers announce the number of
 * steps and their order. The line, dots and visible numbers come from CSS.
 * Renders nothing when the timeline has no steps.
 *
 * @package Ketunkoti\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered timeline items.
 * @var WP_Block $block      Block instance.
 */

namespace Ketunkoti\Blocks;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( '' === trim( $content ) ) {
    return;
}

// role="list" keeps the list semantics in Safari, which drops them for lists
// styled with list-style: none.
$ketunkoti_wrapper_attributes = get_block_wrapper_attributes(
    [
        'class' => 'timeline',
        'role'  => 'list',
    ]
);
?>
<ol <?php echo $ketunkoti_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered inner blocks. ?>
</ol>
