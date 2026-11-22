<?php
/**
 * The "New home" MCP prompt.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `ketunkoti/new-home`, an MCP prompt that walks Claude through
 * creating a new home by copying an existing one.
 *
 * The prompt only returns instructions. Every change is made by the tools it
 * names, so each write still asks the user for approval in the MCP client.
 */
final class New_Home_Prompt {

	/**
	 * Ability name of the prompt.
	 */
	public const NAME = 'ketunkoti/new-home';

	/**
	 * Registers the prompt. Runs on `wp_abilities_api_init`.
	 *
	 * @return void
	 */
	public static function register(): void {
		Registrar::register(
			self::NAME,
			[
				'label'               => __( 'New home', 'ketunkoti-ai' ),
				'description'         => __( 'Step-by-step instructions for adding a new home or rental by copying an existing listing: copy, fill in the home fields, photos, terms, title and content, featured image and SEO. The new listing stays a draft for the editor to publish.', 'ketunkoti-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'source'  => [
							'type'        => 'string',
							'description' => __( 'ID or search words of the home to copy. Optional: without it, Claude finds a similar home or asks.', 'ketunkoti-ai' ),
						],
						'details' => [
							'type'        => 'string',
							'description' => __( 'Details of the new home as free text: address, price, area, rooms, floor, availability and so on. Optional.', 'ketunkoti-ai' ),
						],
					],
				],
				'execute_callback'    => static fn( $input = [] ) => [
					'text' => self::text(
						is_array( $input ) ? (string) ( $input['source'] ?? '' ) : '',
						is_array( $input ) ? (string) ( $input['details'] ?? '' ) : '',
						self::has_seravo()
					),
				],
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'annotations'         => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
			],
			'prompt'
		);
	}

	/**
	 * Whether Seravo AI Assistant's content abilities are available.
	 *
	 * @return bool
	 */
	public static function has_seravo(): bool {
		return function_exists( 'wp_get_ability' ) && wp_get_ability( 'seravo-ai-assistant/update-post' ) instanceof \WP_Ability;
	}

	/**
	 * Builds the prompt text.
	 *
	 * @param string $source  ID or search words of the home to copy; may be empty.
	 * @param string $details Free-text details of the new home; may be empty.
	 * @param bool   $seravo  Whether Seravo AI Assistant's abilities are available.
	 * @return string
	 */
	public static function text( string $source, string $details, bool $seravo ): string {
		$source  = trim( $source );
		$details = trim( $details );

		$lines = [
			'Create a new home listing on Ketun koti by copying an existing one. Work step by step and tell the user what you did after each step. Every write asks the user for approval; if they decline, stop and ask how to continue.',
			'',
			'Source home: ' . ( '' !== $source ? $source : '(not given: find a similar home with ketunkoti/list-homes, or ask the user which one to copy)' ),
			'Details of the new home: ' . ( '' !== $details ? $details : '(not given: ask the user for them before step 3)' ),
			'',
			'1. Find the source. Use ketunkoti/list-homes (search, post_type) if the source is not an ID, then read it with ketunkoti/get-home. Confirm the choice with the user if more than one home fits.',
			'2. Copy it with ketunkoti/copy-home. Check the notes in the response (for example a duplicate title) and tell the user. The copy is a draft.',
			'3. Fill in the home_* fields that differ with ketunkoti/update-home-meta. Use Finnish number formats as given. Read the notes in the response: they flag fields that usually change together (Vuokra and Vakuus, Velaton hinta and Myyntihinta).',
		];

		if ( $seravo ) {
			$lines = array_merge(
				$lines,
				[
					'4. Photos: upload each one with seravo-ai-assistant/upload-media (https URL or base64) and a Finnish alt text that describes what the photo shows. Ask the user for the photos if you do not have them.',
					'5. Terms: find the term IDs with seravo-ai-assistant/list-taxonomies and seravo-ai-assistant/list-terms (home-city, home-type, home-rooms, home-status and so on). Create a missing term with seravo-ai-assistant/create-term only after the user agrees.',
					'6. Title, content, terms and featured image in one seravo-ai-assistant/update-post call. Write in Finnish, keep the source listing\'s block structure and tone, and change only what is different for this home. Never set the status to publish.',
					'7. SEO title and description with seravo-ai-assistant/update-seo. Read seravo-ai-assistant/get-seo first and keep the lengths reasonable.',
				]
			);
		} else {
			$lines = array_merge(
				$lines,
				[
					'4. Seravo AI Assistant is not active, so photos, terms, title, content, featured image and SEO cannot be changed from here. List for the user what to change in wp-admin, based on the details given.',
				]
			);
		}

		$lines[] = ( $seravo ? '8' : '5' ) . '. Finish: leave the listing as a draft. Give the user its edit link and a short summary of what was set and what still needs checking. The editor reviews and publishes it.';

		return implode( "\n", $lines );
	}
}
