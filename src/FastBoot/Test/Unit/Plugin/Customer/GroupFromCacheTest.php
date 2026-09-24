<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Test\Unit\Plugin\Customer;

use GraphCommerce\FastBoot\Plugin\Customer\GroupFromCache;
use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Customer\Api\Data\GroupExtension;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../_files/customer-group-generated.php';

class GroupFromCacheTest extends TestCase
{
    private array $entries = [];

    private function plugin(): GroupFromCache
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn(string $id) => $this->entries[$id] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $id) {
            $this->entries[$id] = $data;

            return true;
        });
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn(true);
        $groups = $this->createStub(GroupInterfaceFactory::class);
        $groups->method('create')->willReturnCallback(fn(): GroupInterface => $this->group());
        $extensions = $this->createStub(ExtensionAttributesFactory::class);
        $extensions->method('create')->willReturnCallback(static fn(): GroupExtension => new GroupExtension());

        return new GroupFromCache($cache, $feature, $groups, $extensions);
    }

    /** A group data object that keeps what its setters receive. */
    private function group(): GroupInterface
    {
        return new class extends DataObject implements GroupInterface {
            public function getId() { return $this->getData('id'); }
            public function setId($id) { return $this->setData('id', $id); }
            public function getCode() { return $this->getData('code'); }
            public function setCode($code) { return $this->setData('code', $code); }
            public function getTaxClassId() { return $this->getData('tax_class_id'); }
            public function setTaxClassId($taxClassId) { return $this->setData('tax_class_id', $taxClassId); }
            public function getTaxClassName() { return $this->getData('tax_class_name'); }
            public function setTaxClassName($taxClassName) { return $this->setData('tax_class_name', $taxClassName); }
            public function getExtensionAttributes() { return $this->getData('extension_attributes'); }
            public function setExtensionAttributes(\Magento\Customer\Api\Data\GroupExtensionInterface $extensionAttributes) { return $this->setData('extension_attributes', $extensionAttributes); }
        };
    }

    public function testAGroupWithExcludedWebsitesComesBackWholeFromTheEntry(): void
    {
        $plugin = $this->plugin();
        $subject = $this->createStub(GroupRepositoryInterface::class);
        $calls = 0;
        $proceed = function () use (&$calls): GroupInterface {
            $calls++;
            $group = $this->group()->setId(3)->setCode('Wholesale')->setTaxClassId(5)->setTaxClassName('Retail Customer');

            return $group->setExtensionAttributes((new GroupExtension())->setExcludeWebsiteIds([2]));
        };
        $plugin->aroundGetById($subject, $proceed, 3);
        $group = $plugin->aroundGetById($subject, $proceed, 3);
        self::assertSame(1, $calls);
        self::assertSame(3, $group->getId());
        self::assertSame('Wholesale', $group->getCode());
        self::assertSame(5, $group->getTaxClassId());
        self::assertSame('Retail Customer', $group->getTaxClassName());
        self::assertSame([2], $group->getExtensionAttributes()->getExcludeWebsiteIds());
    }

    public function testAGroupWithoutExcludedWebsitesCarriesNoExtensionAttributes(): void
    {
        $plugin = $this->plugin();
        $subject = $this->createStub(GroupRepositoryInterface::class);
        $proceed = fn(): GroupInterface => $this->group()->setId(0)->setCode('NOT LOGGED IN')->setTaxClassId(3)->setTaxClassName('Retail Customer');
        $plugin->aroundGetById($subject, $proceed, 0);
        $group = $plugin->aroundGetById($subject, $proceed, 0);
        self::assertSame('NOT LOGGED IN', $group->getCode());
        self::assertNull($group->getExtensionAttributes());
    }
}
