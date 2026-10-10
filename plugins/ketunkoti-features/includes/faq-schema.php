<?php
/**
 * FAQ schema for the core Accordion block.
 *
 * Adds a `ketunkotiFaqSchema` attribute to `core/accordion`. When it is on,
 * the accordion's questions and answers are collected while the page renders
 * and printed once in the footer as a single `FAQPage` JSON-LD object. The
 * editor toggle lives in `src/faq-schema.js`.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Name of the block attribute that opts an accordion into FAQ schema.
 *
 * Must match `FAQ_SCHEMA_ATTRIBUTE` in `src/faq-schema.js`.
 */
const FAQ_SCHEMA_ATTRIBUTE = 'ketunkotiFaqSchema';

/**
 * Registers the FAQ schema attribute on the Accordion block.
 *
 * Without the server side registration the attribute would be dropped from
 * the parsed block before rendering.
 *
 * @param array  $args       Block type registration arguments.
 * @param string $block_type Block type name.
 * @return array Filtered block type registration arguments.
 */
function register_faq_schema_attribute( $args, $block_type ): array {
    if ( 'core/accordion' !== $block_type ) {
        return $args;
    }

    $args['attributes'][ FAQ_SCHEMA_ATTRIBUTE ] = [
        'type'    => 'boolean',
        'default' => false,
    ];

    return $args;
}
add_filter( 'register_block_type_args', __NAMESPACE__ . '\register_faq_schema_attribute', 10, 2 );

/**
 * Stores and returns the questions collected on the current request.
 *
 * Every opted-in accordion on the page adds to the same list, so the page
 * gets one `FAQPage` object rather than one per accordion.
 *
 * @param array|null $questions Question entities to add, or null to only read.
 * @return array All question entities collected so far.
 */
function faq_schema_questions( ?array $questions = null ): array {
    static $collected = [];

    if ( null !== $questions ) {
        $collected = array_merge( $collected, $questions );
    }

    return $collected;
}

/**
 * Returns the text of the accordion heading's title.
 *
 * The title is a rich text attribute sourced from the saved markup, so it is
 * not in the parsed block's attributes. Only the title span is read, which
 * leaves out the toggle icon's text.
 *
 * @param string $html Saved accordion heading markup.
 * @return string Plain text question, or an empty string.
 */
function get_faq_question( string $html ): string {
    $processor = new \WP_HTML_Tag_Processor( $html );

    if ( ! $processor->next_tag(
        [
            'tag_name'   => 'SPAN',
            'class_name' => 'wp-block-accordion-heading__toggle-title',
        ]
    ) ) {
        return '';
    }

    $text  = '';
    $depth = 1;

    while ( $depth > 0 && $processor->next_token() ) {
        if ( '#text' === $processor->get_token_type() ) {
            $text .= $processor->get_modifiable_text();
        } elseif ( 'SPAN' === $processor->get_tag() ) {
            $depth += $processor->is_tag_closer() ? -1 : 1;
        }
    }

    return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Returns the answer HTML for an accordion panel.
 *
 * The panel's blocks are rendered and reduced to the tags Google accepts in
 * an answer, so images, embeds and layout wrappers do not end up in the
 * schema.
 *
 * @param array $panel Parsed `core/accordion-panel` block.
 * @return string Answer HTML, or an empty string when the panel has no text.
 */
function get_faq_answer( array $panel ): string {
    $html = '';

    foreach ( $panel['innerBlocks'] as $inner_block ) {
        $html .= render_block( $inner_block );
    }

    $allowed_tags = [
        'a'      => [ 'href' => true ],
        'b'      => [],
        'br'     => [],
        'div'    => [],
        'em'     => [],
        'h1'     => [],
        'h2'     => [],
        'h3'     => [],
        'h4'     => [],
        'h5'     => [],
        'h6'     => [],
        'i'      => [],
        'li'     => [],
        'ol'     => [],
        'p'      => [],
        'strong' => [],
        'ul'     => [],
    ];

    // Block serialization leaves blank lines between blocks; one is enough.
    $html = trim( preg_replace( '/\n\s*\n/', "\n", wp_kses( $html, $allowed_tags ) ) );

    // Panels left with only empty paragraphs count as unanswered.
    if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
        return '';
    }

    return $html;
}

/**
 * Collects the questions and answers of an opted-in accordion.
 *
 * Runs as a `render_block` filter only to see the block while the page
 * renders; the block markup itself is returned unchanged. Items without a
 * question or an answer are skipped, since Google rejects empty entries.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string Unchanged block HTML.
 */
function collect_faq_schema( $block_content, $block ): string {
    if ( empty( $block['attrs'][ FAQ_SCHEMA_ATTRIBUTE ] ) ) {
        return $block_content;
    }

    $questions = [];

    foreach ( $block['innerBlocks'] as $item ) {
        if ( 'core/accordion-item' !== $item['blockName'] ) {
            continue;
        }

        $question = '';
        $answer   = '';

        foreach ( $item['innerBlocks'] as $inner_block ) {
            if ( 'core/accordion-heading' === $inner_block['blockName'] ) {
                $question = get_faq_question( $inner_block['innerHTML'] );
            } elseif ( 'core/accordion-panel' === $inner_block['blockName'] ) {
                $answer = get_faq_answer( $inner_block );
            }
        }

        if ( '' === $question || '' === $answer ) {
            continue;
        }

        $questions[] = [
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $answer,
            ],
        ];
    }

    faq_schema_questions( $questions );

    return $block_content;
}
add_filter( 'render_block_core/accordion', __NAMESPACE__ . '\collect_faq_schema', 10, 2 );

/**
 * Prints the collected questions as `FAQPage` JSON-LD.
 *
 * Printed in the footer because block content renders after `wp_head` in
 * classic themes, and only on singular views: an FAQ in a post excerpt or
 * listing does not describe the listing page.
 *
 * @return void
 */
function print_faq_schema(): void {
    $questions = faq_schema_questions();

    if ( empty( $questions ) || ! is_singular() ) {
        return;
    }

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $questions,
    ];

    wp_print_inline_script_tag(
        wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ),
        [ 'type' => 'application/ld+json' ]
    );
}
add_action( 'wp_footer', __NAMESPACE__ . '\print_faq_schema' );
