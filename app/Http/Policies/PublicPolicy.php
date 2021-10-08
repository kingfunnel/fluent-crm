<?php

namespace FluentCrm\App\Http\Policies;

use FluentCrm\Includes\Core\Policy;
use FluentCrm\Includes\Request\Request;

class PublicPolicy extends Policy
{
    /**
     * Public EndPoints for FluentCRM
     * @param  \FluentCrm\Includes\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return true;
    }
}
