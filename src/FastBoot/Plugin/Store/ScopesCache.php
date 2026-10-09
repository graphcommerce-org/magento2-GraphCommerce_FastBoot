<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Store;

use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\App\Config\Source\RuntimeConfigSource;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;

/**
 * The websites, groups and stores from the cache instead of three selects
 * on every request. The entry carries the config type's tag next to the
 * store tags: the store manager's reinit, which a new website, group or store
 * runs before anything reads the scopes again, cleans the store tags through
 * the config type, and that clean only drops entries that carry its tag. A
 * shop that dumped its scopes into config.php never reads this.
 */
class ScopesCache
{
    private const SWITCH = 'scopes_cache';

    private const KEY = 'FASTBOOT_SCOPES';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly Feature $feature,
    ) {
    }

    public function aroundGet(RuntimeConfigSource $subject, callable $proceed, $path = '')
    {
        if (!$this->feature->on(self::SWITCH)) {
            return $proceed($path);
        }
        $cached = $this->cache->load(self::KEY);
        if ($cached) {
            return $this->serializer->unserialize($cached);
        }
        $data = $proceed($path);
        if ($data) {
            $this->cache->save(
                $this->serializer->serialize($data),
                self::KEY,
                [ConfigCache::CACHE_TAG, Store::CACHE_TAG, Website::CACHE_TAG, Group::CACHE_TAG]
            );
        }

        return $data;
    }
}
