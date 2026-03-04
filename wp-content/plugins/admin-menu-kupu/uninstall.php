<?php
/**
 * Uninstall cleanup.
 *
 * @package AdminMenuKupu
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only remove data if admin opted in.
$delete = get_option( 'amk_delete_data_on_uninstall', false );
if ( ! $delete ) {
    return;
}

// Site options.
delete_option( 'amk_global_enabled' );
delete_option( 'amk_tooltips_enabled' );
delete_option( 'amk_roles_limit' );
delete_option( 'amk_title_overlay_enabled' );
delete_option( 'amk_delete_data_on_uninstall' );

// User meta cleanup (best-effort; may be heavy on large sites).
if ( function_exists( 'get_users' ) ) {
    $users = get_users( array( 'fields' => 'ID' ) );
    foreach ( $users as $uid ) {
        delete_user_meta( $uid, '_amk_enabled' );
        delete_user_meta( $uid, '_amk_tooltips_enabled' );
    }
}
