<?php

// Operator-run only after approval. Never invoked by a deployment hook.
$root = realpath(getenv('SIGMA_APP_ROOT') ?: __DIR__.'/..');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$destination = $argv[1] ?? '';
if (!$destination || !str_starts_with($destination, '/') || file_exists($destination)) {
    throw new RuntimeException('Supply a new absolute backup directory outside the application.');
}
$parent = realpath(dirname($destination));
$site = realpath(base_path());
if (!$parent || $parent === $site || str_starts_with($parent, $site.DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Backup parent must exist outside the entire application directory.');
}
$config = config('database.connections.'.config('database.default'));
if (!in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true)) throw new RuntimeException('Expected the production MySQL/MariaDB connection.');
$finder = new Symfony\Component\Process\ExecutableFinder();
$dump = $finder->find('mariadb-dump') ?: $finder->find('mysqldump');
$tar = $finder->find('tar');
if (!$dump || !$tar) throw new RuntimeException('Database dump utility and tar must be installed before backup.');
if (!mkdir($destination, 0700)) throw new RuntimeException('Could not create private backup directory.');
$sqlPath = $destination.'/database.sql';
$handle = fopen($sqlPath, 'xb');
chmod($sqlPath, 0600);
try {
    $process = new Symfony\Component\Process\Process([
        $dump, '--single-transaction', '--routines', '--triggers', '--events', '--no-tablespaces',
        '--host='.(string) $config['host'], '--port='.(string) ($config['port'] ?? 3306),
        '--user='.(string) $config['username'], (string) $config['database'],
    ], base_path(), ['MYSQL_PWD' => (string) $config['password']]);
    $process->setTimeout(1800);
    $exit = $process->run(function ($type, $bytes) use ($handle) {
        if ($type === Symfony\Component\Process\Process::OUT && fwrite($handle, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Backup disk write failed.');
        }
    });
    if ($exit !== 0) throw new RuntimeException('Database dump failed. Do not continue activation; review database dump permissions.');
} finally {
    fclose($handle);
}
if (!filesize($sqlPath)) throw new RuntimeException('Empty database backup.');
$archive = $destination.'/environment-and-storage.tar.gz';
$process = new Symfony\Component\Process\Process([$tar, '--dereference', '-czf', $archive, '--', '.env', 'storage'], base_path());
$process->setTimeout(1800);
$process->mustRun();
chmod($archive, 0600);
$verify = new Symfony\Component\Process\Process([$tar, '-tzf', $archive]);
$verify->setTimeout(1800);
$verify->mustRun();
file_put_contents($destination.'/SHA256SUMS', hash_file('sha256', $sqlPath).'  database.sql'.PHP_EOL.hash_file('sha256', $archive).'  environment-and-storage.tar.gz'.PHP_EOL);
chmod($destination.'/SHA256SUMS', 0600);
echo 'Backup completed; archive readable and hashes recorded. An isolated database restore must be verified before activation.'.PHP_EOL;
