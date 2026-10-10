<?php

declare(strict_types=1);

namespace GraphCommerce\FastBoot\Console;

use GraphCommerce\FastBootCache\Model\{Feature,PhpFiles,Release};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    public function __construct(private Feature $feature, private PhpFiles $files, private Release $release)
    {
        parent::__construct('fastboot:status');
    }
    protected function configure(): void
    {
        $this->setDescription('Check the FastBoot release identity, the private cache directory and the feature switches.');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = [];
        $release = $this->release->id();
        if (!is_string($release) || $release === '') {
            $errors[] = 'No static content deployment version. Run setup:static-content:deploy before fastboot:prepare.';
        }
        $directory = $this->files->namespaceDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            $errors[] = 'Cannot create the private FastBoot cache directory.';
        } elseif (!is_writable($directory)) {
            $errors[] = 'FastBoot cache directory is not writable by this CLI user; also check the FPM user.';
        }
        $output->writeln('Release: '.($release ?: '(missing)'));
        foreach (['schema_array','schema_scalars','cache_files','system_config_array','parsed_queries','validated_queries','area_config_diff','quote_without_connection','deploy_config_unchanged','scopes_cache','website_stores','default_store','view_config','guest_tax_factor','placeholder_url'] as $name) {
            $output->writeln($name.': '.($this->feature->on($name) ? 'enabled' : 'disabled'));
        }
        $output->writeln('OPcache must also be checked in the serving FPM pool; CLI settings do not describe its shared memory.');
        foreach ($errors as $error) {
            $output->writeln('<error>'.$error.'</error>');
        }
        return $errors ? Command::FAILURE : Command::SUCCESS;
    }
}
