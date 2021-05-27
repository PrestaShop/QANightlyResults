<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;

require __DIR__ . '/vendor/autoload.php';

$app = Bridge::create();

require __DIR__ . '/src/routes.php';
loadRoutes($app);

require __DIR__ . '/src/settings.php';
require __DIR__ . '/src/database.php';
