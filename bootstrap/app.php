<?php

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use LaravelZero\Framework\Application;
use Psr\Log\NullLogger;

// Bundled dependencies raise deprecations on newer PHP versions, which must never reach stdout/stderr or crash the
// server: mask them before and after the framework error handler (which resets error_reporting) and give it a logger.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$app = Application::configure(basePath: dirname(__DIR__))->create();

if (!$app->bound('log')) {
    $app->instance('log', new NullLogger);
}

$app->afterBootstrapping(HandleExceptions::class, function ($app) {
    if (!$app->runningUnitTests()) {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    }
});

return $app;
