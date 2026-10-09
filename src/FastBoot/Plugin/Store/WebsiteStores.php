<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Store;

use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\Group;
use Magento\Store\Model\ResourceModel\StoreWebsiteRelation;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;

/**
 * The stores of a website from the cache: the storeConfig resolver asks for
 * them on every request with a three-table select. The entry follows the
 * store tags, which a store, group or website save cleans, and carries the
 * config type's tag so the store manager's reinit, which cleans the store
 * tags through the config type, drops it too.
 */
class WebsiteStores
{
    private const SWITCH = 'website_stores';

    private const KEY = 'FASTBOOT_WEBSITE_STORES_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly Feature $feature,
    ) {
    }

    public function aroundGetWebsiteStores(
        StoreWebsiteRelation $subject,
        callable $proceed,
        int $websiteId,
        bool $available = false,
        ?int $storeGroupId = null,
        ?int $storeId = null
    ): array {
        if (!$this->feature->on(self::SWITCH)) {
            return $proceed($websiteId, $available, $storeGroupId, $storeId);
        }
        $key = self::KEY . $websiteId . '_' . (int)$available . '_' . (int)$storeGroupId . '_' . (int)$storeId;
        $cached = $this->cache->load($key);
        if ($cached) {
            return $this->serializer->unserialize($cached);
        }
        $stores = $proceed($websiteId, $available, $storeGroupId, $storeId);
        $this->cache->save(
            $this->serializer->serialize($stores),
            $key,
            [ConfigCache::CACHE_TAG, Store::CACHE_TAG, Website::CACHE_TAG, Group::CACHE_TAG]
        );

        return $stores;
    }
}
