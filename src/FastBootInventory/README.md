# GraphCommerce_FastBootInventory

The stock id of the current website from the cache. Core resolves it with two selects on every
request that loads a product collection.

[`Plugin/StockIdFromCache`](Plugin/StockIdFromCache.php) answers
`Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite::execute()` from the entry
`FASTBOOT_STOCK_ID_<website code>`, under the `stock_id` switch.
[`Plugin/ForgetStockIds`](Plugin/ForgetStockIds.php) cleans the entries after a write of a
stock's sales channel links.
