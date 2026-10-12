<?php
if (PHP_SAPI !== 'cli') exit(1);
$root='/home/sigma-eg/htdocs/sigma-eg.net';
$release='/home/sigma-eg/git/customer-monthly-release-20261012';
$manifest=json_decode(file_get_contents($release.'/profile-payment-manifest.json'),true,512,JSON_THROW_ON_ERROR);
if (trim(file_get_contents($root.'/.deploy-current')) !== $manifest['parent']) throw new RuntimeException('Production changed');
$conflicts=[];
foreach ($manifest['files'] as $file) {
    if (str_contains($file,'..') || !preg_match('~\A(?:resources|public)/[a-zA-Z0-9_./-]+\z~',$file)) throw new RuntimeException('Invalid path');
    if (!hash_equals($manifest['baseHashes'][$file],hash_file('sha256',$root.'/'.$file))) $conflicts[]=$file;
}
if (($argv[1] ?? '') === 'check') { echo json_encode($conflicts,JSON_PRETTY_PRINT).PHP_EOL; exit; }
if ($conflicts) throw new RuntimeException('Server conflicts: '.implode(', ',$conflicts));
$process=proc_open(array_merge(['tar','-cf',$release.'/backup/profile-payment-before.tar','--'],$manifest['files']),[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes,$root);
if (!is_resource($process) || proc_close($process)!==0) throw new RuntimeException('Backup failed');
foreach ($manifest['files'] as $file) {
    if (!copy($release.'/correction/'.$file,$root.'/'.$file.'.payment-next')) throw new RuntimeException('Copy failed');
    chmod($root.'/'.$file.'.payment-next',0644);
    if (!rename($root.'/'.$file.'.payment-next',$root.'/'.$file)) throw new RuntimeException('Install failed');
}
file_put_contents($root.'/.deploy-current',$manifest['commit'].PHP_EOL);
echo 'Installed '.$manifest['commit'].PHP_EOL;
