<?php if (!defined('ABSPATH')) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2196F3">
  <link rel="manifest" href="<?php echo esc_url(TASKSTREAK_URL . 'assets/manifest.webmanifest'); ?>">
  <?php wp_head(); ?>
</head>
<body class="taskstreak-app-shell">
  <div id="taskstreak-root"></div>
  <?php wp_footer(); ?>
</body>
</html>
