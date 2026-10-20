<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Directory;

use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Checkout\CustomerData\DirectoryData;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\Type\Translate as TranslateCache;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The directory section from the cache, per store view, instead of the region and country selects
 * and a country name lookup per country on every request that renders it; a theme that inlines the
 * default section data, such as Hyvä, renders it on every page. Core keeps its region JSON under the
 * config tag the same way; the translate tag covers the translated region names.
 */
class DirectoryDataFromCache
{
    private const SWITCH = 'directory_data';
    private const KEY = 'FASTBOOT_DIRECTORY_DATA_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Feature $feature,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    public function aroundGetSectionData(DirectoryData $subject, callable $proceed): array
    {
        if (!$this->feature->on(self::SWITCH)) {
            return $proceed();
        }
        $key = self::KEY . (int)$this->storeManager->getStore()->getId();
        $cached = $this->cache->load($key);
        $data = is_string($cached) ? json_decode($cached, true) : null;
        if (is_array($data)) {
            return $data;
        }
        $data = $proceed();
        $this->cache->save(json_encode($data), $key, [ConfigCache::CACHE_TAG, TranslateCache::CACHE_TAG]);

        return $data;
    }
}
