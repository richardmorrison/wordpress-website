<?php
if (!defined('ABSPATH')) exit;

/**
 * Default user settings
 */
function taskstreak_default_user_settings() {
  return [
    'theme' => 'system',
    'week_start' => 1,
    'progress_style' => 'bar',
    'progress_mode' => 'rotate',
    'sync_preference' => 'ask',
    'calm_mode' => 'subtle', // option b
  ];
}

function taskstreak_get_user_settings($user_id) {
  global $wpdb;
  $table = "{$wpdb->prefix}taskstreak_user_settings";
  $row = $wpdb->get_row($wpdb->prepare("SELECT settings FROM $table WHERE user_id = %d", $user_id));
  $defaults = taskstreak_default_user_settings();

  if (!$row) return $defaults;

  $decoded = json_decode($row->settings, true);
  if (!is_array($decoded)) return $defaults;

  return array_merge($defaults, $decoded);
}

function taskstreak_save_user_settings($user_id, $settings) {
  global $wpdb;
  $table = "{$wpdb->prefix}taskstreak_user_settings";
  $now = current_time('mysql');

  $defaults = taskstreak_default_user_settings();
  $clean = array_merge($defaults, is_array($settings) ? $settings : []);

  $wpdb->replace($table, [
    'user_id' => (int)$user_id,
    'settings' => wp_json_encode($clean),
    'updated_at' => $now,
  ]);
  return $clean;
}
