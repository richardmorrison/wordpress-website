<?php
/**
 * Plugin Name: TaskStreak
 * Description: A calm, offline-first recurring task tracker with streak motivation.
 * Version: 1.2.3
 * Author: Richard Morrison
 */
if (!defined('ABSPATH')) exit;

define('TASKSTREAK_DIR', plugin_dir_path(__FILE__));
define('TASKSTREAK_URL', plugin_dir_url(__FILE__));
define('TASKSTREAK_VERSION', '1.2.3');

require_once TASKSTREAK_DIR . 'includes/db.php';
require_once TASKSTREAK_DIR . 'includes/settings.php';
require_once TASKSTREAK_DIR . 'includes/rest.php';
require_once TASKSTREAK_DIR . 'includes/enqueue.php';
require_once TASKSTREAK_DIR . 'includes/admin.php';

register_activation_hook(__FILE__, function () {
  taskstreak_create_tables();
  taskstreak_create_or_attach_app_page();
});

/**
 * App shell template override for the dedicated app page id.
 * Learning note:
 * A full HTML shell avoids theme headers and footers so it feels like a separate app.
 */
add_filter('template_include', function ($template) {
  // Dedicated app page (created on activation) always uses the app shell.
  $page_id = (int) get_option('taskstreak_page_id');
  if ($page_id && is_page($page_id)) {
    return TASKSTREAK_DIR . 'templates/taskstreak-app.php';
  }

  // If you embedded the shortcode on a page, treat it as an app page too.
  // Learning note:
  // This avoids theme header and footer, plus it prevents blocks like sharing
  // buttons from appearing below the app.
  if (is_page()) {
    global $post;
    if (!empty($post) && has_shortcode($post->post_content, 'taskstreak')) {
      return TASKSTREAK_DIR . 'templates/taskstreak-app.php';
    }
  }

  return $template;
});

/**
 * Hide the WordPress admin bar on the app page so it feels like a real app.
 * Note: when logged out, the bar is not shown anyway.
 */
add_filter('show_admin_bar', function ($show) {
  $page_id = (int) get_option('taskstreak_page_id');
  if ($page_id && is_page($page_id)) return false;

  if (is_page()) {
    global $post;
    if (!empty($post) && has_shortcode($post->post_content, 'taskstreak')) {
      return false;
    }
  }

  return $show;
}, 20);

/**
 * Shortcode fallback for older pages.
 * If your menu points to an older page, this ensures the app still renders.
 */
add_shortcode('taskstreak', function () {
  taskstreak_mark_shortcode_present();
  return '<div id="taskstreak-root"></div>';
});
