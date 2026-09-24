<?php
declare(strict_types=1);

namespace GraphCommerce\FastBootCache\Model;

/**
 * The cache tags of the entries FastBoot keeps in the default frontend; a save of the
 * data behind a tag cleans it, which sweeps the local files.
 */
final class Tag
{
    public const TAX = 'FASTBOOT_TAX';
    public const CURRENCY = 'FASTBOOT_CURRENCY';
}
