<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootGraphQl\Model\Config;

use GraphCommerce\FastBootCache\Model\Feature;
use GraphCommerce\FastBootCache\Model\PhpFiles;
use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\Data;
use Magento\Framework\Config\ReaderInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * The stitched GraphQL schema as a PHP array in the local files, under the
 * version token of the other local caches: a request includes the array from
 * OPcache where Magento decodes about 1 MB of JSON from the config cache.
 * A miss captures the version, runs Magento's path (the config cache, else
 * the reader and a save to the config cache) and writes the array under the
 * captured version, so a clean on any server during the build refuses the
 * write. The save of a fresh build changes the version too, so the request
 * that builds the schema writes no file; the next request reads the config
 * cache and writes it.
 */
class OpcacheData extends Data
{
    private const SWITCH = 'schema_array';
    private const GROUP = 'SCHEMA';

    public function __construct(
        ReaderInterface $reader,
        CacheInterface $cache,
        private readonly string $cacheId,
        private readonly PhpFiles $files,
        private readonly Feature $feature,
        ?SerializerInterface $serializer = null,
        ?array $cacheTags = null,
    ) {
        parent::__construct($reader, $cache, $cacheId, $serializer, $cacheTags);
    }

    protected function initData()
    {
        if (!$this->feature->on(self::SWITCH)) {
            parent::initData();
            return;
        }
        $data = $this->files->read(self::GROUP, $this->cacheId);
        if (is_array($data)) {
            $this->merge($data);
            return;
        }
        $version = $this->files->generation();
        parent::initData();
        $this->files->write(self::GROUP, $this->cacheId, $this->_data, null, $version);
    }
}
