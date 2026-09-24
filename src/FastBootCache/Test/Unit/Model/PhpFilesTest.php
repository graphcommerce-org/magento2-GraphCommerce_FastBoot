<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootCache\Test\Unit\Model;

use GraphCommerce\FastBootCache\Model\{PhpFiles,Version,Release};
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use PHPUnit\Framework\TestCase;

class PhpFilesTest extends TestCase
{
    private string $var;
    protected function setUp(): void
    {
        $this->var = sys_get_temp_dir().'/fastboot-test-'.bin2hex(random_bytes(8));
        mkdir($this->var);
    }
    protected function tearDown(): void
    {
        $this->remove($this->var);
    }
    private function remove(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $p) {
            is_dir($p) ? $this->remove($p) : unlink($p);
        }@rmdir($dir);
    }
    private function files(string $version, array $options = []): PhpFiles
    {
        $directory = $this->createStub(ReadInterface::class);
        $directory->method('getAbsolutePath')->willReturn($this->var.'/');
        $fs = $this->createStub(Filesystem::class);
        $fs->method('getDirectoryRead')->willReturn($directory);
        $v = $this->createStub(Version::class);
        $v->method('current')->willReturn($version);
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturnCallback(fn ($key, $default = null) => $key === 'fastboot/files' ? $options : $default);
        $release = $this->createStub(Release::class);
        $release->method('id')->willReturn('test-release');
        return new PhpFiles($fs, $v, $config, $release);
    }
    public function testVersionsAreIsolatedButIdenticalPayloadReusesOneBlob(): void
    {
        $a = $this->files('a');
        $b = $this->files('b');
        $a->write('CONFIG', 'a/b', ['old' => true]);
        self::assertNull($b->read('CONFIG', 'a/b'));
        $b->write('CONFIG', 'a/b', ['old' => true]);
        self::assertCount(1, glob($a->namespaceDirectory().'/blobs/*.php'));
        self::assertSame(['old' => true], $b->read('CONFIG', 'a/b'));
        $b->write('CONFIG', 'a_b', 'different');
        self::assertSame(['old' => true], $b->read('CONFIG', 'a/b'));
        self::assertSame('different', $b->read('CONFIG', 'a_b'));
        $b->sweep();
        self::assertNull($b->read('CONFIG', 'a/b'));
        self::assertNull($this->files('a')->read('CONFIG', 'a/b'), 'A sweep comes with a version bump, so another process reads under a new path');
    }
    public function testPublicationGenerationAndExpiryAreRespected(): void
    {
        $f = $this->files('new');
        $f->write('CONFIG', 'a', 'stale', null, 'old');
        self::assertNull($f->read('CONFIG', 'a'));
        $f->write('CONFIG', 'a', 'zero', 0);
        self::assertNull($f->read('CONFIG', 'a'));
        $f->write('CONFIG', 'a', false, 1);
        self::assertFalse($f->read('CONFIG', 'a'));
        usleep(1050000);
        self::assertNull($f->read('CONFIG', 'a'));
    }
    public function testAdmissionIsBoundedAndObjectsAreNeverExported(): void
    {
        $f = $this->files('v', ['max_queries' => 2]);
        foreach (['a','b','c'] as $id) {
            $f->write('PARSED', $id, ['query' => $id]);
        }
        self::assertNull($f->read('PARSED', 'c'));
        self::assertCount(2, glob($f->namespaceDirectory().'/blobs/*.php'));
        $f->write('CONFIG', 'object', new \stdClass());
        self::assertNull($f->read('CONFIG', 'object'));
    }

    public function testAProcessAnswersAnEntryFromMemoryUntilItExpiresOrIsRemoved(): void
    {
        $files = $this->files('v1');
        $files->write('G', 'kept', ['a' => 1]);
        $files->write('G', 'brief', ['b' => 2], 1);
        $this->remove($this->var.'/fastboot');
        self::assertSame(['a' => 1], $files->read('G', 'kept'), 'The memory of this process answers after the files are gone');
        self::assertSame(['b' => 2], $files->read('G', 'brief'));
        usleep(1100000);
        self::assertNull($files->read('G', 'brief'), 'An expired entry leaves the memory');
        $files->remove('G', 'kept');
        self::assertNull($files->read('G', 'kept'));
        self::assertNull($this->files('v1')->read('G', 'kept'), 'Another process reads the files');
    }
}
