<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
  add_options_page(
    'TaskStreak',
    'TaskStreak',
    'manage_options',
    'taskstreak',
    'taskstreak_admin_page'
  );
});

function taskstreak_admin_page() {
  if (!current_user_can('manage_options')) return;

  $page_id = (int) get_option('taskstreak_page_id');
  $url = $page_id ? get_permalink($page_id) : home_url('/');
  echo '<div class="wrap">';
  echo '<h1>TaskStreak</h1>';
  echo '<p>This plugin creates a dedicated app page. Use this link to confirm your menu points to the right page.</p>';
  echo '<p><a class="button button-primary" href="' . esc_url($url) . '" target="_blank">Open TaskStreak app page</a></p>';
  echo '<p>Page ID: ' . esc_html($page_id) . '</p>';
  echo '</div>';
}
