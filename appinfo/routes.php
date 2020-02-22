<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\AptGo\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#caladd', 'url' => '/caladd', 'verb' => 'POST'],
        ['name' => 'page#callist', 'url' => '/callist', 'verb' => 'GET'],
        ['name' => 'page#state', 'url' => '/state', 'verb' => 'POST'],
        ['name' => 'page#formbase', 'url' => '/form', 'verb' => 'GET'],
        ['name' => 'page#formbasepost', 'url' => '/form', 'verb' => 'POST'],
        ['name' => 'page#help', 'url' => '/help', 'verb' => 'GET'],

        ['name' => 'page#form', 'url' => '/pub/{token}/form', 'verb' => 'GET'],
        ['name' => 'page#formpost', 'url' => '/pub/{token}/form', 'verb' => 'POST'],
        ['name' => 'page#cncf', 'url' => '/pub/{token}/cncf', 'verb' => 'GET'],
    ]
];
