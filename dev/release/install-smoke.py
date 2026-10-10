#!/usr/bin/env python3
"""Prove the ZIP installs and registers through Composer against an existing Magento dependency tree."""
import argparse,pathlib,tempfile,json,subprocess,os,zipfile,hashlib
p=argparse.ArgumentParser();p.add_argument('--archive',required=True);p.add_argument('--magento-root',required=True);p.add_argument('--output',required=True);a=p.parse_args()
archive=pathlib.Path(a.archive).resolve();magento=pathlib.Path(a.magento_root).resolve();target=pathlib.Path(a.output).resolve();
if target.exists() and any(target.iterdir()):p.error('Installation smoke output must be empty')
target.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(archive)as z:
 manifest=json.loads(z.read('composer.json'));build=json.loads(z.read('BUILD-MANIFEST.json'))
 for name,digest in build['files'].items():assert hashlib.sha256(z.read(name)).hexdigest()==digest
assert all(not str(x).startswith(('dev/','app/etc/'))for x in build['files'])
php='''$root=$argv[1];$map=require $root.'/vendor/composer/autoload_psr4.php';foreach(array_keys($map)as$prefix)if(str_starts_with($prefix,'GraphCommerce\\\\FastBoot'))unset($map[$prefix]);echo json_encode($map,JSON_THROW_ON_ERROR);'''
psr4=json.loads(subprocess.check_output(['php','-r',php,str(magento)],text=True))
names=[name for name in manifest['require'] if name!='php' and not name.startswith('ext-')]
versions_php='require $argv[1]."/vendor/autoload.php";$versions=[];foreach(json_decode($argv[2],true)as$name)$versions[$name]=Composer\\InstalledVersions::getVersionRanges($name);echo json_encode($versions,JSON_THROW_ON_ERROR);'
replacements=json.loads(subprocess.check_output(['php','-r',versions_php,str(magento),json.dumps(names)],text=True))
project={'name':'fastboot/installation-smoke','type':'project','require':{manifest['name']:manifest['version']},'replace':replacements,'repositories':[{'type':'artifact','url':str(archive.parent)},{'packagist.org':False}],'autoload':{'psr-4':psr4},'config':{'allow-plugins':False}}
(target/'composer.json').write_text(json.dumps(project,indent=2)+'\n')
subprocess.run(['composer','install','--no-interaction','--no-scripts','--no-plugins','--no-progress'],cwd=target,check=True)
check='''require $argv[1].'/vendor/autoload.php';$base=$argv[1].'/vendor/graphcommerce/magento-fast-boot/';foreach(['FastBootCache','FastBoot','FastBootGraphQl','FastBootPreload']as$name){$path=(new Magento\\Framework\\Component\\ComponentRegistrar())->getPath('module','GraphCommerce_'.$name);if(realpath($path)!==realpath($base.'src/'.$name))throw new RuntimeException('Module registration mismatch');}foreach([GraphCommerce\\FastBootCache\\Model\\PhpFiles::class,GraphCommerce\\FastBoot\\Console\\PrepareCommand::class,GraphCommerce\\FastBootGraphQl\\Plugin\\Query\\ValidatedQueries::class,GraphCommerce\\FastBootPreload\\Model\\Dependencies::class]as$class){$r=new ReflectionClass($class);if(!str_starts_with(realpath($r->getFileName()),realpath($base)))throw new RuntimeException('Class escaped installed archive');}echo "PASS Composer artifact installation, four registrations, runtime autoload paths and build hashes\\n";'''
subprocess.run(['php','-r',check,str(target)],cwd=target,check=True)
