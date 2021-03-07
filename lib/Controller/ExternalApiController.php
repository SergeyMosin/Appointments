<?php

/**
 * @copyright Copyright (c) 2021, Thomas Hackländer
 * @author Thomas Hackländer <thomas.hacklaender@iftm.dem>
 * @version 2021-02-21
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\Appointments\Controller;

use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Controller\Errors;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class ExternalApiController extends OCSController {

    private $config;
    private $utils;

    public function __construct($AppName,
                                IRequest $request,
                                IConfig $config,
                                BackendUtils $utils){
        parent::__construct($AppName, $request, 'POST, GET');

        $this->config=$config;
        $this->utils=$utils;
    }

    /**
     * Requests URLs of a physician identified by its Nextcloud ID.
     * 
     * Returns:
     * $urls = [
     *     [
     *         'userid': 'jdoe',
     *         'pageid': p0',
     *         'label': 'Chat page',
     *         'link': 'http://name.domain//index.php/apps/appointments/pub/fLpthOb5fYdBgOY01/form',
     *         'embed': 'http://name.domain//index.php/apps/appointments/embed/fLpthOb5fYdBgOY01/form',
     *     ],
     *     ....
     *     [
     *         'userid': 'jdoe',
     *         'pageid': p5',
     *         ....
     *     ],
     * ];
     * 
     * @param string $userid the (Nexctcloud) ID of the user whoes URLs should be returned.
     * @param string $pageid optional. Restrict the result to the page with specified ID.
     * @param string $label optional. Restrict the result to the pages with specified label.
     * @return array
     */
    public function getPageUrl($userid, $pageid, $label) {
         
        $pgs=$this->utils->getUserSettings(BackendUtils::KEY_PAGES, $userid);

        // The public base address of the returned pages
        $pubWebBase = $this->utils->getPublicWebBase();
       
        // Start with an empty return array
        $urls = array();

        foreach ($pgs as $pid => $pg) {

            // Return enabled pages only
            if (! $pg['enabled']) continue;

            // Test for requested pageid
            if (! empty($pageid) && ($pageid !== $pid)) continue;

            // Test for requested label
            if (! empty($label) && ($label !== $pg['label'])) continue;

            // Get the token of the ID
            $tkn = $this->utils->getToken($userid, $pid);

            $url = array();
            $url['userid'] = $userid;
            $url['pageid'] = $pid;
            $url['label'] = $pg['label'];
            $url['link'] = $pubWebBase . '/' . $this->utils->pubPrx($tkn, false) . 'form';
            $url['embed'] = $pubWebBase . '/' . $this->utils->pubPrx($tkn, true) . 'form';

            $urls[] = $url;
        }

        $response = new DataResponse();
        $response->setData($urls);

        // HPPT ststus codes: https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
        if (empty($urls)) {
            // 202 - successfull, but no result
            $response->setStatus(202);
        } else {
            // 200 - successfull
            $response->setStatus(200);
        }
 
        return $response;
    }

    /**
     * Requests directory URL of a physician identified by its Nextcloud ID.
     * 
     * @param string $userid the (Nexctcloud) ID of the user whoes URL should be returned.
     * @return string the url
     */
    public function getDirUrl($userid) {

        $gos = 'substr';
        $c = $this->config->getUserValue($userid, $this->appName, 'cnk');
        $go = 'hexdec';
        $dir = $this->utils->getUserSettings(BackendUtils::KEY_DIR, $userid);

        // The public base address of the returned pages
        $pubWebBase = $this->utils->getPublicWebBase();

        $response = new DataResponse();

        if (count($dir) === 0) {
            $response->setData('');
            // 202 - successfull, but no result
            $response->setStatus(202);
            return $response;

        } else if (empty($c) || (($go($gos($c, 0, 0b100)) >> 15) & 0b1) !== (($go($gos($c,0b100,4))>>0xC) & 0b1)) {
            $response->setData('');
            // 202 - successfull, but no result
            $response->setStatus(202);
            return $response;
        }

        // "p0" will return only the encoded username
        $response->setData($pubWebBase . '/pub/' . $this->utils->getToken($userid, "p0") . '/dir');
        // 200 - successfull
        $response->setStatus(200);

        return $response;
    }

}