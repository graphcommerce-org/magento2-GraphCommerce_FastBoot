<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootCache\Test\Unit\Model;

use GraphCommerce\FastBootCache\Model\{Release,PhpFiles,Version};
use Magento\Framework\App\{DeploymentConfig,Filesystem\DirectoryList};
use Magento\Framework\Filesystem;
use PHPUnit\Framework\TestCase;

class ReleaseTest extends TestCase
{
    public function testDeploymentChangesIsolateLocalDataWithoutAnExplicitId(): void
    {
        $root = sys_get_temp_dir().'/fastboot-release-'.bin2hex(random_bytes(8));
        mkdir($root.'/static', 0700, true);
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturnCallback(static fn ($key, $default = null) => match ($key) {
            'fastboot/release' => 'ignored-legacy-build',
            'cache/frontend/default' => ['backend_options' => ['server' => '127.0.0.1']],
            default => $default,
        });
        $dirs = new DirectoryList($root, [DirectoryList::STATIC_VIEW => ['path' => $root.'/static'], DirectoryList::CACHE => ['path' => $root.'/private-cache']]);
        $version = $this->createStub(Version::class);
        $version->method('current')->willReturn('unchanged-cache-generation');
        $read = $this->createStub(\Magento\Framework\Filesystem\Directory\ReadInterface::class);
        $read->method('getAbsolutePath')->willReturn($root.'/private-cache/');
        $fs = $this->createStub(Filesystem::class);
        $fs->method('getDirectoryRead')->willReturnCallback(static function ($code) use ($read) {
            self::assertSame(DirectoryList::CACHE, $code);
            return $read;
        });
        $files = fn ($release) => new PhpFiles($fs, $version, $config, $release);
        try {
            $missing = new Release($dirs);
            self::assertNull($missing->id());
            $files($missing)->write('CONFIG', 'example', 'must-not-be-published');
            self::assertNull($files($missing)->read('CONFIG', 'example'));
            file_put_contents($root.'/static/deployed_version.txt', "build-one\n");
            $first = new Release($dirs);
            self::assertSame('build-one', $first->id());
            $files($first)->write('CONFIG', 'example', 'old-build');
            self::assertSame('old-build', $files($first)->read('CONFIG', 'example'));
            self::assertStringStartsWith($root.'/private-cache/fastboot/', $files($first)->namespaceDirectory());
            file_put_contents($root.'/static/deployed_version.txt', 'build-two');
            $second = new Release($dirs);
            self::assertSame('build-one', $first->id()); // In-flight request keeps its build snapshot.
            self::assertSame('build-two', $second->id());
            self::assertNull($files($second)->read('CONFIG', 'example'));
            self::assertNotSame($files($first)->namespaceDirectory(), $files($second)->namespaceDirectory());
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
