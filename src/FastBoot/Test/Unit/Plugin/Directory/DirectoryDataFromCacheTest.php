<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Test\Unit\Plugin\Directory;

use GraphCommerce\FastBoot\Plugin\Directory\DirectoryDataFromCache;
use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Checkout\CustomerData\DirectoryData;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class DirectoryDataFromCacheTest extends TestCase
{
    private array $entries = [];
    private array $tags = [];
    private int $storeId = 1;

    private function plugin(bool $on = true): DirectoryDataFromCache
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn(string $id) => $this->entries[$id] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $id, array $tags) {
            $this->entries[$id] = $data;
            $this->tags[$id] = $tags;

            return true;
        });
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn($on);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturnCallback(fn() => $this->storeId);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new DirectoryDataFromCache($cache, $feature, $stores);
    }

    public function testTheSectionIsComputedOncePerStoreView(): void
    {
        $plugin = $this->plugin();
        $subject = $this->createStub(DirectoryData::class);
        $calls = 0;
        $proceed = function () use (&$calls) {
            $calls++;

            return ['NL' => ['name' => 'Netherlands' . $calls, 'regions' => [12 => ['code' => 'NH', 'name' => 'Noord-Holland']]]];
        };

        $first = $plugin->aroundGetSectionData($subject, $proceed);
        self::assertSame($first, $plugin->aroundGetSectionData($subject, $proceed));
        self::assertSame(1, $calls);
        self::assertSame('Noord-Holland', $first['NL']['regions'][12]['name']);
        self::assertSame(['CONFIG', 'TRANSLATE'], $this->tags['FASTBOOT_DIRECTORY_DATA_1']);

        $this->storeId = 2;
        self::assertSame('Netherlands2', $plugin->aroundGetSectionData($subject, $proceed)['NL']['name']);
        self::assertSame(2, $calls);
    }

    public function testTheSwitchOffComputesEveryTime(): void
    {
        $plugin = $this->plugin(false);
        $calls = 0;
        $proceed = function () use (&$calls) {
            $calls++;

            return [];
        };
        $plugin->aroundGetSectionData($this->createStub(DirectoryData::class), $proceed);
        $plugin->aroundGetSectionData($this->createStub(DirectoryData::class), $proceed);

        self::assertSame(2, $calls);
        self::assertSame([], $this->entries);
    }
}
