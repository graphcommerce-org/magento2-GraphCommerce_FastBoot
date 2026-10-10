#!/usr/bin/env python3
"""Create a disposable public-source Magento fixture without private Composer credentials."""
import argparse
import json
import pathlib
import subprocess
import tempfile

parser = argparse.ArgumentParser()
parser.add_argument('--root', required=True)
parser.add_argument('--archive', help='Existing official Magento 2.4.8 source tarball')
args = parser.parse_args()
package = pathlib.Path(__file__).resolve().parents[2]
root = pathlib.Path(args.root).resolve()
if root == package or package in root.parents:
    parser.error('Fixture root must be outside the package to prevent recursive Composer copies')
if root.exists() and any(root.iterdir()):
    parser.error('Fixture root must be empty')
root.mkdir(parents=True, exist_ok=True)
with tempfile.TemporaryDirectory(prefix='fastboot-source-') as temporary:
    archive = pathlib.Path(args.archive).resolve() if args.archive else pathlib.Path(temporary) / 'magento.tar.gz'
    if not args.archive:
        subprocess.run(['curl', '--fail', '--location', '--retry', '3', '--output', str(archive),
                        'https://codeload.github.com/magento/magento2/tar.gz/refs/tags/2.4.8'], check=True)
    subprocess.run(['tar', '-xzf', str(archive), '--strip-components=1', '-C', str(root)], check=True)
manifest = root / 'composer.json'
config = json.loads(manifest.read_text())
config['require-dev'] = {'phpunit/phpunit': '^11.5'}
config['require']['graphcommerce/magento-fast-boot'] = 'dev-ci'
config['repositories'] = [{'type': 'path', 'url': str(package),
                           'options': {'symlink': False, 'versions': {'graphcommerce/magento-fast-boot': 'dev-ci'}}}]
config['config']['allow-plugins'] = False
# Resolve the same PHP-compatible dependency baseline in both runtime jobs.
config['config']['platform'] = {'php': '8.3.0'}
manifest.write_text(json.dumps(config, indent=4) + '\n')
(root / 'composer.lock').unlink(missing_ok=True)
subprocess.run(['composer', 'install', '--no-interaction', '--no-progress', '--no-plugins', '--no-scripts'], cwd=root, check=True)
# No business DB is needed for units or DI compilation.
modules = ['Magento_' + p.name for p in sorted((root / 'app/code/Magento').iterdir()) if (p / 'registration.php').is_file()]
modules += ['GraphCommerce_' + name for name in ['FastBootCache', 'FastBoot', 'FastBootGraphQl', 'FastBootPreload']]
(root / 'app/etc/config.php').write_text("<?php\nreturn ['modules' => [\n" + ''.join("    '" + m + "' => 1,\n" for m in modules) + "]];\n")
(root / 'app/etc/env.php').write_text("""<?php
return [
    'crypt' => ['key' => 'fastboot-disposable-ci-fixture'],
    'session' => ['save' => 'files'],
];
""")
(root/'pub/static').mkdir(parents=True, exist_ok=True)
(root/'pub/static/deployed_version.txt').write_text('ci-build')
print('Prepared disposable Magento 2.4.8 fixture at', root)
