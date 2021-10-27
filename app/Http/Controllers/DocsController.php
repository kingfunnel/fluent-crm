<?php

namespace FluentCrm\App\Http\Controllers;


use FluentCrm\Includes\Helpers\Arr;

class DocsController extends Controller
{
    private $restApi = 'https://fluentcrm.com/wp-json/wp/v2/';

    public function index()
    {
        $request = wp_remote_get($this->restApi.'docs?per_page=100');

        $docs = json_decode(wp_remote_retrieve_body($request), true);

        $formattedDocs = [];

        foreach ($docs as $doc) {
            $primaryCategory = Arr::get($doc, 'taxonomy_info.doc_category.0', ['value' => 'none', 'label' => 'Other']);
            $formattedDocs[] = [
                'title' => $doc['title']['rendered'],
                'content' => $doc['content']['rendered'],
                'link' => $doc['link'],
                'category' => $primaryCategory
            ];
        }

        return [
            'docs' => $formattedDocs
        ];
    }

    public function getAddons()
    {
        $addOns = [
            'fluentform' => [
                'title' => 'Fluent Forms',
                'logo' => fluentCrmMix('images/fluentform.png'),
                'is_installed' => defined('FLUENTFORM'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluentform/',
                'settings_url' => admin_url('admin.php?page=fluent_forms'),
                'action_text' => 'Install Fluent Forms',
                'description' => 'Collect leads and build any type of forms, accept payments, connect with your CRM with the Fastest Contact Form Builder Plugin for WordPress'
            ],
            'fluentsmtp' => [
                'title' => 'Fluent SMTP',
                'logo' => fluentCrmMix('images/fluent-smtp.svg'),
                'is_installed' => defined('FLUENTMAIL'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-smtp/',
                'settings_url' => admin_url('options-general.php?page=fluent-mail#/'),
                'action_text' => 'Install Fluent SMTP',
                'description' => 'The Ultimate SMTP and SES Plugin for WordPress. Connect with any SMTP, SendGrid, Mailgun, SES, Sendinblue, PepiPost, Google, Microsoft and more.'
            ],
            'fluentconnect' => [
                'title' => 'Fluent Connect',
                'logo' => fluentCrmMix('images/fluent-connect.svg'),
                'is_installed' => defined('FLUENT_CONNECT_PLUGIN_VERSION'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-connect/',
                'settings_url' => admin_url('admin.php?page=fluent-connect#/'),
                'action_text' => 'Install Fluent Connect',
                'description' => 'Connect FluentCRM with ThriveCart and create, segment contact and run automation on ThriveCart purchase events.'
            ]
        ];

        return [
            'addons' => $addOns
        ];
    }
}
