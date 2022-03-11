<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Services\ExternalIntegrations\MailComplaince\Webhook;
use FluentCrm\Includes\Request\Request;

class WebhookBounceController extends Controller
{
    private $validServices = ['mailgun', 'pepipost', 'postmark', 'sendgrid', 'sparkpost'];

    public function handleBounce(Request $request, $serviceName, $securityCode)
    {
        if ($securityCode != $this->getSecurityCode() || !in_array($serviceName, $this->validServices)) {
            return $this->getError();
        }

        $result = (new Webhook())->handle($serviceName, $request);

        return [
            'success' => 1,
            'message' => 'recorded',
            'service' => $serviceName,
            'result' => $result
        ];

    }

    private function getSecurityCode()
    {
        $code = fluentcrm_get_option('_fc_bounce_key');
        if (!$code) {
            $code = 'fcrm_' . substr(md5(wp_generate_uuid4()), 0, 14);
            fluentcrm_update_option('_fc_bounce_key', $code);
        }

        return $code;
    }

    private function getError()
    {
        return [
            'status'  => false,
            'message' => 'Invalid Data or Security Code'
        ];
    }
}
