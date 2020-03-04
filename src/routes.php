<?php

use App\Controller\DataController;
use App\Controller\GraphController;
use App\Controller\ReportController;

$app->get('/reports', [ReportController::class, 'index']);
$app->get('/reports/{report:[0-9]+}', [ReportController::class, 'report']);
$app->get('/reports/{report:[0-9]+}/suites/{suite:[0-9]+}', [ReportController::class, 'suite']);
$app->get('/hook/add', [ReportController::class, 'insert']);

$app->get('/graph', [GraphController::class, 'index']);
$app->get('/graph/parameters', [GraphController::class, 'parameters']);

$app->get('/data/badge', [DataController::class, 'badge']);

