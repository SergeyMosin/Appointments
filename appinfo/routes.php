<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#caladd', 'url' => '/caladd', 'verb' => 'POST'],
        ['name' => 'page#formbase', 'url' => '/form', 'verb' => 'GET'],
        ['name' => 'page#formbasepost', 'url' => '/form', 'verb' => 'POST'],
        ['name' => 'page#help', 'url' => '/help', 'verb' => 'GET'],

        ['name' => 'page#form', 'url' => '/pub/{token}/form', 'verb' => 'GET'],
        ['name' => 'page#formpost', 'url' => '/pub/{token}/form', 'verb' => 'POST'],
        ['name' => 'page#cncf', 'url' => '/pub/{token}/cncf', 'verb' => 'GET'],

        ['name' => 'page#formemb', 'url' => '/embed/{token}/form', 'verb' => 'GET'],
        ['name' => 'page#formpostemb', 'url' => '/embed/{token}/form', 'verb' => 'POST'],
        ['name' => 'page#cncfemb', 'url' => '/embed/{token}/cncf', 'verb' => 'GET'],

        ['name' => 'state#index', 'url' => '/state', 'verb' => 'POST'],

        ['name' => 'calendars#calgetweek', 'url' => '/calgetweek', 'verb' => 'POST'],
        ['name' => 'calendars#callist', 'url' => '/callist', 'verb' => 'GET'],

        ['name' => 'dir#index', 'url' => '/pub/{token}/dir', 'verb' => 'GET'],
        ['name' => 'dir#indexbase', 'url' => '/dir', 'verb' => 'GET'],
        
        ['name' => 'Debug#settingsDump', 'url' => '/settings_dump', 'verb' => 'GET'],
    ],

    // ExternalApiController
    'ocs' => [
        // GET, http://domain/ocs/v2.php/apps/appointments/api/v1/pageurl
        // @param userid = {string} (Nexctcloud) ID of the user whoes URL should be returned
        // @param pagid = {string} Optional. Restrict the result to the page with specified ID.
        // @param label = {string} Optional. Restrict the result to the pages with specified label.
        // @param format = json
        // @status 200 - successfull
        // @status 202 - successfull, but no result
        ['name' => 'external_api#get_page_url', 'url' => '/api/v1/pageurl', 'verb' => 'GET'],
    ]
];
