<?php
if (!defined('ABSPATH')) exit;

function taskstreak_create_tables() {
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  $charset = $wpdb->get_charset_collate();

  $tasks = "{$wpdb->prefix}taskstreak_tasks";
  $done = "{$wpdb->prefix}taskstreak_completions";
  $orders = "{$wpdb->prefix}taskstreak_orders";
  $settings = "{$wpdb->prefix}taskstreak_user_settings";
  $history = "{$wpdb->prefix}taskstreak_history";

  dbDelta("CREATE TABLE $tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    reps_target INT NOT NULL DEFAULT 1,
    days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
    repeats_limit INT NULL,
    repeats_done INT NOT NULL DEFAULT 0,
    streak_days INT NOT NULL DEFAULT 0,
    tier VARCHAR(20) NOT NULL DEFAULT 'new',
    grace_used_week_start DATE NULL,
    grace_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id)
  ) $charset;");

  dbDelta("CREATE TABLE $done (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  reps_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq (user_id, task_id, day),
  KEY user_day (user_id, day)
) $charset;");


  dbDelta("CREATE TABLE $orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    order_json LONGTEXT NOT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq (user_id, day)
  ) $charset;");

  dbDelta("CREATE TABLE $settings (
    user_id BIGINT UNSIGNED NOT NULL,
    settings LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id)
  ) $charset;");

  dbDelta("CREATE TABLE $history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(40) NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    streak_days INT NULL,
    earned_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    note TEXT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY kind (kind)
  ) $charset;");
}

/**
 * Create the app page once, store its id.
 * If a page named "My Tasks" already exists, attach to that page.
 */
function taskstreak_create_or_attach_app_page() {
  if (get_option('taskstreak_page_id')) return;

  $existing = get_page_by_title('My Tasks');
  if ($existing && !empty($existing->ID)) {
    update_option('taskstreak_page_id', (int)$existing->ID);
    return;
  }

  $page_id = wp_insert_post([
    'post_title' => 'My Tasks',
    'post_status' => 'publish',
    'post_type' => 'page',
    'post_content' => '',
  ]);

  if ($page_id && !is_wp_error($page_id)) {
    update_option('taskstreak_page_id', (int)$page_id);
  }
}
