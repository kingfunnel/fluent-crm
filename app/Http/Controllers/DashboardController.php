<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Services\Stats;

class DashboardController extends Controller
{
    public function getStats(Stats $stats)
    {
        $overallStats = $stats->getCounts();

        return [
            'stats' => $overallStats,
            'sales' => apply_filters('fluentcrm_sales_stats', []),
            'onboarding' => $stats->getOnboardingStat(),
            'quick_links' => $stats->getQuickLinks(),
            'ff_config' => [
                'is_installed' => defined('FLUENTFORM'),
                'create_form_link' => admin_url('admin.php?page=fluent_forms#add=1')
            ]
        ];
    }
}
