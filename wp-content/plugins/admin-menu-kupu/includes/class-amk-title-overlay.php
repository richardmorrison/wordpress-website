<?php
/**
 * Optional title overlay (H1) replacement.
 *
 * @package AdminMenuKupu
 */

defined( 'ABSPATH' ) || exit;

class AMK_Title_Overlay {

    /** @var AMK_Glossary */
    protected $glossary;

    public function __construct( AMK_Glossary $glossary ) {
        $this->glossary = $glossary;
    }

    public function hooks() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function enqueue( $hook ) {
        if ( ! amk_bool( get_option( 'amk_title_overlay_enabled', false ) ) ) {
            return;
        }
        $user_enabled = AMK_Plugin::instance()->is_enabled_for_user();
        if ( ! $user_enabled ) {
            return;
        }
        if ( ! amk_current_user_has_role( (array) get_option( 'amk_roles_limit', array() ) ) ) {
            return;
        }

        wp_register_script(
            'amk-title-overlay',
            plugins_url( 'assets/js/admin-title-overlay.js', AMK_Plugin::instance()->file ),
            array(),
            AMK_Plugin::instance()->version,
            true
        );
        wp_localize_script( 'amk-title-overlay', 'AMK_TITLES', array(
            'headers' => $this->glossary->headers(),
        ) );
        wp_enqueue_script( 'amk-title-overlay' );
    }
}
