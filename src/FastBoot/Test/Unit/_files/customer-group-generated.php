<?php
/**
 * The generated customer group classes, for a unit run without Magento's generated code.
 */
declare(strict_types=1);

namespace Magento\Customer\Api\Data;

if (!interface_exists(GroupExtensionInterface::class)) {
    interface GroupExtensionInterface extends \Magento\Framework\Api\ExtensionAttributesInterface
    {
        public function getExcludeWebsiteIds();

        public function setExcludeWebsiteIds($excludeWebsiteIds);
    }

    class GroupExtension extends \Magento\Framework\Api\AbstractSimpleObject implements GroupExtensionInterface
    {
        public function getExcludeWebsiteIds()
        {
            return $this->_get('exclude_website_ids');
        }

        public function setExcludeWebsiteIds($excludeWebsiteIds)
        {
            return $this->setData('exclude_website_ids', $excludeWebsiteIds);
        }
    }
}

if (!class_exists(GroupInterfaceFactory::class)) {
    class GroupInterfaceFactory
    {
        public function create(array $data = []): GroupInterface
        {
            throw new \LogicException('The test stubs the factory');
        }
    }
}
