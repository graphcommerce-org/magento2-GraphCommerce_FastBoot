<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Directory;

use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Directory\Model\Currency as CurrencyModel;
use Magento\Directory\Model\ResourceModel\Currency;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;

/**
 * A currency rate from the cache instead of a select per request; a rate import cleans the
 * entries. A missing rate is an entry too, as core answers false for it.
 */
class CurrencyRatesFromCache
{
    private const SWITCH = 'currency_rates';
    private const KEY = 'FASTBOOT_CURRENCY_RATE_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Feature $feature,
    ) {
    }

    public function aroundGetRate(Currency $subject, callable $proceed, $currencyFrom, $currencyTo)
    {
        return $this->rate('rate', $currencyFrom, $currencyTo, static fn() => $proceed($currencyFrom, $currencyTo));
    }

    public function aroundGetAnyRate(Currency $subject, callable $proceed, $currencyFrom, $currencyTo)
    {
        return $this->rate('any', $currencyFrom, $currencyTo, static fn() => $proceed($currencyFrom, $currencyTo));
    }

    public function afterSaveRates(Currency $subject, $result)
    {
        $this->cache->clean([Tag::CURRENCY]);

        return $result;
    }

    private function rate(string $kind, $currencyFrom, $currencyTo, callable $compute)
    {
        $from = strtoupper($currencyFrom instanceof CurrencyModel ? (string)$currencyFrom->getCode() : (string)$currencyFrom);
        $to = strtoupper($currencyTo instanceof CurrencyModel ? (string)$currencyTo->getCode() : (string)$currencyTo);
        if ($from === $to || !$this->feature->on(self::SWITCH)) {
            return $compute();
        }
        $key = self::KEY . $kind . '_' . $from . '_' . $to;
        $cached = $this->cache->load($key);
        $entry = is_string($cached) ? json_decode($cached, true) : null;
        if (is_array($entry) && array_key_exists('rate', $entry)) {
            return $entry['rate'];
        }
        $rate = $compute();
        $this->cache->save(json_encode(['rate' => $rate], JSON_PRESERVE_ZERO_FRACTION), $key, [ConfigCache::CACHE_TAG, Tag::CURRENCY]);

        return $rate;
    }
}
