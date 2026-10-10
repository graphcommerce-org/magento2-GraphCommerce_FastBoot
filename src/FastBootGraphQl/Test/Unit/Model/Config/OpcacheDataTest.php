<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootGraphQl\Test\Unit\Model\Config;

use GraphCommerce\FastBootCache\Model\{Feature, PhpFiles, Release, Version};
use GraphCommerce\FastBootGraphQl\Model\Config\OpcacheData;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\{CacheInterface, ReaderInterface};
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class OpcacheDataTest extends TestCase
{
    private const SCHEMA = ['types' => ['Query' => ['fields' => ['products' => ['type' => 'Products']]]]];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/fastboot-schema-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testFileHitReadsNeitherCacheNorReader(): void
    {
        $files = $this->createMock(PhpFiles::class);
        $files->method('read')->with('SCHEMA', 'schema')->willReturn(self::SCHEMA);
        $files->expects(self::never())->method('write');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method('load');
        $reader = $this->createMock(ReaderInterface::class);
        $reader->expects(self::never())->method('read');

        $data = new OpcacheData($reader, $cache, 'schema', $files, $this->feature(true), new Json());

        self::assertSame(self::SCHEMA, $data->get(null));
    }

    public function testMissRunsMagentoPathAndWritesUnderVersionCapturedBeforeTheBuild(): void
    {
        $captured = false;
        $files = $this->createMock(PhpFiles::class);
        $files->method('read')->willReturn(null);
        $files->expects(self::once())->method('generation')->willReturnCallback(function () use (&$captured) {
            $captured = true;
            return 'before-build';
        });
        $files->expects(self::once())->method('write')->with('SCHEMA', 'schema', self::SCHEMA, null, 'before-build');
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(function () use (&$captured) {
            self::assertTrue($captured);
            return false;
        });
        $cache->expects(self::once())->method('save')->with(json_encode(self::SCHEMA), 'schema');
        $reader = $this->createMock(ReaderInterface::class);
        $reader->expects(self::once())->method('read')->willReturn(self::SCHEMA);

        $data = new OpcacheData($reader, $cache, 'schema', $files, $this->feature(true), new Json());

        self::assertSame(self::SCHEMA, $data->get(null));
    }

    public function testMissFromConfigCacheWritesTheDecodedArray(): void
    {
        $files = $this->createMock(PhpFiles::class);
        $files->method('read')->willReturn(null);
        $files->method('generation')->willReturn('current');
        $files->expects(self::once())->method('write')->with('SCHEMA', 'schema', self::SCHEMA, null, 'current');
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode(self::SCHEMA));
        $reader = $this->createMock(ReaderInterface::class);
        $reader->expects(self::never())->method('read');

        $data = new OpcacheData($reader, $cache, 'schema', $files, $this->feature(true), new Json());

        self::assertSame(self::SCHEMA, $data->get(null));
    }

    public function testSwitchOffRunsMagentoPathOnly(): void
    {
        $this->assertMagentoPathOnly($this->feature(false));
    }

    public function testDisabledConfigCacheRunsMagentoPathOnly(): void
    {
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturn([]);
        $state = $this->createStub(StateInterface::class);
        $state->method('isEnabled')->willReturnCallback(static fn ($type) => $type !== 'config');

        $this->assertMagentoPathOnly(new Feature($config, $state));
    }

    public function testFileOfOneRequestServesTheNext(): void
    {
        $version = 'v1';
        $files = $this->files($version);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode(self::SCHEMA));
        new OpcacheData($this->createStub(ReaderInterface::class), $cache, 'schema', $files, $this->feature(true), new Json());

        $untouched = $this->createMock(CacheInterface::class);
        $untouched->expects(self::never())->method('load');
        $data = new OpcacheData($this->createStub(ReaderInterface::class), $untouched, 'schema', $this->files($version), $this->feature(true), new Json());

        self::assertSame(self::SCHEMA, $data->get(null));
    }

    public function testVersionChangeDuringTheBuildRefusesTheWrite(): void
    {
        $version = 'v1';
        $reader = $this->createStub(ReaderInterface::class);
        $reader->method('read')->willReturnCallback(function () use (&$version) {
            $version = 'v2';
            return self::SCHEMA;
        });
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);

        $data = new OpcacheData($reader, $cache, 'schema', $this->files($version), $this->feature(true), new Json());

        self::assertSame(self::SCHEMA, $data->get(null));
        self::assertNull($this->files($version)->read('SCHEMA', 'schema'));
        $version = 'v1';
        self::assertNull($this->files($version)->read('SCHEMA', 'schema'));
    }

    public function testSchemaOfOneAndAHalfMegabytesFitsTheDefaultLimits(): void
    {
        $schema = ['types' => []];
        foreach (range(1, 480) as $type) {
            $schema['types']['Type' . $type] = ['name' => 'Type' . $type, 'type' => 'graphql_type', 'fields' => array_fill_keys(array_map(static fn ($field) => 'field_' . $field, range(1, 20)), ['type' => 'String', 'required' => false, 'description' => 'A field of the schema'])];
        }
        self::assertGreaterThan(1572864, strlen(var_export($schema, true)));
        $version = 'v1';
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode($schema));
        new OpcacheData($this->createStub(ReaderInterface::class), $cache, 'schema', $this->files($version), $this->feature(true), new Json());

        self::assertSame($schema, $this->files($version)->read('SCHEMA', 'schema'));
    }

    private function assertMagentoPathOnly(Feature $feature): void
    {
        $files = $this->createMock(PhpFiles::class);
        $files->expects(self::never())->method('read');
        $files->expects(self::never())->method('write');
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode(self::SCHEMA));
        $reader = $this->createMock(ReaderInterface::class);
        $reader->expects(self::never())->method('read');

        $data = new OpcacheData($reader, $cache, 'schema', $files, $feature, new Json());

        self::assertSame(self::SCHEMA, $data->get(null));
    }

    private function feature(bool $on): Feature
    {
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn($on);
        return $feature;
    }

    /** Real local files of one release; `$version` is the token every read and write sees at that moment. */
    private function files(string &$version): PhpFiles
    {
        $directory = $this->createStub(ReadInterface::class);
        $directory->method('getAbsolutePath')->willReturn($this->root . '/');
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($directory);
        $token = $this->createStub(Version::class);
        $token->method('current')->willReturnCallback(static function () use (&$version) {
            return $version;
        });
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturnCallback(static fn ($key, $default = null) => $default);
        $release = $this->createStub(Release::class);
        $release->method('id')->willReturn('build');
        return new PhpFiles($filesystem, $token, $config, $release);
    }
}
