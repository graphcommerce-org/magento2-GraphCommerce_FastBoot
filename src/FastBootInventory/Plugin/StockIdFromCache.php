<?php
declare(strict_types=1);

namespace GraphCommerce\FastBootInventory\Plugin;

use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The stock id of the current website as a cache entry, keyed by the website code the
 * resolver reads from the request's store.
 */
class StockIdFromCache
{
    private const SWITCH = 'stock_id';
    private const KEY = 'FASTBOOT_STOCK_ID_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Feature $feature,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request,
    ) {
    }

    public function aroundExecute(GetStockIdForCurrentWebsite $subject, callable $proceed): int
    {
        if (!$this->feature->on(self::SWITCH)) {
            return $proceed();
        }
        $store = $this->storeManager->getStore($this->request->getParam('store'));
        $key = self::KEY . $this->storeManager->getWebsite($store->getWebsiteId())->getCode();
        $cached = $this->cache->load($key);
        $entry = is_string($cached) ? json_decode($cached, true) : null;
        if (is_array($entry) && isset($entry['id'])) {
            return (int)$entry['id'];
        }
        $stockId = $proceed();
        $this->cache->save(json_encode(['id' => $stockId]), $key, [ConfigCache::CACHE_TAG, Tag::STOCK]);

        return $stockId;
    }
}
