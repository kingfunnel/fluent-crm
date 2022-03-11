<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\Includes\Emogrifier\Emogrifier;
use FluentCrm\Includes\Helpers\Arr;

class EmailDesignTemplates
{
    public function addPlainTemplate($emailBody, $templateData, $campaign)
    {
        $templateData = $this->filterTemplateData($templateData);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.plain.Template', $templateData);
        $emailBody = $emailBody->__toString();
        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    public function addSimpleTemplate($emailBody, $templateData, $campaign)
    {
        if(empty($templateData['config']['body_bg_color'])) {
            $templateData['config']['body_bg_color'] = '#FAFAFA';
        }

        if(empty($templateData['config']['content_bg_color'])) {
            $templateData['config']['content_bg_color'] = '#ffffff';
        }

        $templateData = $this->filterTemplateData($templateData);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.simple.Template', $templateData);
        $emailBody = $emailBody->__toString();
        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    public function addClassicTemplate($emailBody, $templateData, $campaign)
    {
        if(empty($templateData['config']['content_bg_color'])) {
            $templateData['config']['content_bg_color'] = '#ffffff';
        }

        $templateData = $this->filterTemplateData($templateData);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.classic.Template', $templateData);
        $emailBody = $emailBody->__toString();

        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return  $emogrifier->emogrify();
    }

    public function addRawClassicTemplate($emailBody, $templateData, $campaign)
    {
        $templateData = $this->filterTemplateData($templateData);

        $configDefault = [
            'content_width' => '',
            'headings_font_family' => '',
            'text_color' => '',
            'headings_color' => '',
            'link_color' => '',
            'body_bg_color' => '',
            'content_bg_color' => '',
            'footer_text_color' => '',
            'content_font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'",
        ];

        $templateData['config'] = wp_parse_args($templateData['config'], $configDefault);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.raw_classic.Template', $templateData);
        $emailBody = $emailBody->__toString();
        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    private function filterTemplateData($templateData)
    {
        if(Arr::get($templateData, 'config.disable_footer') == 'yes') {
            $templateData['footer_text'] = '';
        }

        return $templateData;
    }

}
