<?php
/**
 * Helper functions
 *
 * @package AdminMenuKupu
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'amk_bool' ) ) {
    /**
     * Normalize boolean-ish values from DB/forms.
     *
     * @param mixed $val Value.
     * @return bool
     */
    function amk_bool( $val ) {
        if ( is_bool( $val ) ) {
            return $val;
        }
        $truey = array( '1', 1, 'true', 'on', 'yes' );
        return in_array( $val, $truey, true );
    }
}

if ( ! function_exists( 'amk_current_user_has_role' ) ) {
    /**
     * Whether the current user has one of the allowed roles.
     *
     * @param array $roles Role slugs.
     * @return bool
     */
    function amk_current_user_has_role( $roles ) {
        if ( empty( $roles ) || ! is_array( $roles ) ) {
            return true; // No restriction.
        }
        $user = wp_get_current_user();
        if ( ! $user || empty( $user->roles ) ) {
            return false;
        }
        foreach ( $user->roles as $role ) {
            if ( in_array( $role, $roles, true ) ) {
                return true;
            }
        }
        return false;
    }
}
