<?php
/**
 * Plugin Name: Admin Menu Kupu (Māori Labels)
 * Description: Replaces wp-admin menu labels with Māori (Te Reo) equivalents, with a tooltip/hover revealing the original label. Per-user toggles and a global default are provided. Optional page title overlay module. Audio scaffolding is omitted.
 * Version: 0.6.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Richard Morrison
 * License: GPL-2.0+
 * Text Domain: admin-menu-kupu
 * Domain Path: /languages
 * 
 * @package AdminMenuKupu
 */

defined( 'ABSPATH' ) || exit;

// Autoload includes (no composer runtime needed).
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-amk-glossary.php';
require_once __DIR__ . '/includes/class-amk-plugin.php';
require_once __DIR__ . '/includes/class-amk-settings.php';
require_once __DIR__ . '/includes/class-amk-adminbar.php';
require_once __DIR__ . '/includes/class-amk-title-overlay.php';

/**
 * Activation: ensure defaults exist.
 */
function amk_activate() {
    if ( get_option( 'amk_global_enabled', null ) === null ) {
        add_option( 'amk_global_enabled', true );
    }
    if ( get_option( 'amk_tooltips_enabled', null ) === null ) {
        add_option( 'amk_tooltips_enabled', true );
    }
    if ( get_option( 'amk_roles_limit', null ) === null ) {
        add_option( 'amk_roles_limit', array() );
    }
    if ( get_option( 'amk_title_overlay_enabled', null ) === null ) {
        add_option( 'amk_title_overlay_enabled', false );
    }
    if ( get_option( 'amk_delete_data_on_uninstall', null ) === null ) {
        add_option( 'amk_delete_data_on_uninstall', false );
    }
}
register_activation_hook( __FILE__, 'amk_activate' );

/**
 * Bootstrap
 */
add_action( 'plugins_loaded', function(){
    $glossary_path = __DIR__ . '/glossary/admin-menu-kupu-glossary.json';
    $glossary = new AMK_Glossary( $glossary_path );

    $plugin = new AMK_Plugin( __FILE__, $glossary );
    $plugin->hooks();

    $settings = new AMK_Settings( $glossary );
    $settings->hooks();

    $adminbar = new AMK_AdminBar();
    $adminbar->hooks();

    $titles = new AMK_Title_Overlay( $glossary );
    $titles->hooks();

    // --- Audio future stub (disabled, not enqueued) ---
    // add_action( 'admin_init', function(){ /* reserved for future audio glossary playback */ } );
} );
