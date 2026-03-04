<?php
/**
 * Plugin Name: Christmas Lights
 * Description: Adds a lightweight string of Christmas lights to the top of your site using a standalone Web Component.
 * Version: 0.1.2
 * Author: Josh
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CL_VERSION', '0.1.2' );

function cl_plugin_url() {
	return plugin_dir_url( __FILE__ );
}

function cl_enqueue_assets() {
	// Prefer CDN if defined via filter; fallback to bundled asset.
	$src = apply_filters( 'christmas_lights_component_src', cl_plugin_url() . 'assets/christmas-lights.js' );
	wp_enqueue_script( 'christmas-lights', $src, array(), CL_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'cl_enqueue_assets' );

function cl_render_component() {
	// Output the component early in body. Supports attributes via filters.
	$attrs = apply_filters( 'christmas_lights_component_attrs', array() );
	$allowed = array( 'count','colors','twinkle','speed','size','offset' );
	$attr_str = '';
	foreach ( $attrs as $k => $v ) {
		if ( in_array( $k, $allowed, true ) ) {
			$attr_str .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
	}
	echo '<christmas-lights' . $attr_str . '></christmas-lights>';
}

// Prefer wp_body_open (WP 5.2+) for correct placement.
add_action( 'wp_body_open', 'cl_render_component' );
// Fallback if theme lacks wp_body_open.
add_action( 'wp_footer', function(){
	if ( ! did_action( 'wp_body_open' ) ) {
		cl_render_component();
	}
}, 5 );
