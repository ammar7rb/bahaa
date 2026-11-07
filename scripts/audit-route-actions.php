<?php

// Read-only controller-action audit. Run with the release's configured database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$missing = [];
$count = 0;
foreach ($app['router']->getRoutes() as $route) {
    $action = $route->getAction('controller');
    if (!$action || $route->getAction('uses') instanceof Closure) {
        continue;
    }
    $count++;
    [$controller, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');
    if (!class_exists($controller) || !method_exists($controller, $method)
        || !(new ReflectionMethod($controller, $method))->isPublic()) {
        $missing[] = implode('|', $route->methods()).' '.$route->uri().' => '.$action;
    }
}
echo 'Checked '.$count.' controller routes; missing actions: '.count($missing).PHP_EOL;
foreach ($missing as $line) {
    echo $line.PHP_EOL;
}
exit($missing ? 1 : 0);
