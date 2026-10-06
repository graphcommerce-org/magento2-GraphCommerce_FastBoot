<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Test\Unit\Plugin\Store;

use GraphCommerce\FastBoot\Plugin\Store\ScopesCache;
use GraphCommerce\FastBoot\Plugin\Store\WebsiteStores;
use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\App\Config\Source\RuntimeConfigSource;
use Magento\Store\Model\Group;
use Magento\Store\Model\ResourceModel\StoreWebsiteRelation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreResolver;
use Magento\Store\Model\Website;
use PHPUnit\Framework\TestCase;

/**
 * A new website, group or store runs the store manager's reinit before anything reads
 * the scopes again. The reinit cleans the store tags through the config cache type,
 * which only drops entries that also carry the config type's tag; an entry without it
 * survives, and the scopes reloaded right after come back without the new website.
 */
class StoreReinitTest extends TestCase
{
    /** @var array<string, array{data: string, tags: string[]}> */
    private array $entries = [];

    private function cache(): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn(string $id) => $this->entries[$id]['data'] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $id, array $tags = []) {
            $this->entries[$id] = ['data' => $data, 'tags' => $tags];

            return true;
        });

        return $cache;
    }

    private function feature(): Feature
    {
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn(true);

        return $feature;
    }

    /** StoreManager::reinitStores(): a matching-any-tag clean through the config type's tag scope. */
    private function reinitStores(): void
    {
        foreach ([StoreResolver::CACHE_TAG, Store::CACHE_TAG, Website::CACHE_TAG, Group::CACHE_TAG] as $tag) {
            foreach ($this->entries as $id => $entry) {
                if (in_array($tag, $entry['tags'], true) && in_array(ConfigCache::CACHE_TAG, $entry['tags'], true)) {
                    unset($this->entries[$id]);
                }
            }
        }
    }

    public function testTheScopesReadAfterANewWebsiteComeFromTheTables(): void
    {
        $plugin = new ScopesCache($this->cache(), new Json(), $this->feature());
        $subject = $this->createStub(RuntimeConfigSource::class);
        $tables = ['websites' => ['base' => ['website_id' => '1', 'code' => 'base']]];
        $proceed = static function () use (&$tables): array {
            return $tables;
        };

        $plugin->aroundGet($subject, $proceed);
        $tables['websites']['wands'] = ['website_id' => '5', 'code' => 'wands'];
        $this->reinitStores();

        self::assertArrayHasKey('wands', $plugin->aroundGet($subject, $proceed)['websites']);
    }

    public function testTheStoresOfAWebsiteReadAfterANewStoreComeFromTheTables(): void
    {
        $plugin = new WebsiteStores($this->cache(), new Json(), $this->feature());
        $subject = $this->createStub(StoreWebsiteRelation::class);
        $stores = [['store_id' => '1', 'code' => 'default']];
        $proceed = static function () use (&$stores): array {
            return $stores;
        };

        $plugin->aroundGetWebsiteStores($subject, $proceed, 1);
        $stores[] = ['store_id' => '2', 'code' => 'second'];
        $this->reinitStores();

        self::assertCount(2, $plugin->aroundGetWebsiteStores($subject, $proceed, 1));
    }
}
