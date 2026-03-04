<?php
if (!defined('ABSPATH')) exit;

$GLOBALS['taskstreak_shortcode_present'] = false;

function taskstreak_mark_shortcode_present() {
  $GLOBALS['taskstreak_shortcode_present'] = true;
}

function taskstreak_should_load_assets() {
  $page_id = (int) get_option('taskstreak_page_id');
  if ($page_id && is_page($page_id)) return true;

  global $post;
  if (!empty($post) && is_singular() && has_shortcode($post->post_content, 'taskstreak')) return true;

  if (!empty($GLOBALS['taskstreak_shortcode_present'])) return true;

  return false;
}

add_action('wp_enqueue_scripts', function () {
  if (!taskstreak_should_load_assets()) return;

  wp_enqueue_style('taskstreak-app', TASKSTREAK_URL . 'assets/app.css', [], TASKSTREAK_VERSION);
  wp_enqueue_script('taskstreak-app', TASKSTREAK_URL . 'assets/app.js', [], TASKSTREAK_VERSION, true);
  wp_enqueue_script('taskstreak-pwa', TASKSTREAK_URL . 'assets/pwa.js', [], TASKSTREAK_VERSION, true);

  $page_id = (int) get_option('taskstreak_page_id');
  $page_url = $page_id ? get_permalink($page_id) : home_url('/');

  wp_localize_script('taskstreak-app', 'TASKSTREAK', [
    'rest'   => esc_url_raw(rest_url('taskstreak/v1')),
    'nonce'  => wp_create_nonce('wp_rest'),
    'assets' => TASKSTREAK_URL . 'assets/',
    'pageUrl'=> $page_url,
    'appPageId' => $page_id,
    'siteName' => get_bloginfo('name'),
  ]);
});

/**
 * If the app is mounted inside a normal theme page (shortcode fallback),
 * lightly reduce theme chrome without breaking the theme.
 */
add_action('wp_head', function () {
  if (!taskstreak_should_load_assets()) return;
  $page_id = (int) get_option('taskstreak_page_id');
  if ($page_id && is_page($page_id)) return;

  // If the current page is using the shortcode, we render through the app
  // shell template which already removes theme chrome. Do not add the
  // "fade the theme" CSS here, or it may interfere with the app header.
  if (is_page()) {
    global $post;
    if (!empty($post) && has_shortcode($post->post_content, 'taskstreak')) {
      return;
    }
  }

  echo '<style>
  body .site-header, body header, body .wp-block-navigation, body footer, body .site-footer { opacity: 0.15; }
  body .site-header *, body header *, body footer *, body .site-footer * { pointer-events: none; }
  </style>';
});
