<?php
/**
 * Glossary loader/saver.
 *
 * @package AdminMenuKupu
 */

defined( 'ABSPATH' ) || exit;

class AMK_Glossary {

    /**
     * Path to glossary JSON.
     *
     * @var string
     */
    protected $path;

    /**
     * Decoded glossary array.
     *
     * @var array
     */
    protected $data = array(
        'menus'   => array(),
        'submenus'=> array(),
        'headers' => array(),
    );

    /**
     * Constructor.
     *
     * @param string $path Absolute path to glossary file.
     */
    public function __construct( $path ) {
        $this->path = $path;
        $this->load();
    }

    /**
     * Load from disk.
     */
    public function load() {
        $default = array(
            'menus'    => array(),
            'submenus' => array(),
            'headers'  => array(),
        );
        if ( is_readable( $this->path ) ) {
            $raw = file_get_contents( $this->path );
            $json = json_decode( $raw, true );
            if ( is_array( $json ) ) {
                $this->data = wp_parse_args( $json, $default );
                return;
            }
        }
        $this->data = $default;
    }

    /**
     * Save to disk.
     *
     * @return bool
     */
    public function save() {
        $json = wp_json_encode( $this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        return (bool) file_put_contents( $this->path, $json );
    }

    /**
     * Export JSON string.
     *
     * @return string
     */
    public function export_json() {
        return wp_json_encode( $this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    }

    /**
     * Import JSON string (validated).
     *
     * @param string $json JSON.
     * @return bool
     */
    public function import_json( $json ) {
        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            return false;
        }
        $decoded = wp_parse_args( $decoded, array(
            'menus'    => array(),
            'submenus' => array(),
            'headers'  => array(),
        ) );
        foreach ( array( 'menus', 'submenus', 'headers' ) as $section ) {
            if ( ! is_array( $decoded[ $section ] ) ) {
                $decoded[ $section ] = array();
            }
            // Sanitize.
            $clean = array();
            foreach ( $decoded[ $section ] as $k => $v ) {
                $k = sanitize_text_field( $k );
                $v = sanitize_text_field( $v );
                if ( $k !== '' && $v !== '' ) {
                    $clean[ $k ] = $v;
                }
            }
            $decoded[ $section ] = $clean;
        }
        $this->data = $decoded;
        return $this->save();
    }

    /** @return array */
    public function menus() { return isset( $this->data['menus'] ) ? $this->data['menus'] : array(); }
    /** @return array */
    public function submenus() { return isset( $this->data['submenus'] ) ? $this->data['submenus'] : array(); }
    /** @return array */
    public function headers() { return isset( $this->data['headers'] ) ? $this->data['headers'] : array(); }

    /**
     * Get translation for a key by section.
     *
     * @param string $section menus|submenus|headers
     * @param string $key     Original label.
     * @return string|null
     */
    public function get( $section, $key ) {
        $arr = isset( $this->data[ $section ] ) ? $this->data[ $section ] : array();
        return isset( $arr[ $key ] ) ? $arr[ $key ] : null;
    }

    /**
     * Replace or add a term.
     *
     * @param string $section Section.
     * @param string $key     Key.
     * @param string $value   Value.
     */
    public function set( $section, $key, $value ) {
        if ( ! isset( $this->data[ $section ] ) ) {
            $this->data[ $section ] = array();
        }
        $this->data[ $section ][ $key ] = $value;
        $this->save();
    }

    /**
     * Delete a term.
     *
     * @param string $section Section.
     * @param string $key Key.
     */
    public function delete( $section, $key ) {
        if ( isset( $this->data[ $section ][ $key ] ) ) {
            unset( $this->data[ $section ][ $key ] );
            $this->save();
        }
    }
}
