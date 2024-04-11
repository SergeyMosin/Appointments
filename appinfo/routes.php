<?php

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#caladd', 'url' => '/caladd', 'verb' => 'POST'],
		['name' => 'page#formbase', 'url' => '/form', 'verb' => 'GET'],
		['name' => 'page#formbasepost', 'url' => '/form', 'verb' => 'POST'],

		['name' => 'page#form', 'url' => '/pub/{token}/form', 'verb' => 'GET'],
		['name' => 'page#formpost', 'url' => '/pub/{token}/form', 'verb' => 'POST'],
		['name' => 'page#cncf', 'url' => '/pub/{token}/cncf', 'verb' => 'GET'],

		['name' => 'page#formemb', 'url' => '/embed/{token}/form', 'verb' => 'GET'],
		['name' => 'page#formpostemb', 'url' => '/embed/{token}/form', 'verb' => 'POST'],
		['name' => 'page#cncfemb', 'url' => '/embed/{token}/cncf', 'verb' => 'GET'],

		['name' => 'state#index', 'url' => '/state', 'verb' => 'POST'],

		['name' => 'calendars#calgetweek', 'url' => '/calgetweek', 'verb' => 'POST'],
		['name' => 'calendars#callist', 'url' => '/callist', 'verb' => 'GET'],

        ['name' => 'dir#index', 'url' => '/dir/{token}', 'verb' => 'GET'],
		['name' => 'dir#indexbase', 'url' => '/dir', 'verb' => 'GET'],
        ['name' => 'dir#indexv1', 'url' => '/pub/{token}/dir', 'verb' => 'GET'], # v1 legacy

		['name' => 'debug#index', 'url' => '/debug', 'verb' => 'POST'],
	]
];
