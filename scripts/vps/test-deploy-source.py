#!/usr/bin/env python3
"""Isolated deploy regression tests: disposable Git repositories, no live application."""
import importlib.util,pathlib,subprocess,tempfile,unittest
spec=importlib.util.spec_from_file_location('deploy_source',pathlib.Path(__file__).with_name('deploy-source.py'));deploy=importlib.util.module_from_spec(spec);spec.loader.exec_module(deploy)
class DeployTests(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();root=pathlib.Path(self.temp.name);self.site=root/'site';self.site.mkdir();self.repo=root/'repo.git';self.work=root/'work';self.work.mkdir();self.events=[]
  self.call('git','init','--bare',str(self.repo));self.call('git','init','-b','main',str(self.work));self.call('git','-C',str(self.work),'config','user.name','Test');self.call('git','-C',str(self.work),'config','user.email','test@example.invalid')
  (self.work/'index.php').write_text('<?php echo "one";');self.old=self.commit('initial');(self.site/'index.php').write_text('<?php echo "one";');(self.site/'.deploy-current').write_text(self.old);(self.site/'storage/framework').mkdir(parents=True)
  deploy.REPO=self.repo;deploy.SITE=self.site;deploy.STATE=root/'backups';deploy.artisan=self.artisan;self.original_run=deploy.run
  def run(*args,cwd=None):
   if args[0]=='curl':return b'200'
   return subprocess.check_output(args,cwd=cwd or self.site,stderr=subprocess.STDOUT)
  deploy.run=run
 def tearDown(self):deploy.run=self.original_run;self.temp.cleanup()
 def call(self,*args):return subprocess.check_output(args,stderr=subprocess.STDOUT).decode().strip()
 def commit(self,message):
  self.call('git','-C',str(self.work),'add','.');self.call('git','-C',str(self.work),'commit','-m',message);rev=self.call('git','-C',str(self.work),'rev-parse','HEAD');self.call('git','-C',str(self.work),'push',str(self.repo),'main');return rev
 def artisan(self,*args):self.events.append(args[0]);return b''
 def next(self):
  (self.work/'index.php').write_text('<?php echo "two";');return self.commit('update')
 def test_success_verifies_source_and_commit(self):
  target=self.next();result=deploy.deploy(target);self.assertEqual(target,(self.site/'.deploy-current').read_text().strip());self.assertEqual(1,result['verified_source_files']);self.assertIn('two',(self.site/'index.php').read_text());self.assertIn('up',self.events)
 def test_render_failure_restores_source_and_marker(self):
  target=self.next();failed=[False]
  def artisan(*args):
   self.events.append(args[0])
   if args[0]=='view:cache' and not failed[0]:failed[0]=True;raise RuntimeError('synthetic rendering failure')
   return b''
  deploy.artisan=artisan
  with self.assertRaisesRegex(RuntimeError,'synthetic'):deploy.deploy(target)
  self.assertEqual(self.old,(self.site/'.deploy-current').read_text());self.assertIn('one',(self.site/'index.php').read_text());self.assertIn('up',self.events)
 def test_drift_blocks_before_maintenance(self):
  target=self.next();(self.site/'index.php').write_text('<?php echo "drift";')
  with self.assertRaisesRegex(RuntimeError,'Source differs'):deploy.deploy(target)
  self.assertEqual([],self.events)
 def test_migration_requires_separate_release(self):
  d=self.work/'database/migrations';d.mkdir(parents=True);(d/'new.php').write_text('<?php');target=self.commit('migration')
  with self.assertRaisesRegex(RuntimeError,'Migrations require'):deploy.deploy(target)
  self.assertEqual([],self.events)
if __name__=='__main__':unittest.main()
