<?php
declare(strict_types=1);

namespace GraphCommerce\FastBootInventory\Test\Unit\Plugin;

use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use GraphCommerce\FastBootInventory\Plugin\ForgetStockIds;
use GraphCommerce\FastBootInventory\Plugin\StockIdFromCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventorySales\Model\ResourceModel\ReplaceSalesChannelsDataForStock;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../_files/inventory-classes.php';

class StockIdFromCacheTest extends TestCase
{
    private array $entries = [];
    private CacheInterface&MockObject $cache;

    private function cache(): CacheInterface&MockObject
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('load')->willReturnCallback(fn(string $id) => $this->entries[$id] ?? false);
        $this->cache->method('save')->willReturnCallback(function (string $data, string $id) {
            $this->entries[$id] = $data;

            return true;
        });
        $this->cache->method('clean')->willReturnCallback(function (array $tags) {
            $this->entries = in_array(Tag::STOCK, $tags, true) ? [] : $this->entries;

            return true;
        });

        return $this->cache;
    }

    private function plugin(): StockIdFromCache
    {
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn(true);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturn($website);

        return new StockIdFromCache($this->cache(), $feature, $storeManager, $this->createStub(RequestInterface::class));
    }

    public function testTheStockIdIsAnEntryUntilTheSalesChannelsChange(): void
    {
        $plugin = $this->plugin();
        $subject = $this->createStub(GetStockIdForCurrentWebsite::class);
        $calls = 0;
        $proceed = function () use (&$calls): int {
            $calls++;

            return 3;
        };

        $this->assertSame(3, $plugin->aroundExecute($subject, $proceed));
        $this->assertSame(3, $plugin->aroundExecute($subject, $proceed));
        $this->assertSame(1, $calls);
        $this->assertSame('{"id":3}', $this->entries['FASTBOOT_STOCK_ID_base']);

        (new ForgetStockIds($this->cache))->afterExecute($this->createStub(ReplaceSalesChannelsDataForStock::class), null);
        $plugin->aroundExecute($subject, $proceed);
        $this->assertSame(2, $calls);
    }
}
