<?php
/**
 * Admin Bar quick toggle.
 *
 * @package AdminMenuKupu
 */

defined( 'ABSPATH' ) || exit;

class AMK_AdminBar {

    /** @var string */
    protected $nonce_action = 'amk_toggle_user';

    public function hooks() {
        add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 200 );
        add_action( 'wp_ajax_amk_toggle_user', array( $this, 'ajax_toggle' ) );
    }

    /**
     * Add a "Kupu: On/Off" toggle
     */
    public function admin_bar( WP_Admin_Bar $bar ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return;
        }

        $enabled = AMK_Plugin::instance()->is_enabled_for_user();
        $title   = $enabled ? 'Kupu: On' : 'Kupu: Off';

        $bar->add_node( array(
            'id'    => 'amk-toggle',
            'title' => '<span id="amk-adminbar-label">' . esc_html( $title ) . '</span>',
            'href'  => '#',
            'meta'  => array(
                'title' => esc_attr__( 'Toggle Admin Menu Kupu', 'admin-menu-kupu' ),
                'html'  => '<a id="amk-adminbar-toggle" class="ab-item" href="#">' . esc_html( $title ) . '</a>',
            ),
        ) );
    }

    /**
     * AJAX: toggle user preference.
     */
    public function ajax_toggle() {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
        }

        check_ajax_referer( $this->nonce_action, 'nonce' );

        $enable = isset( $_POST['enable'] ) ? ( '1' === $_POST['enable'] ) : null;
        if ( null === $enable ) {
            wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
        }

        $user_id = get_current_user_id();
        update_user_meta( $user_id, '_amk_enabled', $enable ? 1 : 0 );

        wp_send_json_success( array( 'enabled' => (bool) $enable ) );
    }
}
