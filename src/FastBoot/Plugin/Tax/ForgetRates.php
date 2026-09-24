<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Tax;

use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Framework\App\CacheInterface;

/**
 * A tax rule, rate or class save or delete, and a customer group save or delete, drop the
 * tax entries: the rates, the groups and the guest tax factor.
 */
class ForgetRates
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function afterSave($subject, $result)
    {
        $this->cache->clean([Tag::TAX]);

        return $result;
    }

    public function afterDelete($subject, $result)
    {
        $this->cache->clean([Tag::TAX]);

        return $result;
    }
}
