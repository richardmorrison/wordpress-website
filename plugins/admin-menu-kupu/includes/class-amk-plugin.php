<?php
/**
 * Core overlay behavior.
 *
 * @package AdminMenuKupu
 */

defined( 'ABSPATH' ) || exit;

class AMK_Plugin {

    /** @var AMK_Plugin */
    protected static $instance;

    /** @var string Plugin file */
    public $file;

    /** @var string Plugin version */
    public $version = '0.6.0';

    /** @var AMK_Glossary */
    public $glossary;

    /** Singleton */
    public static function instance() {
        return self::$instance;
    }

    public function __construct( $file, AMK_Glossary $glossary ) {
        self::$instance = $this;
        $this->file     = $file;
        $this->glossary = $glossary;
    }

    public function hooks() {
        add_action( 'admin_init', array( $this, 'maybe_set_user_defaults' ) );
        add_action( 'admin_menu', array( $this, 'overlay_admin_menu' ), 9999 );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Default user prefs follow global option.
     */
    public function maybe_set_user_defaults() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) { return; }

        if ( null === get_user_meta( $user_id, '_amk_enabled', true ) || '' === get_user_meta( $user_id, '_amk_enabled', true ) ) {
            update_user_meta( $user_id, '_amk_enabled', amk_bool( get_option( 'amk_global_enabled', true ) ) ? 1 : 0 );
        }
        if ( null === get_user_meta( $user_id, '_amk_tooltips_enabled', true ) || '' === get_user_meta( $user_id, '_amk_tooltips_enabled', true ) ) {
            update_user_meta( $user_id, '_amk_tooltips_enabled', amk_bool( get_option( 'amk_tooltips_enabled', true ) ) ? 1 : 0 );
        }
    }

    /**
     * Whether enabled for current user (respect role limits).
     *
     * @return bool
     */
    public function is_enabled_for_user() {
        if ( ! is_user_logged_in() ) { return false; }
        if ( ! amk_current_user_has_role( (array) get_option( 'amk_roles_limit', array() ) ) ) {
            return false;
        }
        $user_enabled = get_user_meta( get_current_user_id(), '_amk_enabled', true );
        return amk_bool( '' === $user_enabled ? get_option( 'amk_global_enabled', true ) : $user_enabled );
    }

    /**
     * Overlay labels in $menu/$submenu (server-side baseline).
     */
    public function overlay_admin_menu() {
        if ( ! $this->is_enabled_for_user() ) {
            return;
        }

        global $menu, $submenu;

        // Top level menus.
        foreach ( (array) $menu as $idx => $item ) {
            if ( ! isset( $item[0] ) ) { continue; }
            $original = wp_strip_all_tags( $item[0] );
            $translated = $this->glossary->get( 'menus', $original );
            if ( $translated ) {
                $menu[ $idx ][0] = $this->wrap_label( $translated, $original );
            }
        }

        // Submenus.
        foreach ( (array) $submenu as $parent => $items ) {
            foreach ( (array) $items as $idx => $item ) {
                if ( ! isset( $item[0] ) ) { continue; }
                $original = wp_strip_all_tags( $item[0] );
                $translated = $this->glossary->get( 'submenus', $original );
                if ( $translated ) {
                    $submenu[ $parent ][ $idx ][0] = $this->wrap_label( $translated, $original );
                }
            }
        }
    }

    /**
     * Wrap label to carry original and translated values.
     *
     * @param string $translated Translated.
     * @param string $original   Original.
     * @return string
     */
    protected function wrap_label( $translated, $original ) {
        $span = sprintf(
            '<span class="wp-menu-name" title="%1$s" data-amk-original="%1$s" data-amk-translated="%2$s">%2$s</span>',
            esc_attr( $original ),
            esc_html( $translated )
        );
        return $span;
    }

    /**
     * Enqueue CSS/JS for tooltips and admin bar toggle glue.
     */
    public function enqueue_assets( $hook ) {
        if ( ! is_admin() ) { return; }

        wp_register_style(
            'amk-style',
            plugins_url( 'assets/css/admin-menu-kupu.css', $this->file ),
            array(),
            $this->version
        );
        wp_register_script(
            'amk-script',
            plugins_url( 'assets/js/admin-menu-kupu.js', $this->file ),
            array(),
            $this->version,
            true
        );

        $enabled  = $this->is_enabled_for_user();
        $tooltips = amk_bool( get_user_meta( get_current_user_id(), '_amk_tooltips_enabled', true ) );
        if ( '' === $tooltips ) {
            $tooltips = amk_bool( get_option( 'amk_tooltips_enabled', true ) );
        }

        wp_localize_script( 'amk-script', 'AMK_VARS', array(
            'enabled'          => (bool) $enabled,
            'tooltips_enabled' => (bool) $tooltips,
            'nonce'            => wp_create_nonce( 'amk_toggle_user' ),
        ) );

        wp_enqueue_style( 'amk-style' );
        wp_enqueue_script( 'amk-script' );
    }
}
