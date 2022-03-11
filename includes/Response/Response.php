<?php

namespace FluentCrm\Includes\Response;

class Response
{
    protected $app = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function json($data = null, $code = 200)
    {
        wp_send_json($data, $code);
    }

    public function send($data = null, $code = 200)
    {
        if (defined('LSCWP_V')) {
            do_action('litespeed_control_set_nocache', 'FluentCRM api should not be cached');
        }

        return new \WP_REST_Response($data, $code);
    }

    public function sendSuccess($data = null, $code = 200)
    {
        return $this->send($data, $code);
    }

    public function sendError($data = null, $code = 423)
    {
        return $this->send($data, $code);
    }
}
