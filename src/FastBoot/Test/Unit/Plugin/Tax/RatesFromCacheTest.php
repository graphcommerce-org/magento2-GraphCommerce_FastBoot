<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Test\Unit\Plugin\Tax;

use GraphCommerce\FastBoot\Plugin\Tax\RatesFromCache;
use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\ResourceModel\Calculation;
use PHPUnit\Framework\TestCase;

class RatesFromCacheTest extends TestCase
{
    /** @var array<string, string> */
    private array $entries = [];
    private array $tags = [];

    private function plugin(bool $on = true): RatesFromCache
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn(string $id) => $this->entries[$id] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $id, array $tags) {
            $this->entries[$id] = $data;
            $this->tags[$id] = $tags;

            return true;
        });
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn($on);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new RatesFromCache($cache, $feature, $stores);
    }

    private function request(string $country = 'NL', int $productClass = 2): DataObject
    {
        return new DataObject(['store' => 1, 'product_class_id' => $productClass, 'customer_class_id' => 3, 'country_id' => $country, 'region_id' => 0, 'postcode' => '*']);
    }

    public function testTheRateInfoOfARequestKeyIsComputedOnceAndTaggedForTaxSaves(): void
    {
        $plugin = $this->plugin();
        $subject = $this->createStub(Calculation::class);
        $calls = 0;
        $proceed = function () use (&$calls): array {
            $calls++;

            return ['process' => [['id' => 'NL', 'percent' => 21.0, 'rates' => [['code' => 'NL', 'title' => 'BTW', 'percent' => 21.0, 'position' => 1, 'priority' => 1]]]], 'value' => 21.0];
        };
        $first = $plugin->aroundGetRateInfo($subject, $proceed, $this->request());
        $second = $plugin->aroundGetRateInfo($subject, $proceed, $this->request());
        self::assertSame(1, $calls);
        self::assertSame($first, $second);
        self::assertSame(21.0, $second['value']);
        self::assertContains(Tag::TAX, $this->tags[array_key_first($this->tags)]);
        $plugin->aroundGetRateInfo($subject, $proceed, $this->request('DE'));
        self::assertSame(2, $calls, 'Another destination is another entry');
    }

    public function testAProcessWithGivenRatesAndAnOffSwitchBypassTheCache(): void
    {
        $subject = $this->createStub(Calculation::class);
        $calls = 0;
        $proceed = function () use (&$calls): array {
            $calls++;

            return [];
        };
        $plugin = $this->plugin();
        $plugin->aroundGetCalculationProcess($subject, $proceed, $this->request(), [['code' => 'x']]);
        $plugin->aroundGetCalculationProcess($subject, $proceed, $this->request(), [['code' => 'x']]);
        self::assertSame(2, $calls);
        self::assertSame([], $this->entries);
        $off = $this->plugin(false);
        $off->aroundGetCalculationProcess($subject, $proceed, $this->request());
        $off->aroundGetCalculationProcess($subject, $proceed, $this->request());
        self::assertSame(4, $calls);
    }
}
