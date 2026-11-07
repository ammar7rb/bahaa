#!/usr/bin/env python3
"""Deploy repository main, preserving private/runtime data; no implicit migrations."""
import contextlib,fcntl,hashlib,json,os,pathlib,shutil,subprocess,sys,tarfile,tempfile,time
REPO=pathlib.Path('/home/sigma-eg/git/sigma-site.git');SITE=pathlib.Path('/home/sigma-eg/htdocs/sigma-eg.net');STATE=pathlib.Path('/home/sigma-eg/git/source-deployments')
def run(*args,cwd=None):return subprocess.check_output(args,cwd=cwd or SITE,stderr=subprocess.STDOUT)
def git(*args):return run('git','--git-dir='+str(REPO),*args)
def protected(p):
 name=pathlib.PurePosixPath(p).name.lower()
 return p.startswith(('storage/','vendor/','node_modules/','bootstrap/cache/','public/storage/','.well-known/')) or name.startswith('.env') and name!='.env.example' or name.endswith(('.key','.pem','.p12','.pfx','.sql','.log','.sqlite')) or 'apple-authkey' in name or p.startswith('.deploy-')
def normalized(data):return data if b'\0' in data else data.replace(b'\r\n',b'\n')
def verify(rev):
 paths=[p for p in git('ls-tree','-r','--name-only','-z',rev).decode().rstrip('\0').split('\0') if not protected(p)]
 process=subprocess.Popen(['git','--git-dir='+str(REPO),'cat-file','--batch'],stdin=subprocess.PIPE,stdout=subprocess.PIPE)
 mismatches=[]
 try:
  for p in paths:
   process.stdin.write((rev+':'+p+'\n').encode());process.stdin.flush();header=process.stdout.readline().split();size=int(header[-1]);expected=process.stdout.read(size);process.stdout.read(1)
   f=SITE/p
   if not f.is_file() or normalized(f.read_bytes())!=normalized(expected):mismatches.append(p)
 finally:process.stdin.close();process.stdout.close();process.wait()
 if mismatches:raise RuntimeError('Source differs from deployed commit: '+', '.join(mismatches[:12]))
 return len(paths)
def artisan(*args):return run('php8.3','artisan',*args)
def deploy(target):
 if not all(c in '0123456789abcdef' for c in target) or len(target)!=40:raise RuntimeError('Invalid revision')
 if git('rev-parse','refs/heads/main').decode().strip()!=target:raise RuntimeError('Target is not current main')
 current=(SITE/'.deploy-current').read_text().strip();git('merge-base','--is-ancestor',current,target)
 if (SITE/'storage/framework/down').exists():raise RuntimeError('Site already in maintenance')
 verified=verify(current)
 if target==current:return {'commit':target,'verified_source_files':verified,'already_current':True}
 changes=git('diff','--name-only','-z',current,target).decode().rstrip('\0').split('\0')
 if any(protected(p) for p in changes):raise RuntimeError('Protected runtime path in commit')
 if any(p.startswith('database/migrations/') for p in changes):raise RuntimeError('Migrations require a separately reviewed schema release')
 if any(p in ['composer.json','composer.lock'] for p in changes):raise RuntimeError('Dependency changes require a separately reviewed dependency release')
 STATE.mkdir(mode=0o700,exist_ok=True);release=STATE/(target+'-'+str(int(time.time())));release.mkdir(mode=0o700);stage=release/'stage';backup=release/'backup';stage.mkdir();backup.mkdir()
 archive=git('archive',target);archivefile=release/'source.tar';archivefile.write_bytes(archive)
 with tarfile.open(archivefile) as tar:
  for member in tar.getmembers():
   if member.issym() or member.islnk() or pathlib.PurePosixPath(member.name).is_absolute() or '..' in pathlib.PurePosixPath(member.name).parts:raise RuntimeError('Unsafe source archive entry')
  tar.extractall(stage,filter='data')
 for p in changes:
  f=stage/p
  if f.is_file() and p.endswith('.php'):run('php8.3','-l',str(f))
 existed=[]
 for p in changes:
  f=SITE/p
  if f.is_file():dest=backup/p;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(f,dest);existed.append(p)
 (release/'previous.json').write_text(json.dumps({'commit':current,'files':existed,'changes':changes},indent=2))
 copied=False;down=False
 try:
  artisan('down','--retry=15');down=True;verify(current);copied=True
  for p in changes:
   f=stage/p;dest=SITE/p
   if f.is_file():
    dest.parent.mkdir(parents=True,exist_ok=True);tmp=dest.with_name(dest.name+'.deploy-next');shutil.copy2(f,tmp);os.chmod(tmp,0o755 if p.endswith('.sh') else 0o644);os.replace(tmp,dest)
   elif dest.is_file():dest.unlink()
  artisan('optimize:clear');artisan('view:cache');count=verify(target);artisan('queue:restart');artisan('up');down=False
  for url in ['https://sigma-eg.net/','https://sigma-eg.net/api/v1/config']:
   status=run('curl','--max-time','30','-sS','-o','/dev/null','-w','%{http_code}',url).decode()
   if status!='200':raise RuntimeError('Health check failed: '+url+' HTTP '+status)
  marker=SITE/'.deploy-current.next';marker.write_text(target+'\n');os.replace(marker,SITE/'.deploy-current')
  result={'commit':target,'previous':current,'verified_source_files':count,'changed_files':changes,'deployed_at_utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'health':'200','schema_changes':False};(release/'verification.json').write_text(json.dumps(result,indent=2));(REPO/'last-source-deploy.json').write_text(json.dumps(result,indent=2));return result
 except BaseException:
  if copied:
   for p in changes:
    dest=SITE/p
    if p in existed:shutil.copy2(backup/p,dest)
    elif dest.is_file():dest.unlink()
   with contextlib.suppress(Exception):artisan('optimize:clear');artisan('view:cache')
  raise
 finally:
  if down or (SITE/'storage/framework/down').exists():artisan('up')
if __name__=='__main__':
 try:
  with open(REPO/'deploy.lock','a') as lock:
   fcntl.flock(lock,fcntl.LOCK_EX)
   if sys.argv[1]=='verify':result={'commit':sys.argv[2],'verified_source_files':verify(sys.argv[2])}
   else:result=deploy(sys.argv[1])
  print(json.dumps(result))
 except Exception as e:print(str(e),file=sys.stderr);sys.exit(1)
