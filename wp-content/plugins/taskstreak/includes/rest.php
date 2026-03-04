<?php
if (!defined('ABSPATH')) exit;

require_once TASKSTREAK_DIR . 'includes/settings.php';

add_action('rest_api_init', function () {
  $ns = 'taskstreak/v1';

  register_rest_route($ns, '/boot', [
    'methods' => 'GET',
    'callback' => 'taskstreak_rest_boot',
    'permission_callback' => 'is_user_logged_in',
  ]);

  register_rest_route($ns, '/tasks', [
    [
      'methods' => 'GET',
      'callback' => 'taskstreak_rest_list_tasks',
      'permission_callback' => 'is_user_logged_in',
    ],
    [
      'methods' => 'POST',
      'callback' => 'taskstreak_rest_create_task',
      'permission_callback' => 'is_user_logged_in',
    ],
  ]);

  register_rest_route($ns, '/tasks/(?P<id>\d+)', [
    [
      'methods' => 'POST',
      'callback' => 'taskstreak_rest_update_task',
      'permission_callback' => 'is_user_logged_in',
    ],
    [
      'methods' => 'DELETE',
      'callback' => 'taskstreak_rest_delete_task',
      'permission_callback' => 'is_user_logged_in',
    ],
  ]);

  register_rest_route($ns, '/complete', [
    'methods' => 'POST',
    'callback' => 'taskstreak_rest_complete',
    'permission_callback' => 'is_user_logged_in',
  ]);

  register_rest_route($ns, '/order', [
    [
      'methods' => 'GET',
      'callback' => 'taskstreak_rest_get_order',
      'permission_callback' => 'is_user_logged_in',
    ],
    [
      'methods' => 'POST',
      'callback' => 'taskstreak_rest_save_order',
      'permission_callback' => 'is_user_logged_in',
    ],
  ]);

  register_rest_route($ns, '/settings', [
    [
      'methods' => 'GET',
      'callback' => 'taskstreak_rest_get_settings',
      'permission_callback' => 'is_user_logged_in',
    ],
    [
      'methods' => 'POST',
      'callback' => 'taskstreak_rest_save_settings',
      'permission_callback' => 'is_user_logged_in',
    ],
  ]);

  register_rest_route($ns, '/history', [
    [
      'methods' => 'GET',
      'callback' => 'taskstreak_rest_get_history',
      'permission_callback' => 'is_user_logged_in',
    ],
    [
      'methods' => 'POST',
      'callback' => 'taskstreak_rest_add_reflection',
      'permission_callback' => 'is_user_logged_in',
    ],
  ]);
});

function taskstreak_rest_boot() {
  $user_id = get_current_user_id();
  $settings = taskstreak_get_user_settings($user_id);
  $page_id = (int) get_option('taskstreak_page_id');
  $page_url = $page_id ? get_permalink($page_id) : home_url('/');
  return [
    'today' => current_time('Y-m-d'),
    'settings' => $settings,
    'appPageUrl' => $page_url,
    'appPageId' => $page_id,
  ];
}

function taskstreak_days_mask_from_array($days) {
  // days: 0=Sun..6=Sat
  $mask = 0;
  if (!is_array($days)) return 127;
  foreach ($days as $d) {
    $di = (int)$d;
    if ($di < 0 || $di > 6) continue;
    $mask |= (1 << $di);
  }
  if ($mask === 0) $mask = 127;
  return $mask;
}

function taskstreak_is_due_today($task, $today) {
  $dow = (int) gmdate('w', strtotime($today . ' 12:00:00'));
  $mask = (int) $task->days_mask;
  return (bool)($mask & (1 << $dow));
}

function taskstreak_week_start_date($today, $week_start) {
  // week_start: 0=Sun..6=Sat
  $dow = (int) gmdate('w', strtotime($today . ' 12:00:00'));
  $diff = ($dow - (int)$week_start + 7) % 7;
  return gmdate('Y-m-d', strtotime($today . ' -' . $diff . ' days'));
}

function taskstreak_add_history($user_id, $kind, $task_id=null, $streak_days=null, $note=null) {
  global $wpdb;
  $table = "{$wpdb->prefix}taskstreak_history";
  $earned = current_time('mysql');
  $expires = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql') . ' +7 days'));
  $wpdb->insert($table, [
    'user_id' => (int)$user_id,
    'kind' => sanitize_text_field($kind),
    'task_id' => $task_id ? (int)$task_id : null,
    'streak_days' => is_null($streak_days) ? null : (int)$streak_days,
    'earned_at' => $earned,
    'expires_at' => $expires,
    'note' => $note ? sanitize_textarea_field($note) : null,
  ]);
}

function taskstreak_rest_list_tasks() {
  global $wpdb;
  $user_id = get_current_user_id();
  $today = current_time('Y-m-d');

  $tasks = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}taskstreak_tasks
    WHERE user_id = %d
    ORDER BY id ASC
  ", $user_id));

  $done_rows = $wpdb->get_results($wpdb->prepare("
    SELECT task_id, reps_count FROM {$wpdb->prefix}taskstreak_completions
    WHERE user_id = %d AND day = %s
  ", $user_id, $today));

  $done = [];
  foreach ($done_rows as $r) $done[(int)$r->task_id] = (int)$r->reps_count;

  return array_map(function($t) use ($done) {
    return [
      'id' => (int)$t->id,
      'title' => $t->title,
      'reps_target' => (int)$t->reps_target,
      'days_mask' => (int)$t->days_mask,
      'repeats_limit' => is_null($t->repeats_limit) ? null : (int)$t->repeats_limit,
      'repeats_done' => (int)$t->repeats_done,
      'streak_days' => (int)$t->streak_days,
      'tier' => $t->tier,
      'grace_active' => (int)$t->grace_active,
      'reps_today' => !empty($done[(int)$t->id]) ? (int)$done[(int)$t->id] : 0,
      'done_today' => (!empty($done[(int)$t->id]) && (int)$done[(int)$t->id] >= (int)$t->reps_target),
    ];
  }, $tasks);
}

function taskstreak_rest_create_task($req) {
  global $wpdb;
  $user_id = get_current_user_id();

  $p = $req->get_json_params();
  $title = sanitize_text_field($p['title'] ?? '');
  if (!$title) return new WP_Error('bad_title', 'title required', ['status'=>400]);

  $days = $p['days'] ?? null;
  $reps_target = isset($p['reps_target']) ? (int)$p['reps_target'] : 1;
  if ($reps_target < 1) $reps_target = 1;
  $mask = taskstreak_days_mask_from_array($days);

  $limit = isset($p['repeats_limit']) && $p['repeats_limit'] !== '' ? (int)$p['repeats_limit'] : null;
  if (!is_null($limit) && $limit < 1) $limit = null;

  $now = current_time('mysql');

  $wpdb->insert("{$wpdb->prefix}taskstreak_tasks", [
    'user_id' => (int)$user_id,
    'title' => $title,
    'reps_target' => (int)$reps_target,
    'days_mask' => (int)$mask,
    'repeats_limit' => $limit,
    'repeats_done' => 0,
    'streak_days' => 0,
    'tier' => 'new',
    'grace_used_week_start' => null,
    'grace_active' => 0,
    'created_at' => $now,
    'updated_at' => $now,
  ]);

  return ['success'=>true, 'id'=>(int)$wpdb->insert_id];
}

function taskstreak_rest_update_task($req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $id = (int) $req['id'];

  $p = $req->get_json_params();
  $fields = [];

  if (isset($p['title'])) $fields['title'] = sanitize_text_field($p['title']);
  if (isset($p['days'])) $fields['days_mask'] = taskstreak_days_mask_from_array($p['days']);
  if (isset($p['reps_target'])) { $rt = (int)$p['reps_target']; if ($rt < 1) $rt = 1; $fields['reps_target'] = $rt; }
  if (array_key_exists('repeats_limit', $p)) {
    $limit = $p['repeats_limit'] === '' ? null : (int)$p['repeats_limit'];
    if (!is_null($limit) && $limit < 1) $limit = null;
    $fields['repeats_limit'] = $limit;
  }

  if (!$fields) return ['success'=>true];

  $fields['updated_at'] = current_time('mysql');

  $wpdb->update("{$wpdb->prefix}taskstreak_tasks", $fields, [
    'id' => $id,
    'user_id' => $user_id,
  ]);

  return ['success'=>true];
}

function taskstreak_rest_delete_task($req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $id = (int) $req['id'];
  $wpdb->delete("{$wpdb->prefix}taskstreak_tasks", ['id'=>$id, 'user_id'=>$user_id]);
  $wpdb->delete("{$wpdb->prefix}taskstreak_completions", ['task_id'=>$id, 'user_id'=>$user_id]);
  return ['success'=>true];
}

function taskstreak_recalc_tier($streak_days, $previous_tier) {
  if ($streak_days >= 14) return 'trophy';
  if ($streak_days >= 3) return 'flame';
  return 'new';
}

function taskstreak_rest_complete($req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $today = current_time('Y-m-d');

  $p = $req->get_json_params();
  $task_id = (int)($p['task_id'] ?? 0);
  $undo = !empty($p['undo']);

  $task = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}taskstreak_tasks
    WHERE id = %d AND user_id = %d
  ", $task_id, $user_id));

  if (!$task) return new WP_Error('not_found', 'task not found', ['status'=>404]);

  // Update reps for today (count-based completion)
$row = $wpdb->get_row($wpdb->prepare("
  SELECT id, reps_count FROM {$wpdb->prefix}taskstreak_completions
  WHERE user_id=%d AND task_id=%d AND day=%s
  LIMIT 1
", $user_id, $task_id, $today));

$reps_today = $row ? (int)$row->reps_count : 0;
$reps_target = max(1, (int)$task->reps_target);

if ($undo) {
  // Undo removes one rep. If it hits zero, remove the row.
  $reps_today = max(0, $reps_today - 1);
  if ($row) {
    if ($reps_today === 0) {
      $wpdb->delete("{$wpdb->prefix}taskstreak_completions", ['id' => (int)$row->id]);
    } else {
      $wpdb->update("{$wpdb->prefix}taskstreak_completions", [
        'reps_count' => $reps_today,
        'updated_at' => current_time('mysql'),
      ], ['id' => (int)$row->id]);
    }
  }
  return ['success'=>true, 'reps_today'=>$reps_today, 'done_today'=>($reps_today >= $reps_target)];
}

// Add one rep
$reps_today = $reps_today + 1;
if ($row) {
  $wpdb->update("{$wpdb->prefix}taskstreak_completions", [
    'reps_count' => $reps_today,
    'updated_at' => current_time('mysql'),
  ], ['id' => (int)$row->id]);
} else {
  $wpdb->insert("{$wpdb->prefix}taskstreak_completions", [
    'user_id' => $user_id,
    'task_id' => $task_id,
    'day' => $today,
    'reps_count' => $reps_today,
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql'),
  ]);
}

// Only award streak progress when the task reaches its reps target for the day.
$just_completed = ($reps_today === $reps_target);

// Streak logic (simple and kind):
// If task was not completed yesterday and it was due yesterday, streak would normally reset.
// We allow one grace per week_start window to protect the streak once.
$yesterday = gmdate('Y-m-d', strtotime($today . ' -1 day'));

$settings = taskstreak_get_user_settings($user_id);
$week_start = (int)($settings['week_start'] ?? 1);
$week_start_date = taskstreak_week_start_date($today, $week_start);

$was_due_yesterday = taskstreak_is_due_today($task, $yesterday);

$done_yesterday_row = $wpdb->get_row($wpdb->prepare("
  SELECT reps_count FROM {$wpdb->prefix}taskstreak_completions
  WHERE user_id=%d AND task_id=%d AND day=%s
  LIMIT 1
", $user_id, $task_id, $yesterday));

$done_yesterday = $done_yesterday_row ? ((int)$done_yesterday_row->reps_count >= $reps_target) : false;

$streak = (int)$task->streak_days;
$prev_tier = $task->tier;

$grace_used = $task->grace_used_week_start ? $task->grace_used_week_start : null;
$grace_active = 0;

if ($was_due_yesterday && !$done_yesterday && $streak > 0) {
  if ($grace_used !== $week_start_date) {
    $grace_used = $week_start_date;
    $grace_active = 1;
  } else {
    $streak = 0;
    if ($prev_tier === 'trophy') $prev_tier = 'flame';
    else if ($prev_tier === 'flame') $prev_tier = 'new';
  }
}

// Increment streak only when the reps target is met today
if ($just_completed && taskstreak_is_due_today($task, $today)) {
  $streak = $streak + 1;
}

$tier = taskstreak_recalc_tier($streak, $prev_tier);

// repeats_done: count days completed, not reps
$repeats_done = (int)$task->repeats_done;
if ($just_completed) {
  if (!is_null($task->repeats_limit)) {
    $repeats_done = min((int)$task->repeats_limit, $repeats_done + 1);
  } else {
    $repeats_done = $repeats_done + 1;
  }
}

$wpdb->update("{$wpdb->prefix}taskstreak_tasks", [
  'streak_days' => $streak,
  'tier' => $tier,
  'repeats_done' => $repeats_done,
  'grace_used_week_start' => $grace_used,
  'grace_active' => $grace_active,
  'updated_at' => current_time('mysql'),
], [
  'id' => $task_id,
  'user_id' => $user_id,
]);

// Badge milestones only when a day is completed (target met)
if ($just_completed) {
  $milestones = [3, 7, 14, 30];
  foreach ($milestones as $m) {
    if ($streak === $m) taskstreak_add_history($user_id, 'milestone_' . $m, $task_id, $m, null);
  }
}

return [
  'success'=>true,
  'reps_today'=>$reps_today,
  'reps_target'=>$reps_target,
  'done_today'=>($reps_today >= $reps_target),
  'just_completed'=>$just_completed,
  'streak_days'=>$streak,
  'tier'=>$tier,
  'grace_active'=>$grace_active
];

}

function taskstreak_rest_get_order() {
  global $wpdb;
  $user_id = get_current_user_id();
  $today = current_time('Y-m-d');
  $row = $wpdb->get_row($wpdb->prepare("SELECT order_json, locked FROM {$wpdb->prefix}taskstreak_orders WHERE user_id=%d AND day=%s", $user_id, $today));
  if (!$row) return ['order'=>[], 'locked'=>1];
  $order = json_decode($row->order_json, true);
  if (!is_array($order)) $order = [];
  return ['order'=>$order, 'locked'=>(int)$row->locked];
}

function taskstreak_rest_save_order($req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $today = current_time('Y-m-d');
  $p = $req->get_json_params();
  $order = is_array($p['order'] ?? null) ? array_values(array_map('intval', $p['order'])) : [];
  $locked = !empty($p['locked']) ? 1 : 0;

  $wpdb->replace("{$wpdb->prefix}taskstreak_orders", [
    'user_id' => $user_id,
    'day' => $today,
    'order_json' => wp_json_encode($order),
    'locked' => $locked,
    'updated_at' => current_time('mysql'),
  ]);

  return ['success'=>true];
}

function taskstreak_rest_get_settings() {
  $user_id = get_current_user_id();
  return taskstreak_get_user_settings($user_id);
}

function taskstreak_rest_save_settings($req) {
  $user_id = get_current_user_id();
  $p = $req->get_json_params();

  $allowed_theme = ['light','dark','system'];
  $allowed_style = ['bar','ring','dots','wave'];
  $allowed_mode = ['rotate','fixed'];
  $allowed_sync = ['ask','device','latest'];
  $allowed_calm = ['subtle','always'];

  $settings = taskstreak_get_user_settings($user_id);

  if (isset($p['theme']) && in_array($p['theme'], $allowed_theme, true)) $settings['theme'] = $p['theme'];
  if (isset($p['week_start'])) $settings['week_start'] = max(0, min(6, (int)$p['week_start']));
  if (isset($p['progress_style']) && in_array($p['progress_style'], $allowed_style, true)) $settings['progress_style'] = $p['progress_style'];
  if (isset($p['progress_mode']) && in_array($p['progress_mode'], $allowed_mode, true)) $settings['progress_mode'] = $p['progress_mode'];
  if (isset($p['sync_preference']) && in_array($p['sync_preference'], $allowed_sync, true)) $settings['sync_preference'] = $p['sync_preference'];
  if (isset($p['calm_mode']) && in_array($p['calm_mode'], $allowed_calm, true)) $settings['calm_mode'] = $p['calm_mode'];

  return taskstreak_save_user_settings($user_id, $settings);
}

function taskstreak_rest_get_history() {
  global $wpdb;
  $user_id = get_current_user_id();
  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT id, kind, task_id, streak_days, earned_at, expires_at, note
    FROM {$wpdb->prefix}taskstreak_history
    WHERE user_id=%d
    ORDER BY earned_at DESC, id DESC
    LIMIT 200
  ", $user_id));

  return array_map(function($r){
    return [
      'id' => (int)$r->id,
      'kind' => $r->kind,
      'task_id' => is_null($r->task_id) ? null : (int)$r->task_id,
      'streak_days' => is_null($r->streak_days) ? null : (int)$r->streak_days,
      'earned_at' => $r->earned_at,
      'expires_at' => $r->expires_at,
      'note' => $r->note,
    ];
  }, $rows);
}

function taskstreak_rest_add_reflection($req) {
  $user_id = get_current_user_id();
  $p = $req->get_json_params();
  $note = sanitize_textarea_field($p['note'] ?? '');
  if (!$note) return new WP_Error('bad_note', 'note required', ['status'=>400]);
  taskstreak_add_history($user_id, 'reflection', null, null, $note);
  return ['success'=>true];
}
