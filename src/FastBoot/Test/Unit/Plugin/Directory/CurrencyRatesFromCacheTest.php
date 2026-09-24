<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Test\Unit\Plugin\Directory;

use GraphCommerce\FastBoot\Plugin\Directory\CurrencyRatesFromCache;
use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Directory\Model\ResourceModel\Currency;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CurrencyRatesFromCacheTest extends TestCase
{
    private array $entries = [];
    private CacheInterface&MockObject $cache;

    private function plugin(): CurrencyRatesFromCache
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('load')->willReturnCallback(fn(string $id) => $this->entries[$id] ?? false);
        $this->cache->method('save')->willReturnCallback(function (string $data, string $id) {
            $this->entries[$id] = $data;

            return true;
        });
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn(true);

        return new CurrencyRatesFromCache($this->cache, $feature);
    }

    public function testARateAndAMissingRateAreEntriesAndARateImportCleansThem(): void
    {
        $plugin = $this->plugin();
        $subject = $this->createStub(Currency::class);
        $calls = 0;
        $proceed = function () use (&$calls) {
            $calls++;

            return $calls === 1 ? '0.8500' : false;
        };
        self::assertSame('0.8500', $plugin->aroundGetRate($subject, $proceed, 'EUR', 'usd'));
        self::assertSame('0.8500', $plugin->aroundGetRate($subject, $proceed, 'eur', 'USD'));
        self::assertFalse($plugin->aroundGetAnyRate($subject, $proceed, 'EUR', 'GBP'));
        self::assertFalse($plugin->aroundGetAnyRate($subject, $proceed, 'EUR', 'GBP'));
        self::assertSame(2, $calls);
        self::assertSame(1, $plugin->aroundGetRate($subject, static fn() => 1, 'EUR', 'EUR'));
        self::assertCount(2, $this->entries);
        $this->cache->expects(self::once())->method('clean')->with([Tag::CURRENCY]);
        $plugin->afterSaveRates($subject, null);
    }
}
