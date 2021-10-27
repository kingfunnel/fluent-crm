<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Services\Helper;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Request\Request;
use FluentCrm\App\Models\Subscriber;

class ImporterController extends Controller
{
    public function getDrivers()
    {
        $drivers = apply_filters('fluentcrm_import_providers', [
            'csv'   => [
                'label'    => 'CSV File',
                'logo'     => fluentCrmMix('images/csv.svg'),
                'disabled' => false
            ],
            'users' => [
                'label'    => 'WordPress Users',
                'logo'     => fluentCrmMix('images/wordpress.svg'),
                'disabled' => false
            ]
        ]);

        if ($proDrivers = $this->getProDrivers()) {
            $drivers = array_merge($drivers, $proDrivers);
        }

        return [
            'drivers' => $drivers
        ];
    }

    public function getDriver(Request $request, $driver)
    {
        if ($driver == 'users') {
            return $this->processUserDriver($request);
        }

        $response = apply_filters('fluentcrm_get_import_driver_' . $driver, false, $request);

        if (!$response || is_wp_error($response)) {
            $message = 'Sorry no driver found for this import';
            if (is_wp_error($response)) {
                $message = $response->get_error_message();
            }
            return $this->sendError([
                'message' => $message
            ]);
        }

        return $response;
    }

    public function importData(Request $request, $driver)
    {
        $config = $request->get('config');
        $page = $request->get('importing_page');

        if ($driver == 'users') {
            return $this->processUserImport($config, $page);
        }

        $response = apply_filters('fluentcrm_post_import_driver_' . $driver, false, $config, $page);

        if (!$response || is_wp_error($response)) {
            $message = 'Sorry no driver found for this import';
            if (is_wp_error($response)) {
                $message = $response->get_error_message();
            }
            return $this->sendError([
                'message' => $message
            ]);
        }

        return $response;
    }

    private function processUserDriver($request)
    {
        $summary = $request->get('summary');

        if ($summary) {
            $config = $request->get('config');

            $userQuery = new \WP_User_Query([
                'role__in' => Arr::get($config, 'roles'),
                'number'   => 5,
                'fields'   => ['ID', 'display_name', 'user_email'],
            ]);

            $users = $userQuery->get_results();
            $total = $userQuery->get_total();

            $formattedUsers = [];

            foreach ($users as $user) {
                $formattedUsers[] = [
                    'name'  => $user->display_name,
                    'email' => $user->user_email
                ];
            }

            return $this->send([
                'import_info' => [
                    'subscribers'       => $formattedUsers,
                    'total'             => $total,
                    'has_list_config'   => true,
                    'has_tag_config'    => true,
                    'has_status_config' => true,
                    'has_update_config' => true,
                    'has_silent_config' => true
                ]]);
        }

        if (!function_exists('get_editable_roles')) {
            require_once(ABSPATH . '/wp-admin/includes/user.php');
        }
        $roles = \get_editable_roles();

        $formattedRoles = [];

        foreach ($roles as $roleKey => $role) {
            $formattedRoles[] = [
                'id'    => $roleKey,
                'label' => $role['name']
            ];
        }

        return [
            'config' => [
                'roles' => []
            ],
            'fields' => [
                'roles' => [
                    'label'              => 'Select User Roles',
                    'inline_help'        => 'Please check the user roles that you want to import as contact',
                    'type'               => 'checkbox-group',
                    'options'            => $formattedRoles,
                    'input_class'        => 'fluentcrm_2col_labels',
                    'has_all_selector'   => true,
                    'all_selector_label' => 'All'
                ]
            ],
            'labels' => [
                'step_2' => 'Next [Review Data]',
                'step_3' => 'Import Users Now'
            ]
        ];
    }

    private function processUsers($users, $inputs)
    {
        $subscribers = [];
        foreach ($users as $user) {
            $subscriber = Helper::getWPMapUserInfo($user);
            $subscriber['source'] = 'wp_users';
            if ($subscriber['email']) {
                $subscribers[] = $subscriber;
            }
        }

        $sendDoubleOptin = Arr::get($inputs, 'double_optin_email') == 'yes';

        return Subscriber::import(
            $subscribers,
            $inputs['tags'],
            $inputs['lists'],
            $inputs['update'],
            $inputs['new_status'],
            $sendDoubleOptin
        );
    }

    private function processUserImport($config, $page)
    {
        $inputs = Arr::only($config, [
            'map', 'tags', 'lists', 'roles', 'update', 'new_status', 'double_optin_email', 'import_silently'
        ]);

        $limit = apply_filters('fluentcrm_process_subscribers_per_request', 100);

        $userQuery = new \WP_User_Query([
            'role__in' => $inputs['roles'],
            'number'   => $limit,
            'offset'   => ($page - 1) * $limit
        ]);

        if (Arr::get($inputs, 'import_silently') == 'yes') {
            if (!defined('FLUENTCRM_DISABLE_TAG_LIST_EVENTS')) {
                define('FLUENTCRM_DISABLE_TAG_LIST_EVENTS', true);
            }
        }

        $total = $userQuery->get_total();
        $users = $userQuery->get_results();
        if ($users) {
            $this->processUsers($users, $inputs);
        }

        $hasRecords = !!count($users);

        return $this->sendSuccess([
            'page_total'   => ceil($total / $limit),
            'record_total' => $total,
            'has_more'     => $hasRecords,
            'current_page' => $page,
            'next_page'    => $page + 1
        ]);
    }

    private function getProDrivers()
    {
        $drivers = [];

        if (defined('FLUENTCAMPAIGN')) {
            return $drivers;
        }

        if (defined('LLMS_PLUGIN_FILE')) {
            $drivers['lifterlms'] = [
                'label'            => 'LifterLMS',
                'logo'             => fluentCrmMix('images/lifterlms.png'),
                'disabled'         => true,
                'disabled_message' => 'Import LifterLMS students by course and groups then segment by associate tags. This is a pro feature. Please upgrade to activate this feature'
            ];
        }

        if (defined('LEARNDASH_VERSION')) {
            $drivers['learndash'] = [
                'label'            => 'LearnDash',
                'logo'             => fluentCrmMix('images/learndash.png'),
                'disabled'         => true,
                'disabled_message' => 'Import LearnDash students by course and groups then segment by associate tags. This is a pro feature. Please upgrade to activate this feature'
            ];
        }

        if (defined('TUTOR_VERSION')) {
            $drivers['tutorlms'] = [
                'label'            => 'TutorLMS',
                'logo'             => fluentCrmMix('images/tutorlms.jpg'),
                'disabled'         => true,
                'disabled_message' => 'Import TutorLMS students by course then segment by associate tags. This is a pro feature. Please upgrade to activate this feature'
            ];
        }

        if (defined('PMPRO_VERSION')) {
            $drivers['pmpro'] = [
                'label'            => 'Paid Membership Pro',
                'logo'             => fluentCrmMix('images/pmpro.png'),
                'disabled'         => true,
                'disabled_message' => 'Import Paid Membership Pro members by membership levels then segment by associate tags. This is a pro feature. Please upgrade to activate this feature'
            ];
        }

        if (defined('WLM3_PLUGIN_VERSION')) {
            $drivers['wishlist_member'] = [
                'label'            => 'Wishlist member',
                'logo'             => fluentCrmMix('images/wishlist_member.png'),
                'disabled'         => true,
                'disabled_message' => 'Import Wishlist members by membership levels then segment by associate tags. This is a pro feature. Please upgrade to activate this feature'
            ];
        }

        if (class_exists('\Restrict_Content_Pro')) {
            $drivers['rcp'] = [
                'label'            => 'Restrict Content Pro',
                'logo'             => fluentCrmMix('images/rcp.png'),
                'disabled'         => true,
                'disabled_message' => 'Import Restrict Content Pro members by membership levels then segment by associate tags. This is a pro feature. Please upgrade to activate this feature'
            ];
        }

        if (defined('BP_REQUIRED_PHP_VERSION') && function_exists('\buddypress')) {

            $pluginName = 'BuddyPress';
            $logo = fluentCrmMix('images/buddypress.png');

            if (defined('BP_PLATFORM_VERSION')) {
                $pluginName = 'BuddyBoss';
                $logo = fluentCrmMix('images/buddyboss.svg');
            }

            $drivers['buddypress'] = [
                'label'            => $pluginName,
                'logo'             => $logo,
                'disabled'         => true,
                'disabled_message' => sprintf('Import %s members by member groups and member types then segment by associate tags. This is a pro feature. Please upgrade to activate this feature', $pluginName)
            ];
        }

        if (defined('LP_PLUGIN_FILE')) {
            $drivers['learnpress'] = [
                'label'            => 'LearnPress',
                'logo'             => fluentCrmMix('images/learnpress.png'),
                'disabled'         => true,
                'disabled_message' => 'Import LearnPress students by course then segment by associate tags. This is a pro feature. Please upgrade to activate this feature'
            ];
        }

        return $drivers;
    }
}
