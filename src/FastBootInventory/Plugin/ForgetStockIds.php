<?php
declare(strict_types=1);

namespace GraphCommerce\FastBootInventory\Plugin;

use GraphCommerce\FastBootCache\Model\Tag;
use Magento\Framework\App\CacheInterface;

/**
 * Cleans the stock id entries after a write of the sales channel links of a stock.
 */
class ForgetStockIds
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param object $subject
     * @param mixed $result
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(object $subject, $result)
    {
        $this->cache->clean([Tag::STOCK]);

        return $result;
    }
}
