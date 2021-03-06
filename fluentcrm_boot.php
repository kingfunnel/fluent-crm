<?php

!defined('WPINC') && die;

define('FLUENTCRM', 'fluentcrm');
define('FLUENTCRM_UPLOAD_DIR', '/fluentcrm');
define('FLUENTCRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENTCRM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FLUENTCRM_PLUGIN_VERSION', '2.3.0');

spl_autoload_register(function ($class) {
    $match = 'FluentCrm';
    if (!preg_match("/\b{$match}\b/", $class)) {
        return;
    }

    $path = plugin_dir_path(__FILE__);
    $file = str_replace(
        ['FluentCrm', '\\', '/App/', '/Includes/'],
        ['', DIRECTORY_SEPARATOR, 'app/', 'includes/'],
        $class
    );
    require(trailingslashit($path) . trim($file, '/') . '.php');
});

// Keep it here, doesn't work in plugin files/classes
add_filter('cron_schedules', function ($schedules) {
    if(!is_array($schedules)) {
        $schedules = [];
    }
    if(!isset($schedules['fluentcrm_every_minute'])) {
        $schedules['fluentcrm_every_minute'] = array(
            'interval' => 60,
            'display'  => esc_html__('Every Minute (FluentCRM)', 'fluentform'),
        );
    }

    return $schedules;
}, 11);

add_action('fluentcrm_loaded', function () {
    $hookName = 'fluentcrm_scheduled_minute_tasks';
    if (!wp_next_scheduled($hookName)) {
        wp_schedule_event(time(), 'fluentcrm_every_minute', $hookName);
    }

    $dailyHook = 'fluentcrm_scheduled_hourly_tasks';
    if (!wp_next_scheduled($dailyHook)) {
        wp_schedule_event(time(), 'hourly', $dailyHook);
    }
}, 10);


include 'includes/WPFluent/wpfluent.php';
include 'includes/WPOrm/autoload.php';
include 'includes/fluentvalidator/fluentvalidator.php';
