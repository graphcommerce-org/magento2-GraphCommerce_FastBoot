<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootCache\Model;

use Magento\Framework\App\DeploymentConfig;

/**
 * The switches of the FastBoot modules, from the `fastboot` array of
 * app/etc/env.php (or config.php): every mechanism is on unless its name is
 * set to false there, so a deployment turns one off without a code change
 * and a bench measures one alone. Each mechanism names its own switch.
 */
class Feature
{
    private ?array $switches = null;
    private const CACHED = ['schema_array' => true,'cache_files' => true,'system_config_array' => true,'view_config' => true,'parsed_queries' => true,'validated_queries' => true,'scopes_cache' => true,'website_stores' => true,'deploy_config_unchanged' => true,'placeholder_url' => true,'guest_tax_factor' => true,'tax_rates' => true,'customer_groups' => true,'currency_rates' => true,'stock_id' => true];

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
        private readonly ?\Magento\Framework\App\Cache\StateInterface $cacheState = null,
    ) {
    }

    public function on(string $name): bool
    {
        $this->switches ??= (array)$this->deploymentConfig->get('fastboot', []);

        return ($this->switches[$name] ?? true) !== false
            && (!isset(self::CACHED[$name]) || $this->cacheState === null || $this->cacheState->isEnabled('config'));
    }
}
