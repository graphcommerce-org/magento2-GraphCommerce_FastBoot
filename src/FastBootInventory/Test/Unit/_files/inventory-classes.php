<?php
/**
 * The MSI classes the plugins act on, for a unit run without the inventory modules.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model {
    if (!class_exists(GetStockIdForCurrentWebsite::class)) {
        class GetStockIdForCurrentWebsite
        {
            public function execute(): int
            {
                return 0;
            }
        }
    }
}

namespace Magento\InventorySales\Model\ResourceModel {
    if (!class_exists(ReplaceSalesChannelsDataForStock::class)) {
        class ReplaceSalesChannelsDataForStock
        {
            public function execute(array $salesChannels, int $stockId): void
            {
            }
        }
    }
}
