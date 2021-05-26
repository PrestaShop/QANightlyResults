<?php

use DI\Bridge\Slim\Bridge;
use Slim\Handlers\ErrorHandler;

require __DIR__ . '/../vendor/autoload.php';

$app = Bridge::create();

/*
 * Load routes
 */
require __DIR__ . '/../src/routes.php';

loadRoutes($app);
$app->addRoutingMiddleware();

/*
 * Load settings
 */
require __DIR__ . '/../src/settings.php';
require __DIR__ . '/../src/database.php';

// Add Error Middleware
$display_errors = false;
if (getenv('QANB_ENV') === 'staging' || getenv('QANB_ENV') === 'testing') {
    $display_errors = true;
}
$errorMiddleware = $app->addErrorMiddleware($display_errors, $display_errors, $display_errors);
/** @var ErrorHandler */
$defaultHandler = $errorMiddleware->getDefaultErrorHandler();
$defaultHandler->forceContentType('application/json');

$app->run();
