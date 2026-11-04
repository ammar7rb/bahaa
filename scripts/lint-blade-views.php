<?php

// Compile and PHP-lint every project Blade template without rendering it.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$compiler = $app->make('blade.compiler');
$failures = [];
$count = 0;
foreach ([resource_path('views'), resource_path('themes'), base_path('Modules')] as $root) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')
            || preg_match('~[/\\\\](vendor|node_modules)[/\\\\]~', $file->getPathname())) continue;
        $count++;
        $temporary = tempnam(sys_get_temp_dir(), 'sigma-blade-');
        try {
            file_put_contents($temporary, $compiler->compileString(file_get_contents($file->getPathname())));
            $process = new Symfony\Component\Process\Process([PHP_BINARY, '-l', $temporary]);
            $process->run();
            if (!$process->isSuccessful()) {
                $failures[] = $file->getPathname().PHP_EOL.$process->getErrorOutput().$process->getOutput();
            }
        } catch (Throwable $exception) {
            $failures[] = $file->getPathname().': '.$exception->getMessage();
        } finally {
            unlink($temporary);
        }
    }
}
echo 'Blade templates checked: '.$count.'; failures: '.count($failures).PHP_EOL;
foreach ($failures as $failure) echo $failure.PHP_EOL;
exit($failures ? 1 : 0);
