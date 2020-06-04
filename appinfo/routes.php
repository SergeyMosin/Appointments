<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#caladd', 'url' => '/caladd', 'verb' => 'POST'],
        ['name' => 'page#calgetweek', 'url' => '/calgetweek', 'verb' => 'GET'],
        ['name' => 'page#callist', 'url' => '/callist', 'verb' => 'GET'],
        ['name' => 'page#state', 'url' => '/state', 'verb' => 'POST'],
        ['name' => 'page#formbase', 'url' => '/form', 'verb' => 'GET'],
        ['name' => 'page#formbasepost', 'url' => '/form', 'verb' => 'POST'],
        ['name' => 'page#help', 'url' => '/help', 'verb' => 'GET'],

        ['name' => 'page#form', 'url' => '/pub/{token}/form', 'verb' => 'GET'],
        ['name' => 'page#formpost', 'url' => '/pub/{token}/form', 'verb' => 'POST'],
        ['name' => 'page#cncf', 'url' => '/pub/{token}/cncf', 'verb' => 'GET'],

        ['name' => 'page#formemb', 'url' => '/embed/{token}/form', 'verb' => 'GET'],
        ['name' => 'page#formpostemb', 'url' => '/embed/{token}/form', 'verb' => 'POST'],
        ['name' => 'page#cncfemb', 'url' => '/embed/{token}/cncf', 'verb' => 'GET'],

    ]
];
