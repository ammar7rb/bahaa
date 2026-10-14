<?php
if(PHP_SAPI !== 'cli') exit(1);
$root='/home/sigma-eg/htdocs/sigma-eg.net';
$release='/home/sigma-eg/git/customer-stage7910-20261014';
$manifest=json_decode(file_get_contents($release.'/backend-manifest.json'),true,512,JSON_THROW_ON_ERROR);
$normalized=fn($s)=>preg_replace('/^\xEF\xBB\xBF/','',str_replace("\r\n","\n",$s));
$conflicts=[];
foreach($manifest['deployFiles'] as $file){
 if(str_contains($file,'..') || !preg_match('~\A(?:app|routes|resources|database)/[a-zA-Z0-9_./-]+\z~',$file)) throw new RuntimeException('Invalid path');
 $live=is_file($root.'/'.$file)?hash('sha256',$normalized(file_get_contents($root.'/'.$file))):null;
 if($live!==$manifest['baseHashes'][$file] && $live!==$manifest['hashes'][$file]) $conflicts[]=$file;
 if(hash('sha256',$normalized(file_get_contents($release.'/source/'.$file)))!==$manifest['hashes'][$file]) throw new RuntimeException('Source mismatch');
}
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=Illuminate\Support\Facades\Schema::class;
$revision=trim(file_get_contents($root.'/.deploy-current'));
$checks=['revision'=>$revision,'conflicts'=>$conflicts,
 'deletion_schema'=>$schema::hasColumn('users','account_deleted_at') && $schema::hasTable('customer_deletion_flows'),
 'page_translations'=>$schema::hasColumn('business_pages','translations'),
 'existing_veterinary_pages'=>Illuminate\Support\Facades\DB::table('business_pages')->where('slug','veterinary-services')->orWhereIn('title',['خدمات بيطرية','الخدمات البيطرية','Veterinary Services'])->get(['id','title','slug','status'])];
if(($argv[1]??'')==='check'){echo json_encode($checks,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($conflicts?1:0);}
if($conflicts || $revision!==$manifest['parent'] || !$checks['deletion_schema'] || !$checks['page_translations']) throw new RuntimeException('Preflight not satisfied');
$run=function(array $command,string $cwd) {
 $p=proc_open($command,[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes,$cwd);
 if(!is_resource($p) || proc_close($p)!==0) throw new RuntimeException('Deployment step failed: '.implode(' ',$command));
};
if(($argv[1]??'')==='backup'){
 if(!is_dir($release.'/backup')) mkdir($release.'/backup',0700,true);
 $existing=array_values(array_filter($manifest['deployFiles'],fn($f)=>is_file($root.'/'.$f)));
 $run(array_merge(['tar','-cf',$release.'/backup/code-before.tar','--'],$existing),$root);
 foreach($existing as $file){$target=$release.'/backup/files/'.$file;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);if(!copy($root.'/'.$file,$target))throw new RuntimeException('Backup copy failed');}
 $db=config('database.connections.'.config('database.default'));
 if(($db['driver']??'')!=='mysql') throw new RuntimeException('Unexpected DB driver');
 $quote=fn($s)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$s).'"';
 $credentials=$release.'/backup/dump-options.cnf';
 file_put_contents($credentials,"[client]\nuser=".$quote($db['username'])."\npassword=".$quote($db['password'])."\nhost=".$quote($db['host'])."\nport=".(int)$db['port']."\n");chmod($credentials,0600);
 try{$run(['mysqldump','--defaults-extra-file='.$credentials,'--single-transaction','--skip-lock-tables','--no-tablespaces','--result-file='.$release.'/backup/database-before.sql',$db['database']],$root);}finally{unlink($credentials);}
 chmod($release.'/backup/database-before.sql',0600);
 echo "Code and database backup completed.\n";exit;
}
if(($argv[1]??'')!=='install') throw new RuntimeException('Use check, backup, install');
if(!is_file($release.'/backup/database-before.sql') || filesize($release.'/backup/database-before.sql')===0) throw new RuntimeException('Missing backup');
$run(['php8.3','artisan','down','--retry=60'],$root);
try{
 $migration='database/migrations/2026_10_14_200000_add_veterinary_services_business_page.php';
 if(!copy($release.'/source/'.$migration,$root.'/'.$migration))throw new RuntimeException('Migration copy failed');
 $run(['php8.3','artisan','migrate','--path='.$migration,'--force','--no-interaction'],$root);
 foreach($manifest['deployFiles'] as $file){
  $target=$root.'/'.$file;if(!is_dir(dirname($target)))mkdir(dirname($target),0755,true);
  if(!copy($release.'/source/'.$file,$target.'.phases-next'))throw new RuntimeException('Copy failed');
  chmod($target.'.phases-next',0644);if(!rename($target.'.phases-next',$target))throw new RuntimeException('Install failed');
 }
 $run(['php8.3','artisan','optimize:clear'],$root);
 $run(['php8.3','artisan','view:cache'],$root);
 $run(['php8.3','artisan','queue:restart'],$root);
 file_put_contents($root.'/.deploy-current',$manifest['commit'].PHP_EOL);
}catch(Throwable $error){
 foreach($manifest['deployFiles'] as $file)if(is_file($release.'/backup/files/'.$file))copy($release.'/backup/files/'.$file,$root.'/'.$file);
 file_put_contents($root.'/.deploy-current',$manifest['parent'].PHP_EOL);
 $run(['php8.3','artisan','optimize:clear'],$root);
 throw $error;
}finally{$run(['php8.3','artisan','up'],$root);}
echo 'Installed '.$manifest['commit'].PHP_EOL;
