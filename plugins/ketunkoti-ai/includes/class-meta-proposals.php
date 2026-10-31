<?php
/**
 * Approval flow for home_* changes on homes the assistant did not create.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

use MEOM\AI_Assistant\Settings;
use WP_Error;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stores proposed meta changes and applies them only after approval.
 *
 * A proposal changes nothing. It is applied only by the user who made it,
 * within TTL, with the assistant's "Allow applying changes from Claude on my
 * computer" switch on, and only if none of its fields changed since. Applied
 * changes are logged on the post with their before and after values.
 *
 * Holds no MCP-specific code, so the flow can move into meom-ai-assistant
 * later as meta support in its own change types.
 */
final class Meta_Proposals {

    /**
     * How long a proposal can be applied, in seconds.
     */
    public const TTL = 30 * MINUTE_IN_SECONDS;

    /**
     * Post meta that holds one row per applied proposal.
     */
    public const LOG_META = '_ketunkoti_ai_meta_log';

    /**
     * Transient key prefix of pending proposals.
     */
    private const TRANSIENT_PREFIX = 'ketunkoti_ai_proposal_';

    /**
     * Stores a proposed change and returns its before and after values.
     *
     * @param int   $post_id Post ID.
     * @param int   $user_id User who proposes, and the only one who may apply.
     * @param array $meta    Meta key => raw value; null or '' clears a field.
     * @return array|WP_Error Proposal ID, expiry and changes, or an error.
     */
    public static function propose( int $post_id, int $user_id, array $meta ) {
        if ( ! Settings::mcp_apply_allowed( $user_id ) ) {
            return self::apply_disabled();
        }

        $prepared = Home_Fields::prepare( $post_id, $meta );
        if ( is_wp_error( $prepared ) ) {
            return $prepared;
        }

        $current = Home_Fields::values( $post_id, array_keys( $prepared ) );
        $after   = [];
        foreach ( $prepared as $key => $value ) {
            if ( ! self::same( $current[ $key ], $value ) ) {
                $after[ $key ] = $value;
            }
        }

        if ( ! $after ) {
            return new WP_Error(
                'ketunkoti_ai_no_changes',
                __( 'Every field already has the proposed value. Nothing to change.', 'ketunkoti-ai' )
            );
        }

        $proposal = [
            'id'      => wp_generate_uuid4(),
            'post_id' => $post_id,
            'user_id' => $user_id,
            'created' => time(),
            'before'  => array_intersect_key( $current, $after ),
            'after'   => $after,
        ];
        set_transient( self::TRANSIENT_PREFIX . $proposal['id'], $proposal, self::TTL );

        $fields  = Home_Fields::keys( (string) get_post_type( $post_id ) );
        $changes = [];
        foreach ( $after as $key => $value ) {
            $changes[] = [
                'key'    => $key,
                'label'  => $fields[ $key ]['label'],
                'before' => $proposal['before'][ $key ],
                'after'  => $value,
            ];
        }

        return [
            'proposal_id' => $proposal['id'],
            'post_id'     => $post_id,
            'title'       => get_the_title( $post_id ),
            'expires'     => gmdate( 'c', $proposal['created'] + self::TTL ),
            'changes'     => $changes,
            // Keys of the whole call: a related field sent with its current value was kept on purpose.
            'notes'       => Home_Fields::related_notes( $post_id, array_keys( $prepared ) ),
        ];
    }

    /**
     * Applies a stored proposal after the user has approved it.
     *
     * @param string $proposal_id Proposal ID from propose().
     * @param int    $user_id     User who applies.
     * @return array|WP_Error Post ID and the applied values, or an error.
     */
    public static function apply( string $proposal_id, int $user_id ) {
        $proposal = self::load( $proposal_id, $user_id );
        if ( is_wp_error( $proposal ) ) {
            return $proposal;
        }

        if ( ! Settings::mcp_apply_allowed( $user_id ) ) {
            return self::apply_disabled();
        }

        $post_id = (int) $proposal['post_id'];
        if ( ! user_can( $user_id, 'edit_post', $post_id ) ) {
            return new WP_Error(
                'ketunkoti_ai_forbidden',
                __( 'You may no longer edit this home.', 'ketunkoti-ai' )
            );
        }

        $changed = [];
        foreach ( Home_Fields::values( $post_id, array_keys( $proposal['before'] ) ) as $key => $value ) {
            if ( $value !== $proposal['before'][ $key ] ) {
                $changed[] = $key;
            }
        }
        if ( $changed ) {
            return new WP_Error(
                'ketunkoti_ai_stale',
                sprintf(
                    /* translators: %s: comma separated list of meta keys. */
                    __( 'These fields changed after the proposal: %s. Read the home again and propose anew.', 'ketunkoti-ai' ),
                    implode( ', ', $changed )
                )
            );
        }

        Home_Fields::write( $post_id, $proposal['after'] );
        add_post_meta(
            $post_id,
            self::LOG_META,
            wp_slash(
                [
                    'proposal_id' => $proposal['id'],
                    'user_id'     => $user_id,
                    'applied'     => time(),
                    'before'      => $proposal['before'],
                    'after'       => $proposal['after'],
                ]
            )
        );
        delete_transient( self::TRANSIENT_PREFIX . $proposal['id'] );

        return [
            'post_id' => $post_id,
            'applied' => $proposal['after'],
        ];
    }

    /**
     * Drops a stored proposal.
     *
     * @param string $proposal_id Proposal ID from propose().
     * @param int    $user_id     User who discards.
     * @return array|WP_Error The discarded ID, or an error.
     */
    public static function discard( string $proposal_id, int $user_id ) {
        $proposal = self::load( $proposal_id, $user_id );
        if ( is_wp_error( $proposal ) ) {
            return $proposal;
        }

        delete_transient( self::TRANSIENT_PREFIX . $proposal['id'] );

        return [ 'discarded' => $proposal['id'] ];
    }

    /**
     * Loads a pending proposal of the user.
     *
     * @param string $proposal_id Proposal ID.
     * @param int    $user_id     User who acts on it.
     * @return array|WP_Error The proposal, or an error when it is missing, expired or someone else's.
     */
    private static function load( string $proposal_id, int $user_id ) {
        $proposal = wp_is_uuid( $proposal_id, 4 ) ? get_transient( self::TRANSIENT_PREFIX . $proposal_id ) : false;

        if ( ! is_array( $proposal ) || time() - (int) $proposal['created'] > self::TTL ) {
            return new WP_Error(
                'ketunkoti_ai_proposal_not_found',
                __( 'The proposal does not exist, has expired, or was already applied or discarded. Propose the change again.', 'ketunkoti-ai' )
            );
        }

        if ( (int) $proposal['user_id'] !== $user_id ) {
            return new WP_Error(
                'ketunkoti_ai_not_your_proposal',
                __( 'Only the user who made the proposal may apply or discard it.', 'ketunkoti-ai' )
            );
        }

        return $proposal;
    }

    /**
     * Whether a stored value already equals a prepared value.
     *
     * @param mixed $current  Stored value, null when not set.
     * @param mixed $prepared Prepared value, null to delete.
     * @return bool
     */
    private static function same( $current, $prepared ): bool {
        if ( null === $current || null === $prepared ) {
            return $current === $prepared;
        }

        return (string) $current === (string) $prepared;
    }

    /**
     * Builds the error for a user whose profile switch is off.
     *
     * @return WP_Error
     */
    private static function apply_disabled(): WP_Error {
        return new WP_Error(
            'ketunkoti_ai_apply_disabled',
            __( 'Changes to existing homes need the user to turn on "Allow applying changes from Claude on my computer" in their WordPress profile. Ask the user to turn it on and try again.', 'ketunkoti-ai' )
        );
    }
}
