<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Config;

use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Framework\App\Config;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The values a request read from the system configuration, by scope, scope code and path,
 * so a second read of the same value skips the scope resolution and the type lookup. A
 * clean of the configuration, a mutable set and a switch of the current store empty it.
 */
class ValueMemo implements ResetAfterRequestInterface
{
    private const SWITCH = 'config_memo';

    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(
        private readonly Feature $feature,
    ) {
    }

    /**
     * @param mixed $path
     * @param mixed $scope
     * @param mixed $scopeCode
     * @return mixed
     */
    public function aroundGetValue(Config $subject, callable $proceed, $path = null, $scope = 'default', $scopeCode = null)
    {
        if (($scopeCode !== null && !is_scalar($scopeCode)) || !$this->feature->on(self::SWITCH)) {
            return $proceed($path, $scope, $scopeCode);
        }
        $key = $scope . "\0" . ($scopeCode ?? '') . "\0" . $path;
        if (!array_key_exists($key, $this->values)) {
            $this->values[$key] = $proceed($path, $scope, $scopeCode);
        }

        return $this->values[$key];
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public function afterClean(Config $subject, $result)
    {
        $this->values = [];

        return $result;
    }

    public function forget(): void
    {
        $this->values = [];
    }

    public function _resetState(): void
    {
        $this->values = [];
    }
}
