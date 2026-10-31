<?php
/**
 * Organization details for The SEO Framework's JSON-LD.
 *
 * The SEO Framework's Knowledge Graph settings only output the name, URL,
 * logo and social profiles. This adds the business details that have no
 * setting: description, legal name, VAT ID, contact point and address.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Public contact email, shared by the organization and its contact point.
 */
const ORGANIZATION_EMAIL = 'ketunkoti@foxnet.fi';

/**
 * Returns the extra properties for the `Organization` entity.
 *
 * Ketun koti is an auxiliary business name (aputoiminimi) of Fox Holding Oy,
 * so `name` stays as set in The SEO Framework and the company goes into
 * `legalName`.
 *
 * @return array Schema.org properties keyed by property name.
 */
function organization_schema_details(): array {
    return [
        'description'  => __( 'Ketun koti ostaa ja vuokraa asuntoja sekä myy osan niistä valmiiksi vuokrattuina sijoitusasuntoina suoraan vuokranantajalta.', 'ketunkoti-features' ),
        'legalName'    => 'Fox Holding Oy',
        'vatID'        => 'FI22272576',
        'email'        => ORGANIZATION_EMAIL,
        'contactPoint' => [
            '@type'             => 'ContactPoint',
            'contactType'       => 'customer service',
            'email'             => ORGANIZATION_EMAIL,
            'url'               => home_url( '/ota-yhteytta/' ),
            'availableLanguage' => 'fi',
        ],
        'address'      => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Ristiniementie 12 B 22',
            'postalCode'      => '02320',
            'addressLocality' => 'Espoo',
            'addressCountry'  => 'FI',
        ],
    ];
}

/**
 * Adds the organization details to The SEO Framework's schema graph.
 *
 * The graph only contains an `Organization` entity when the Knowledge Graph
 * is set to represent an organization, so this does nothing otherwise.
 * Values already set by The SEO Framework win over the ones added here.
 *
 * @param array $graph A sequential list of graph entities.
 * @return array Filtered graph.
 */
function add_organization_schema_details( $graph ): array {
    if ( ! is_array( $graph ) ) {
        return [];
    }

    foreach ( $graph as $index => $entity ) {
        if ( 'Organization' !== ( $entity['@type'] ?? '' ) ) {
            continue;
        }

        $graph[ $index ] = $entity + organization_schema_details();
    }

    return $graph;
}
add_filter( 'the_seo_framework_schema_graph_data', __NAMESPACE__ . '\add_organization_schema_details' );

/**
 * Page types for the pages that describe the business, keyed by page slug.
 *
 * The SEO Framework marks every page as `WebPage` and has no per-page setting.
 * Slugs are used rather than IDs because the IDs differ between environments.
 */
const ORGANIZATION_PAGE_TYPES = [
    'ketun-kodista' => 'AboutPage',
    'ota-yhteytta'  => 'ContactPage',
];

/**
 * Marks the about and contact pages with their own schema.org page types.
 *
 * The page entity also gets an `about` reference to the organization, the
 * same reference The SEO Framework adds to the front page. The organization's
 * `@id` is read from the `WebSite` entity's publisher, so nothing is added
 * when the Knowledge Graph represents a person.
 *
 * @param array $graph A sequential list of graph entities.
 * @return array Filtered graph.
 */
function set_organization_page_types( $graph ): array {
    if ( ! is_array( $graph ) || ! is_page() ) {
        return is_array( $graph ) ? $graph : [];
    }

    $type = ORGANIZATION_PAGE_TYPES[ get_post_field( 'post_name', get_queried_object_id() ) ] ?? null;

    if ( ! $type ) {
        return $graph;
    }

    $organization_id = null;

    foreach ( $graph as $entity ) {
        if ( 'WebSite' === ( $entity['@type'] ?? '' ) && 'Organization' === ( $entity['publisher']['@type'] ?? '' ) ) {
            $organization_id = $entity['publisher']['@id'] ?? null;
        }
    }

    foreach ( $graph as $index => $entity ) {
        if ( 'WebPage' !== ( $entity['@type'] ?? '' ) ) {
            continue;
        }

        $graph[ $index ]['@type'] = $type;

        if ( $organization_id && ! isset( $entity['about'] ) ) {
            $graph[ $index ]['about'] = [ '@id' => $organization_id ];
        }
    }

    return $graph;
}
add_filter( 'the_seo_framework_schema_graph_data', __NAMESPACE__ . '\set_organization_page_types' );
