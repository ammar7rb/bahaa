<?php
// Private CLI deployment helper. Never copy this file into the public site.
if (PHP_SAPI !== 'cli') exit(1);
$root = '/home/sigma-eg/htdocs/sigma-eg.net';
$release = '/home/sigma-eg/git/customer-monthly-release-20261012';
$manifest = json_decode(file_get_contents($release.'/monthly-release-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (realpath($root) !== $root || realpath($release) !== $release) throw new RuntimeException('Unexpected deployment paths');
foreach ($manifest['files'] as $file) {
    if (!preg_match('~\A(?:app|bootstrap|database|resources|routes|public)/[a-zA-Z0-9_./-]+\z~', $file) || str_contains($file, '..')) throw new RuntimeException('Invalid release path');
    if (!is_file($release.'/next/'.$file) || is_link($release.'/next/'.$file)) throw new RuntimeException('Missing or linked source: '.$file);
}
function command(array $arguments, string $cwd): void {
    $process = proc_open($arguments, [0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR], $pipes, $cwd);
    if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Deployment command failed');
}
if (($argv[1] ?? '') === 'preflight') {
    if (trim(file_get_contents($root.'/.deploy-current')) !== $manifest['parent']) throw new RuntimeException('Production revision changed');
    $conflicts = [];
    foreach ($manifest['baseHashes'] as $file => $hash) {
        if ($hash ? (!is_file($root.'/'.$file) || !hash_equals($hash,hash_file('sha256',$root.'/'.$file))) : file_exists($root.'/'.$file)) $conflicts[] = $file;
    }
    echo json_encode(['files'=>count($manifest['files']),'conflicts'=>$conflicts],JSON_PRETTY_PRINT).PHP_EOL;
    exit($conflicts ? 2 : 0);
}
if (($argv[1] ?? '') !== 'install') throw new RuntimeException('Unknown operation');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config('database.connections.'.config('database.default'));
$backup = $release.'/backup';
if (file_exists($backup)) throw new RuntimeException('Backup already exists; do not overwrite');
mkdir($backup,0700);
$existing = array_values(array_filter($manifest['files'],fn($file)=>is_file($root.'/'.$file)));
command(array_merge(['tar','-cf',$backup.'/code-before.tar','--'], $existing),$root);
$configPath = $backup.'/database-client.cnf';
$escape = fn($value) => '"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','\\n','\\r'],(string)$value).'"';
file_put_contents($configPath,"[client]\nuser=".$escape($connection['username'])."\npassword=".$escape($connection['password'])."\nhost=".$escape($connection['host'])."\nport=".(int)$connection['port']."\n");
chmod($configPath,0600);
$process = proc_open(['mysqldump','--defaults-extra-file='.$configPath,'--single-transaction','--no-tablespaces',$connection['database']],
    [0=>['file','/dev/null','r'],1=>['file',$backup.'/database-before.sql','w'],2=>['file',$backup.'/database-backup-errors.log','w']],$pipes,$root);
$exit = is_resource($process) ? proc_close($process) : 1;
unlink($configPath);
if ($exit !== 0 || filesize($backup.'/database-before.sql') === 0) throw new RuntimeException('Database backup failed; inspect private log');
chmod($backup.'/database-before.sql',0600);
command(['php','artisan','down','--retry=60'],$root);
try {
    foreach ($manifest['files'] as $file) if (str_starts_with($file,'database/migrations/2026_10_12_')) {
        command(['php','artisan','migrate','--force','--realpath','--path='.$release.'/next/'.$file],$root);
    }
    foreach ($manifest['files'] as $file) {
        $target = $root.'/'.$file;
        if (!is_dir(dirname($target))) mkdir(dirname($target),0755,true);
        if (is_link($target)) throw new RuntimeException('Linked deployment destination');
        if (!copy($release.'/next/'.$file, $target.'.monthly-next')) throw new RuntimeException('Copy failed: '.$file);
        chmod($target.'.monthly-next',0644);
        if (!rename($target.'.monthly-next',$target)) throw new RuntimeException('Install failed: '.$file);
    }
    command(['php','artisan','optimize:clear'],$root);
    command(['php','artisan','view:cache'],$root);
    command(['php','artisan','queue:restart'],$root);
    command(['php','artisan','up'],$root);
    file_put_contents($root.'/.deploy-current',$manifest['commit'].PHP_EOL);
    echo 'Installed '.$manifest['commit'].PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Release stopped with maintenance active. Preserve database history and repair forward: '.$error->getMessage().PHP_EOL);
    exit(1);
}
