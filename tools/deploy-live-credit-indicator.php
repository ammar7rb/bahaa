<?php
if (PHP_SAPI !== 'cli') exit(1);
$root='/home/sigma-eg/htdocs/sigma-eg.net';
$release='/home/sigma-eg/git/customer-monthly-release-20261012';
$manifest=json_decode(file_get_contents($release.'/live-credit-manifest.json'),true,512,JSON_THROW_ON_ERROR);
if (trim(file_get_contents($root.'/.deploy-current')) !== $manifest['parent']) throw new RuntimeException('Production changed');
foreach ($manifest['files'] as $file) {
    if (str_contains($file,'..') || !preg_match('~\A(?:app|routes|resources|public)/[a-zA-Z0-9_./-]+\z~',$file)) throw new RuntimeException('Invalid path');
    if ($manifest['baseHashes'][$file] === null) {
        if (file_exists($root.'/'.$file)) throw new RuntimeException('Unexpected new target');
        continue;
    }
    if (!hash_equals($manifest['baseHashes'][$file],hash_file('sha256',$root.'/'.$file))) {
        // Prior deployment copied Windows newlines. Accept only identical normalized contents.
        $baseline=file_get_contents($release.'/live-credit-base/'.$file);
        if (!hash_equals($manifest['baseHashes'][$file],hash('sha256',str_replace("\r\n","\n",$baseline))) || str_replace("\r\n","\n",$baseline)!==str_replace("\r\n","\n",file_get_contents($root.'/'.$file))) throw new RuntimeException('Server conflict: '.$file);
    }
}
if (($argv[1] ?? '')==='check') { echo "Preflight passed\n";exit; }
$process=proc_open(array_merge(['tar','-cf',$release.'/backup/live-credit-before.tar','--'],array_values(array_filter($manifest['files'],fn($file)=>$manifest['baseHashes'][$file]!==null))),[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes,$root);
if (!is_resource($process)||proc_close($process)!==0) throw new RuntimeException('Backup failed');
foreach ($manifest['files'] as $file) {
    if (!copy($release.'/live-credit/'.$file,$root.'/'.$file.'.credit-next')) throw new RuntimeException('Copy failed');
    chmod($root.'/'.$file.'.credit-next',0644);
    if (!rename($root.'/'.$file.'.credit-next',$root.'/'.$file)) throw new RuntimeException('Install failed');
}
file_put_contents($root.'/.deploy-current',$manifest['commit'].PHP_EOL);
echo 'Installed '.$manifest['commit'].PHP_EOL;
