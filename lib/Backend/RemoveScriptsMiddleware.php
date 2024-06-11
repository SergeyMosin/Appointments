<?php

namespace OCA\Appointments\Backend;

use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;

class RemoveScriptsMiddleware extends Middleware
{

    private bool $removeNcScripts;

    public function afterController($controller, $methodName, Response $response)
    {

        if (isset($response->getHeaders()['X-Appointments'])) {
            $this->removeNcScripts = true;
            $response->addHeader('X-Appointments', null);
        } else {
            $this->removeNcScripts = false;
        }
        return $response;
    }

    public function beforeOutput($controller, $methodName, $output)
    {
        if ($this->removeNcScripts === true) {
            return preg_replace('/<script nonce="[^"]*?" defer src="(?:\/dist\/core-common|\/dist\/core-main|\/apps\/files_pdfviewer\/js\/files_pdfviewer-public)\.js[^<]*?<\/script>/', '', $output, 3);
        }
        return $output;
    }
}