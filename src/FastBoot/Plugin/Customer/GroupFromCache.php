<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Customer;

use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;

/**
 * A customer group by id from the cache: its code, tax class and excluded websites, as the
 * repository and core's excluded-website plugin answer them. A group or tax class save
 * cleans the entries. The plugin wraps core's excluded-website plugin, so the entry holds
 * its result.
 */
class GroupFromCache
{
    private const SWITCH = 'customer_groups';
    private const KEY = 'FASTBOOT_GROUP_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Feature $feature,
        private readonly GroupInterfaceFactory $groupFactory,
        private readonly ExtensionAttributesFactory $extensionFactory,
    ) {
    }

    public function aroundGetById(GroupRepositoryInterface $subject, callable $proceed, $id): GroupInterface
    {
        if (!$this->feature->on(self::SWITCH)) {
            return $proceed($id);
        }
        $key = self::KEY . (int)$id;
        $cached = $this->cache->load($key);
        $data = is_string($cached) ? json_decode($cached, true) : null;
        if (is_array($data)) {
            $group = $this->groupFactory->create()
                ->setId($data['id'])
                ->setCode($data['code'])
                ->setTaxClassId($data['tax_class_id'])
                ->setTaxClassName($data['tax_class_name']);
            if ($data['exclude_website_ids']) {
                $group->setExtensionAttributes($this->extensionFactory->create(GroupInterface::class)->setExcludeWebsiteIds($data['exclude_website_ids']));
            }

            return $group;
        }
        $group = $proceed($id);
        $this->cache->save(json_encode([
            'id' => $group->getId(),
            'code' => $group->getCode(),
            'tax_class_id' => $group->getTaxClassId(),
            'tax_class_name' => $group->getTaxClassName(),
            'exclude_website_ids' => $group->getExtensionAttributes()?->getExcludeWebsiteIds() ?? [],
        ]), $key, [ConfigCache::CACHE_TAG, Tag::TAX]);

        return $group;
    }
}
