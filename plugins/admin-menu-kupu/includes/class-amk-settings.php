<?php
/**
 * Settings & Glossary editor.
 *
 * @package AdminMenuKupu
 */

defined( 'ABSPATH' ) || exit;

class AMK_Settings {

    const PAGE_SLUG = 'admin-menu-kupu';

    /** @var AMK_Glossary */
    protected $glossary;

    public function __construct( AMK_Glossary $glossary ) {
        $this->glossary = $glossary;
    }

    public function hooks() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( AMK_Plugin::instance()->file ), array( $this, 'plugin_row_link' ) );

        add_action( 'admin_post_amk_export_glossary', array( $this, 'handle_export' ) );
        add_action( 'admin_post_amk_import_glossary', array( $this, 'handle_import' ) );
    }

    public function register_settings() {
        register_setting( 'amk_general', 'amk_global_enabled', array( 'type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
        register_setting( 'amk_general', 'amk_tooltips_enabled', array( 'type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
        register_setting( 'amk_general', 'amk_roles_limit', array( 'type' => 'array', 'default' => array(), 'sanitize_callback' => array( $this, 'sanitize_roles' ) ) );
        register_setting( 'amk_advanced', 'amk_title_overlay_enabled', array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
        register_setting( 'amk_advanced', 'amk_delete_data_on_uninstall', array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
    }

    public function add_menu() {
        add_options_page(
            __( 'Admin Menu Kupu', 'admin-menu-kupu' ),
            __( 'Admin Menu Kupu', 'admin-menu-kupu' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function plugin_row_link( $links ) {
        $url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'admin-menu-kupu' ) . '</a>';
        return $links;
    }

    public function sanitize_roles( $value ) {
        if ( ! is_array( $value ) ) { return array(); }
        $editable = array_keys( get_editable_roles() );
        $out = array();
        foreach ( $value as $slug ) {
            $slug = sanitize_key( $slug );
            if ( in_array( $slug, $editable, true ) ) {
                $out[] = $slug;
            }
        }
        return array_values( array_unique( $out ) );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $tabs = array(
            'general'  => __( 'General', 'admin-menu-kupu' ),
            'glossary' => __( 'Glossary', 'admin-menu-kupu' ),
            'advanced' => __( 'Advanced', 'admin-menu-kupu' ),
        );
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Admin Menu Kupu', 'admin-menu-kupu' ) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $slug => $label ) {
            $class = ( $slug === $active_tab ) ? ' nav-tab nav-tab-active' : ' nav-tab';
            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=' . $slug ) ),
                esc_attr( $class ),
                esc_html( $label )
            );
        }
        echo '</h2>';

        if ( 'general' === $active_tab ) {
            $this->render_general();
        } elseif ( 'glossary' === $active_tab ) {
            $this->render_glossary();
        } else {
            $this->render_advanced();
        }
        echo '</div>';
    }

    protected function render_general() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'amk_general' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Global default enabled', 'admin-menu-kupu' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="amk_global_enabled" value="1" <?php checked( amk_bool( get_option( 'amk_global_enabled', true ) ) ); ?> />
                            <?php esc_html_e( 'Enable overlay for all users by default (users can turn off in their profile).', 'admin-menu-kupu' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tooltips enabled', 'admin-menu-kupu' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="amk_tooltips_enabled" value="1" <?php checked( amk_bool( get_option( 'amk_tooltips_enabled', true ) ) ); ?> />
                            <?php esc_html_e( 'Show original label on hover/focus via tooltip.', 'admin-menu-kupu' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Role limit', 'admin-menu-kupu' ); ?></th>
                    <td>
                        <?php
                        $selected = (array) get_option( 'amk_roles_limit', array() );
                        $roles    = get_editable_roles();
                        foreach ( $roles as $slug => $obj ) {
                            printf(
                                '<label style="display:inline-block;margin-right:12px;"><input type="checkbox" name="amk_roles_limit[]" value="%1$s" %2$s /> %3$s</label>',
                                esc_attr( $slug ),
                                checked( in_array( $slug, $selected, true ), true, false ),
                                esc_html( translate_user_role( $obj['name'] ) )
                            );
                        }
                        ?>
                        <p class="description"><?php esc_html_e( 'If none selected, applies to all roles.', 'admin-menu-kupu' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    protected function render_glossary() {
        $data = array(
            'menus'    => $this->glossary->menus(),
            'submenus' => $this->glossary->submenus(),
            'headers'  => $this->glossary->headers(),
        );
        ?>
        <h2><?php esc_html_e( 'Glossary Editor', 'admin-menu-kupu' ); ?></h2>
        <p><?php esc_html_e( 'Add or edit keys. Keys are case-sensitive and must match visible labels.', 'admin-menu-kupu' ); ?></p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'amk_glossary_save', 'amk_nonce' ); ?>
            <input type="hidden" name="action" value="amk_import_glossary" />
            <textarea name="amk_glossary_json" rows="18" style="width:100%;font-family:monospace;" aria-label="<?php esc_attr_e( 'Glossary JSON', 'admin-menu-kupu' ); ?>"><?php
                echo esc_textarea( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
            ?></textarea>
            <?php submit_button( __( 'Save Glossary', 'admin-menu-kupu' ) ); ?>
        </form>

        <hr/>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'amk_export_glossary', 'amk_nonce' ); ?>
            <input type="hidden" name="action" value="amk_export_glossary" />
            <?php submit_button( __( 'Download Export (JSON)', 'admin-menu-kupu' ), 'secondary' ); ?>
        </form>

        <h3><?php esc_html_e( 'Import from file', 'admin-menu-kupu' ); ?></h3>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'amk_import_glossary', 'amk_nonce' ); ?>
            <input type="hidden" name="action" value="amk_import_glossary" />
            <input type="file" name="amk_glossary_file" accept="application/json,.json" />
            <?php submit_button( __( 'Import JSON', 'admin-menu-kupu' ), 'secondary' ); ?>
        </form>
        <?php
    }

    protected function render_advanced() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'amk_advanced' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Title Overlay (H1) enabled', 'admin-menu-kupu' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="amk_title_overlay_enabled" value="1" <?php checked( amk_bool( get_option( 'amk_title_overlay_enabled', false ) ) ); ?> />
                            <?php esc_html_e( 'Replace common admin page H1 titles after load (optional).', 'admin-menu-kupu' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Delete data on uninstall', 'admin-menu-kupu' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="amk_delete_data_on_uninstall" value="1" <?php checked( amk_bool( get_option( 'amk_delete_data_on_uninstall', false ) ) ); ?> />
                            <?php esc_html_e( 'Remove plugin options and user meta when deleting the plugin.', 'admin-menu-kupu' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p class="description"><?php esc_html_e( 'Audio features are not included in this build. A future extension stub exists but is disabled and not enqueued.', 'admin-menu-kupu' ); ?></p>
        <?php
    }

    public function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'forbidden' ); }
        check_admin_referer( 'amk_export_glossary', 'amk_nonce' );
        $json = $this->glossary->export_json();
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=admin-menu-kupu-glossary.json' );
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function handle_import() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'forbidden' ); }

        // Textarea import (JSON string) OR file upload.
        if ( isset( $_POST['amk_nonce'] ) && wp_verify_nonce( $_POST['amk_nonce'], 'amk_glossary_save' ) && isset( $_POST['amk_glossary_json'] ) ) {
            $json = wp_unslash( $_POST['amk_glossary_json'] );
            $ok   = $this->glossary->import_json( $json );
            wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'glossary', 'import' => $ok ? '1' : '0' ), admin_url( 'options-general.php' ) ) );
            exit;
        }

        if ( isset( $_POST['amk_nonce'] ) && wp_verify_nonce( $_POST['amk_nonce'], 'amk_import_glossary' ) && ! empty( $_FILES['amk_glossary_file']['tmp_name'] ) ) {
            $contents = file_get_contents( $_FILES['amk_glossary_file']['tmp_name'] );
            $ok = $this->glossary->import_json( $contents );
            wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'glossary', 'import' => $ok ? '1' : '0' ), admin_url( 'options-general.php' ) ) );
            exit;
        }

        wp_die( 'bad_request' );
    }
}
