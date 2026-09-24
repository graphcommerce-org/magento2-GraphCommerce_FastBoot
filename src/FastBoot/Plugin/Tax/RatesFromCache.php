<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Tax;

use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\ResourceModel\Calculation;

/**
 * The tax rate and the applied rates of a rate request from the cache, keyed as core's
 * calculation keys its own per-request memory: store, product and customer tax class,
 * country, region and postcode. A tax rule, rate or class save cleans the entries. A
 * signed-in customer's request carries the customer's own address, one entry per
 * address, so it stays with core's per-request memory.
 */
class RatesFromCache
{
    private const SWITCH = 'tax_rates';
    private const KEY = 'FASTBOOT_TAX_RATE_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Feature $feature,
        private readonly StoreManagerInterface $storeManager,
        private readonly UserContextInterface $userContext,
    ) {
    }

    public function aroundGetRateInfo(Calculation $subject, callable $proceed, $request): array
    {
        return $this->entry('info', $request, static fn(): array => $proceed($request));
    }

    public function aroundGetCalculationProcess(Calculation $subject, callable $proceed, $request, $rates = null): array
    {
        if ($rates !== null) {
            return $proceed($request, $rates);
        }

        return $this->entry('process', $request, static fn(): array => $proceed($request));
    }

    private function entry(string $kind, $request, callable $compute): array
    {
        if (!$this->feature->on(self::SWITCH) || (int)$this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER) {
            return $compute();
        }
        $key = self::KEY . $kind . '_' . md5(implode('|', [
            $this->storeManager->getStore($request->getStore())->getId(),
            implode(',', (array)$request->getProductClassId()),
            $request->getCustomerClassId(),
            $request->getCountryId(),
            $request->getRegionId(),
            $request->getPostcode(),
        ]));
        $cached = $this->cache->load($key);
        if (is_string($cached)) {
            $value = json_decode($cached, true);
            if (is_array($value)) {
                return $value;
            }
        }
        $value = $compute();
        $this->cache->save(json_encode($value, JSON_PRESERVE_ZERO_FRACTION), $key, [ConfigCache::CACHE_TAG, Tag::TAX]);

        return $value;
    }
}
