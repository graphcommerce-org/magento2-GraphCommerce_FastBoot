<?php
declare(strict_types=1);

namespace GraphCommerce\FastBootGraphQl\Plugin\CacheId;

use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use Magento\CustomerGraphQl\CacheIdFactorProviders\CustomerTaxRateProvider;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * The guest tax rate factor of the response cache id per store and customer
 * group from the cache. Core loads the group, its tax class, its excluded
 * websites and the tax rates for it on every response. The entry follows the
 * config tag, for the tax defaults, and the tax tag that a tax rate, rule,
 * class or customer group save cleans (see ForgetTaxFactors). A signed-in
 * customer's factor follows the customer's own address and is computed.
 */
class GuestTaxFactor
{
    private const SWITCH = 'guest_tax_factor';

    public const TAG = Tag::TAX;

    private const KEY = 'FASTBOOT_TAX_FACTOR_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Feature $feature,
    ) {
    }

    public function aroundGetFactorValue(CustomerTaxRateProvider $subject, callable $proceed, ContextInterface $context): string
    {
        $attributes = $context->getExtensionAttributes();
        if ($attributes->getIsCustomer() || !$this->feature->on(self::SWITCH)) {
            return $proceed($context);
        }
        $key = self::KEY . $attributes->getStore()->getId() . '_' . ($attributes->getCustomerGroupId() ?? 0);
        $factor = $this->cache->load($key);
        if ($factor === false) {
            $factor = $proceed($context);
            $this->cache->save($factor, $key, [ConfigCache::CACHE_TAG, self::TAG]);
        }

        return (string)$factor;
    }
}
