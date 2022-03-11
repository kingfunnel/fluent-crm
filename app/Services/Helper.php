<?php

namespace FluentCrm\App\Services;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\UrlStores;
use FluentCrm\Includes\Helpers\Arr;

class Helper
{
    public static function urlReplaces($string)
    {
        preg_match_all('/<a[^>]+(href\=["|\'](http.*?)["|\'])/m', $string, $urls);
        $replaces = $urls[1];
        $urls = $urls[2];
        $formatted = [];
        foreach ($urls as $index => $url) {
            $urlSlug = UrlStores::getUrlSlug($url);
            $formatted[$replaces[$index]] = add_query_arg([
                'ns_url' => $urlSlug
            ], site_url());
        }
        return $formatted;
    }

    public static function attachUrls($html, $campaignUrls, $insertId)
    {
        foreach ($campaignUrls as $src => $url) {
            $campaignUrls[$src] = 'href="' . $url . '&mid=' . $insertId . '"';
        }
        return str_replace(array_keys($campaignUrls), array_values($campaignUrls), $html);
    }

    public static function generateEmailHash($insertId)
    {
        return wp_generate_uuid4();
    }

    public static function injectTrackerPixel($emailBody, $hash)
    {
        if (!$hash) {
            return $emailBody;
        }


        if (apply_filters('fluentcrm_disable_email_open_tracking', false)) {
            return $emailBody;
        }

        $trackImageUrl = add_query_arg([
            'fluentcrm' => 1,
            'route'     => 'open',
            '_e_hash'   => $hash
        ], site_url());
        $trackPixelHtml = '<img src="' . $trackImageUrl . '" alt="" />';

        if (strpos($emailBody, '{fluent_track_pixel}') !== false) {
            $emailBody = str_replace('{fluent_track_pixel}', $trackPixelHtml, $emailBody);
        } else {
            // we have to inject this
            $emailBody = str_replace('</body>', $trackPixelHtml . '</body>', $emailBody);
        }

        return $emailBody;
    }

    public static function getProfileSections()
    {
        $sections = [
            'subscriber'        => [
                'name'    => 'subscriber',
                'title'   => __('Overview', 'fluent-crm'),
                'handler' => 'route'
            ],
            'subscriber_emails' => [
                'name'    => 'subscriber_emails',
                'title'   => __('Emails', 'fluent-crm'),
                'handler' => 'route'
            ],
        ];

        if (defined('WC_PLUGIN_FILE') || class_exists('\Easy_Digital_Downloads')) {
            $sections['subscriber_purchases'] = [
                'name'    => 'subscriber_purchases',
                'title'   => __('Purchase History', 'fluent-crm'),
                'handler' => 'route'
            ];
        }

        if (defined('FLUENTFORM')) {
            $sections['subscriber_form_submissions'] = [
                'name'    => 'subscriber_form_submissions',
                'title'   => __('Form Submissions', 'fluent-crm'),
                'handler' => 'route'
            ];
        }

        $supportProviders = apply_filters('fluentcrm-support_tickets_providers', []);
        if ($supportProviders) {
            $sections['subscriber_support_tickets'] = [
                'name'    => 'subscriber_support_tickets',
                'title'   => __('Support Tickets', 'fluent-crm'),
                'handler' => 'route'
            ];
        }

        $sections['subscriber_notes'] = [
            'name'    => 'subscriber_notes',
            'title'   => __('Notes & Activities', 'fluent-crm'),
            'handler' => 'route'
        ];

        return apply_filters('fluentcrm_profile_sections', $sections);
    }

    public static function getDefaultEmailTemplate()
    {
        return 'simple';
    }

    public static function getGlobalSmartCodes()
    {
        $smartCodes[] = [
            'key'        => 'contact',
            'title'      => __('Contact', 'fluent-crm'),
            'shortcodes' => apply_filters('fluentcrm_contact_smartcodes', [
                '{{contact.full_name}}'      => __('Full Name', 'fluent-crm'),
                '{{contact.prefix}}'         => __('Name Prefix', 'fluent-crm'),
                '{{contact.first_name}}'     => __('First Name', 'fluent-crm'),
                '{{contact.last_name}}'      => __('Last Name', 'fluent-crm'),
                '{{contact.email}}'          => __('Contact Email', 'fluent-crm'),
                '{{contact.id}}'             => __('Contact ID', 'fluent-crm'),
                '{{contact.user_id}}'        => __('User ID', 'fluent-crm'),
                '{{contact.address_line_1}}' => __('Address Line 1', 'fluent-crm'),
                '{{contact.address_line_2}}' => __('Address Line 2', 'fluent-crm'),
                '{{contact.city}}'           => __('City', 'fluent-crm'),
                '{{contact.state}}'          => __('State', 'fluent-crm'),
                '{{contact.postal_code}}'    => __('Postal Code', 'fluent-crm'),
                '{{contact.country}}'        => __('Country', 'fluent-crm'),
                '{{contact.phone}}'          => __('Phone Number', 'fluent-crm'),
                '{{contact.status}}'         => __('Status', 'fluent-crm'),
                '{{contact.date_of_birth}}'  => __('Date of Birth', 'fluent-crm')
            ])
        ];

        $customFields = fluentcrm_get_option('contact_custom_fields', []);

        if ($customFields) {
            $shortcodes = [];
            foreach ($customFields as $item) {
                $shortcodes['{{contact.custom.' . $item['slug'] . '}}'] = $item['label'];
            }
            $smartCodes[] = [
                'key'        => 'contact_custom_fields',
                'title'      => 'Custom Fields',
                'shortcodes' => $shortcodes
            ];
        }

        $smartCodes[] = [
            'key'        => 'general',
            'title'      => __('General', 'fluent-crm'),
            'shortcodes' => apply_filters('fluentcrm_general_smartcodes', [
                '{{crm.business_name}}'                              => __('Business Name', 'fluent-crm'),
                '{{crm.business_address}}'                           => __('Business Address', 'fluent-crm'),
                '{{wp.admin_email}}'                                 => __('Admin Email', 'fluent-crm'),
                '{{wp.url}}'                                         => __('Site URL', 'fluent-crm'),
                '##crm.unsubscribe_url##'                            => __('Unsubscribe URL', 'fluent-crm'),
                '##crm.manage_subscription_url##'                    => __('Manage Subscription URL', 'fluent-crm'),
                '##web_preview_url##'                                => __('View On Browser URL', 'fluent-crm'),
                '{{crm.unsubscribe_html|Unsubscribe}}'               => __('Unsubscribe Hyperlink HTML', 'fluent-crm'),
                '{{crm.manage_subscription_html|Manage Preference}}' => __('Manage Subscription Hyperlink HTML', 'fluent-crm'),
            ])
        ];

        return $smartCodes;
    }

    public static function getDoubleOptinSettings()
    {
        if ($settings = fluentcrm_get_option('double_optin_settings', [])) {
            if (empty($settings['after_confirmation_type'])) {
                $settings['after_confirmation_type'] = 'message';
                $settings['after_conf_redirect_url'] = '';
            }
            return $settings;
        }

        $businessName = '';
        $businessEmail = '';
        $businessAddress = '';
        $subject = 'Please Confirm Subscription';
        $business = fluentcrmGetGlobalSettings('business_settings', []);

        if (!empty($business['business_name'])) {
            $businessName = $business['business_name'];
            $subject = "{$businessName} : Please Confirm Subscription";
            if (!empty($business['business_address'])) {
                $businessAddress = $business['business_address'];
            }
        }

        $emailSettings = fluentcrmGetGlobalSettings('email_settings', []);
        if (!empty($emailSettings['from_email'])) {
            $businessEmail = $emailSettings['from_email'];
        }

        return [
            'email_subject'           => $subject,
            'design_template'         => 'simple',
            'email_body'              => '<h2>Please Confirm Subscription</h2><p><a style="color: #ffffff; background-color: #454545; font-size: 16px; border-radius: 5px; text-decoration: none; font-weight: normal; font-style: normal; padding: 0.8rem 1rem; border-color: #0072ff;" href="#activate_link#">Yes, subscribe me to the mailing list</a></p><p>&nbsp;</p><p>If you received this email by mistake, simply delete it. You won\'t be subscribed if you don\'t click the confirmation link above.</p><p>For questions about this list, please contact:<br />' . $businessEmail . '</p>',
            'after_confirmation_type' => 'message',
            'after_confirm_message'   => '<h2>Subscription Confirmed</h2><p>Your subscription to our list has been confirmed.</p><p>Thank you for subscribing!</p><p>&nbsp;</p><p>' . $businessName . '</p><p>' . $businessAddress . '</p><p>&nbsp;</p><p><a style="color: #ffffff; background-color: #404040; font-size: 16px; border-radius: 5px; text-decoration: none; font-weight: normal; font-style: normal; padding: 0.8rem 1rem; border-color: #0072ff;" href="' . site_url() . '">Continue to our Website</a></p>',
            'after_conf_redirect_url' => '',
        ];
    }

    public static function getEmailDesignTemplates()
    {
        $defaultDesignConfig = [
            'content_width'        => 700,
            'headings_font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'",
            'text_color'           => '#202020',
            'link_color'           => '',
            'headings_color'       => '#202020',
            'body_bg_color'        => '#FAFAFA',
            'content_bg_color'     => '#FFFFFF',
            'footer_text_color'    => '#202020',
            'content_font_family'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'",
        ];


        $classicConfig = [
            'content_font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'",
        ];

        if (defined('FLUENTCAMPAIGN')) {
            $defaultDesignConfig['disable_footer'] = 'no';
            $classicConfig['disable_footer'] = 'no';
        }

        $plainConfig = $defaultDesignConfig;
        $plainConfig['body_bg_color'] = '#FFFFFF';

        return apply_filters('fluentcrm_email_design_templates', [
            'simple'      => [
                'id'            => 'simple',
                'label'         => __('Simple Boxed', 'fluent-crm'),
                'image'         => fluentCrm()['url.assets'] . 'images/simple.png',
                'config'        => $defaultDesignConfig,
                'use_gutenberg' => true
            ],
            'plain'       => [
                'id'            => 'plain',
                'label'         => __('Plain Centered', 'fluent-crm'),
                'image'         => fluentCrm()['url.assets'] . 'images/plain-centered.png',
                'config'        => $plainConfig,
                'use_gutenberg' => true
            ],
            'classic'     => [
                'id'            => 'classic',
                'label'         => __('Plain Left', 'fluent-crm'),
                'image'         => fluentCrm()['url.assets'] . 'images/classic.png',
                'config'        => $plainConfig,
                'use_gutenberg' => true
            ],
            'raw_classic' => [
                'id'            => 'raw_classic',
                'label'         => __('Classic Editor', 'fluent-crm'),
                'image'         => fluentCrm()['url.assets'] . 'images/classic_raw.png',
                'config'        => $classicConfig,
                'use_gutenberg' => false,
                'template_type' => 'classic_editor',
                'template_info' => '<h3>Classic Text Based Email</h3><p>Type your simple email and FluentCRM will send that without altering any design processing. The default footer will be injected after your content if footer is not disabled.</p>'
            ],
            'raw_html'    => [
                'id'            => 'raw_html',
                'label'         => __('Raw HTML', 'fluent-crm'),
                'image'         => fluentCrm()['url.assets'] . 'images/raw-html.png',
                'config'        => [],
                'use_gutenberg' => false,
                'template_type' => 'raw_text_box',
                'template_info' => '<h3>Raw HTML Template</h3><p>You can use any type of valid html and FluentCRM will send that without altering any design processing.</p>'
            ]
        ]);
    }

    public static function getTemplateConfig($templateName = '')
    {
        if (!$templateName) {
            $templateName = self::getDefaultEmailTemplate();
        }
        return Arr::get(self::getEmailDesignTemplates(), $templateName . '.config', []);
    }

    public static function getActivatedFeatures()
    {
        return [
            'fluentcampaign' => defined('FLUENTCAMPAIGN')
        ];
    }

    public static function getContactPrefixes()
    {
        return apply_filters('fluentcrm_contact_name_prefixes', [
            'Mr',
            'Mrs',
            'Ms'
        ]);
    }

    public static function getGlobalEmailSettings()
    {
        $defaultFooter = '{{crm.business_name}}, {{crm.business_address}}<br>Don\'t like these emails? <a href="##crm.unsubscribe_url##">Unsubscribe</a> or <a href="##crm.manage_subscription_url##">Manage Email Subscriptions</a>';

        if ($settings = fluentcrmGetGlobalSettings('email_settings', [])) {
            if (empty($settings['email_footer'])) {
                $settings['email_footer'] = $defaultFooter;
            }
            return $settings;
        }

        return [
            'from_name'         => '',
            'from_email'        => '',
            'emails_per_second' => 15,
            'email_footer'      => $defaultFooter
        ];
    }

    public static function getPurchaseHistoryProviders()
    {
        $validProviders = [];

        if (defined('WC_PLUGIN_FILE')) {
            $validProviders['woocommerce'] = [
                'title' => __('Woocommerce Purchase History', 'fluent-crm'),
                'name'  => __('WooCommerce', 'fluent-crm')
            ];
        }

        if (class_exists('\Easy_Digital_Downloads')) {
            $validProviders['edd'] = [
                'title' => __('EDD Purchase History', 'fluent-crm'),
                'name'  => __('Easy Digital Downloads', 'fluent-crm')
            ];
        }

        if (defined('WPPAYFORM_VERSION')) {
            $validProviders['payform'] = [
                'title' => __('WPPayForm Purchase History', 'fluent-crm'),
                'name'  => __('WP Pay Forms', 'fluent-crm')
            ];
        }

        return apply_filters('fluentcrm_purchase_history_providers', $validProviders);
    }

    public static function getThemePrefScheme()
    {
        static $pref;
        if (!$pref) {
            list($color_palette) = get_theme_support('editor-color-palette');

            if (empty($color_palette) || !is_array($color_palette) || count($color_palette) < 2) {
                $color_palette = [
                    [
                        "name"  => "Black",
                        "slug"  => "black",
                        "color" => "#000000"
                    ],
                    [
                        "name"  => "Cyan bluish gray",
                        "slug"  => "cyan-bluish-gray",
                        "color" => "#abb8c3"
                    ],
                    [
                        "name"  => "White",
                        "slug"  => "white",
                        "color" => "#ffffff"
                    ],
                    [
                        "name"  => "Pale pink",
                        "slug"  => "pale-pink",
                        "color" => "#f78da7"
                    ],
                    [
                        "name"  => "Luminous vivid orange",
                        "slug"  => "luminous-vivid-orange",
                        "color" => "#ff6900"
                    ],
                    [
                        "name"  => "Luminous vivid amber",
                        "slug"  => "luminous-vivid-amber",
                        "color" => "#fcb900"
                    ],
                    [
                        "name"  => "Light green cyan",
                        "slug"  => "light-green-cyan",
                        "color" => "#7bdcb5"
                    ],
                    [
                        "name"  => "Vivid green cyan",
                        "slug"  => "vivid-green-cyan",
                        "color" => "#00d084"
                    ],
                    [
                        "name"  => "Pale cyan blue",
                        "slug"  => "pale-cyan-blue",
                        "color" => "#8ed1fc"
                    ],
                    [
                        "name"  => "Vivid cyan blue",
                        "slug"  => "vivid-cyan-blue",
                        "color" => "#0693e3"
                    ],
                    [
                        "name"  => "Vivid purple",
                        "slug"  => "vivid-purple",
                        "color" => "#9b51e0"
                    ]
                ];
            }

            list($font_sizes) = (array)get_theme_support('editor-font-sizes');

            if (empty($font_sizes)) {
                $font_sizes = [
                    [
                        'name'      => 'Small',
                        'shortName' => 'S',
                        'size'      => 14,
                        'slug'      => 'small'
                    ],
                    [
                        'name'      => 'Medium',
                        'shortName' => 'M',
                        'size'      => 18,
                        'slug'      => 'medium'
                    ],
                    [
                        'name'      => 'Large',
                        'shortName' => 'L',
                        'size'      => 24,
                        'slug'      => 'large'
                    ],
                    [
                        'name'      => 'Larger',
                        'shortName' => 'XL',
                        'size'      => 32,
                        'slug'      => 'larger'
                    ]
                ];
            }

            $pref = apply_filters('fluentcrm_theme_pref', [
                'colors'     => (array)$color_palette,
                'font_sizes' => (array)$font_sizes
            ]);
        }

        return $pref;

    }

    public static function getColorSchemeValue($colorName)
    {
        static $colorMap = [];
        if (isset($colorMap[$colorName])) {
            return $colorMap[$colorName];
        }
        $pref = self::getThemePrefScheme();
        $colors = $pref['colors'];
        foreach ($colors as $color) {
            $colorMap[$color['slug']] = $color['color'];
            if ($color['slug'] == $colorName) {
                return $color['color'];
            }
        }
        return '';
    }

    public static function generateThemePrefCss()
    {
        static $color_css;
        if ($color_css) {
            return $color_css;
        }
        $pref = self::getThemePrefScheme();

        $css = '';
        if (isset($pref['colors'])) {
            foreach ($pref['colors'] as $color) {
                if (isset($color['slug']) && isset($color['color'])) {
                    $slug = self::kebabCase($color['slug']);
                    $css .= '.has-' . $slug . '-color  { color: ' . $color['color'] . ';} ';
                    $css .= '.has-' . $slug . '-background-color  { background-color: ' . $color['color'] . '; background: ' . $color['color'] . '; } ';
                    $css .= 'a.has-' . $slug . '-background-color  { border: 1px solid ' . $color['color'] . '; } ';
                }
            }
        }

        if ($pref['font_sizes']) {
            foreach ($pref['font_sizes'] as $size) {
                if (isset($size['slug']) && isset($size['size'])) {
                    $slug = self::kebabCase($size['slug']);
                    $css .= '.fc_email_body .has-' . $slug . '-font-size  { font-size: ' . $size['size'] . 'px !important;} ';
                }
            }
        }

        $color_css = $css;
        return $color_css;
    }

    public static function kebabCase($string)
    {
        return implode('-', array_filter(preg_split('/(\d)/', strtolower(strval($string)), null, PREG_SPLIT_DELIM_CAPTURE)));
    }

    public static function getMailHeadersFromSettings($emailSettings = [])
    {
        if (empty($emailSettings) || Arr::get($emailSettings, 'is_custom') == 'no') {
            $emailSettings = fluentcrmGetGlobalSettings('email_settings', []);
        }

        if (empty($emailSettings)) {
            return [];
        }

        $headers = [];
        if (Arr::get($emailSettings, 'from_name') && Arr::get($emailSettings, 'from_email')) {
            $headers['From'] = $emailSettings['from_name'] . ' <' . $emailSettings['from_email'] . '>';
        } else if ($fromEmail = Arr::get($emailSettings, 'from_email')) {
            $headers['From'] = $fromEmail;
        }

        if (Arr::get($emailSettings, 'reply_to_name') && Arr::get($emailSettings, 'reply_to_email')) {
            $headers['Reply-To'] = $emailSettings['reply_to_name'] . ' <' . $emailSettings['reply_to_email'] . '>';
        } else if ($replyTo = Arr::get($emailSettings, 'reply_to_email')) {
            $headers['Reply-To'] = $replyTo;
        }

        return $headers;
    }

    public static function getMailHeader($existingHeader = [])
    {
        if (!empty($existingHeader['From'])) {
            return $existingHeader;
        }

        $headers = [];
        static $globalHeaders;
        if ($globalHeaders) {
            return $globalHeaders;
        }

        $globalEmailSettings = fluentcrmGetGlobalSettings('email_settings', []);

        $fromName = Arr::get($globalEmailSettings, 'from_name');
        $fromEmail = Arr::get($globalEmailSettings, 'from_email');

        if ($fromName && $fromEmail) {
            $headers['From'] = $fromName . ' <' . $fromEmail . '>';
        } else if ($fromEmail) {
            $headers['From'] = $fromEmail;
        }

        $replyName = Arr::get($globalEmailSettings, 'reply_to_name');
        $replyEmail = Arr::get($globalEmailSettings, 'reply_to_email');

        if ($replyName && $replyEmail) {
            $headers['Reply-To'] = $replyName . ' <' . $replyEmail . '>';
        } else if ($replyEmail) {
            $headers['Reply-To'] = $replyEmail;
        }

        $globalHeaders = $headers;

        return $globalHeaders;
    }

    public static function recordCampaignRevenue($campaignId, $amount, $currency = 'USD', $isRefunded = false)
    {
        $currency = strtolower($currency);
        $existing = fluentcrm_get_campaign_meta($campaignId, '_campaign_revenue');
        $data = [];
        if ($existing && $existing->value) {
            $data = $existing->value;
        }

        if (!isset($data[$currency]) || !is_array($data)) {
            $data[$currency] = 0;
        }

        if ($isRefunded) {
            if ($data[$currency] > $amount) {
                $data[$currency] -= $amount;
            }
        } else {
            $data[$currency] += $amount;
        }

        return fluentcrm_update_campaign_meta($campaignId, '_campaign_revenue', $data);

    }

    public static function getWPMapUserInfo($user)
    {
        if (is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }

        $subscriber = array_filter([
            'user_id'    => $user->ID,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->user_email
        ]);

        if ($address1 = get_user_meta($user->ID, 'billing_address_1', true)) {
            $subscriber['address_line_1'] = $address1;
        }

        if ($address2 = get_user_meta($user->ID, 'billing_address_2', true)) {
            $subscriber['address_line_2'] = $address2;
        }

        if ($city = get_user_meta($user->ID, 'billing_city', true)) {
            $subscriber['city'] = $city;
        }

        if ($postalCode = get_user_meta($user->ID, 'billing_postcode', true)) {
            $subscriber['postal_code'] = $postalCode;
        }

        if ($country = get_user_meta($user->ID, 'billing_country', true)) {
            $subscriber['country'] = $country;
        }

        if ($state = get_user_meta($user->ID, 'billing_state', true)) {
            $subscriber['state'] = $state;
        }

        if ($state = get_user_meta($user->ID, 'billing_phone', true)) {
            $subscriber['phone'] = $state;
        }

        $subscriber = array_filter($subscriber);

        return apply_filters('fluentcrm_user_map_data', $subscriber, $user);
    }

    public static function isUserSyncEnabled()
    {
        static $result = null;
        if ($result === null) {
            $settings = fluentcrm_get_option('user_syncing_settings', []);
            $result = $settings && isset($settings['status']) && $settings['status'] == 'yes';
        }

        return $result;
    }

    public static function isContactDeleteOnUserDeleteEnabled()
    {
        static $result = null;
        if ($result === null) {
            $settings = fluentcrm_get_option('user_syncing_settings', []);
            $result = $settings && isset($settings['delete_contact_on_user_delete']) && $settings['delete_contact_on_user_delete'] == 'yes';
        }

        return $result;
    }

    public static function deleteContacts($contactIds)
    {
        if (!$contactIds) {
            return false;
        }

        do_action('fluentcrm_before_subscribers_deleted', $contactIds);
        Subscriber::whereIn('id', $contactIds)->delete();
        do_action('fluentcrm_after_subscribers_deleted', $contactIds);
        return true;
    }

    public static function hasComplianceText($text)
    {
        if (apply_filters('fluencrm_disable_check_compliance_string', false, $text)) {
            return true;
        }

        $lookUpTexts = [
            '##crm.manage_subscription_url##',
            '##crm.unsubscribe_url##',
            '{{crm.unsubscribe_html',
            '{{crm.manage_subscription_html'
        ];

        foreach ($lookUpTexts as $lookUpText) {
            if (strpos($text, $lookUpText) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function maybeDisableEmojiOnEmail()
    {
        static $disabled;
        if ($disabled) {
            return;
        }
        if (apply_filters('fluentcrm_disable_emoji_to_image', true)) {
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        }
        $disabled = true;
    }

    public static function getPublicLists()
    {
        $emailSettings = self::getGlobalEmailSettings();
        $lists = [];
        $preListType = Arr::get($emailSettings, 'pref_list_type', 'none');
        if ($preListType == 'filtered_only') {
            $prefListItems = Arr::get($emailSettings, 'pref_list_items', []);
            if ($prefListItems) {
                $lists = Lists::whereIn('id', $prefListItems)->get();
                if ($lists->isEmpty()) {
                    return [];
                }
            }
        } else if ($preListType == 'all') {
            $lists = Lists::get();
            if ($lists->isEmpty()) {
                return [];
            }
        }

        return $lists;
    }
}
