<?php
/**
 * Plugin Name:       Eyes Follow Cursor
 * Description:       An interactive block that displays a pair of eyes that follow your cursor around the screen and occasionally wink at you.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Author:            WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eyes-follow-cursor
 *
 * @package EyesFollowCursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function eyes_follow_cursor_block_init() {
	register_block_type( __DIR__ . '/build/' );
}
add_action( 'init', 'eyes_follow_cursor_block_init' );
