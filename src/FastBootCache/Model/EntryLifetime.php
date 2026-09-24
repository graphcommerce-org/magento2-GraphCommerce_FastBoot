<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootCache\Model;

use Magento\Framework\Cache\FrontendInterface;

/** Read actual backend expiry, never grant a promoted entry another full cache lifetime. */
class EntryLifetime
{
    public function remaining(FrontendInterface $frontend, string $id, mixed $value): int|null|false
    {
        $low = $frontend->getLowLevelFrontend();
        try {
            if ($low instanceof \Magento\Framework\Cache\Frontend\Adapter\Symfony\LowLevelFrontend) {
                $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace('.', '__', strtoupper($id)));
                $item = $low->getItem($clean);
                $record = $item->isHit() ? $item->get() : null;
                if (!is_array($record) || !array_key_exists('expire', $record) || self::stored($record['data'] ?? null) !== $value) {
                    return false;
                }
                $expiry = $record['expire'];
            } elseif ($low instanceof \Zend_Cache_Core) {
                $record = $low->getMetadatas(strtoupper($id));
                if (!$record || $low->load(strtoupper($id)) !== $value) {
                    return false;
                }
                $expiry = $record['expire'];
            } else {
                return false;
            }
            return $expiry === null || $expiry === false ? null : max(0, (int)floor($expiry - microtime(true)));
        } catch (\Throwable) {
            // The native read already succeeded; inability to prove expiry disables promotion only.
            return false;
        }
    }

    /**
     * The value as the compression decorator of Mage-OS hands it to the frontends above it:
     * a string above its threshold is stored with the CACHE_COMPRESSION prefix and packed.
     */
    private static function stored(mixed $data): mixed
    {
        if (!is_string($data) || !str_starts_with($data, 'CACHE_COMPRESSION')) {
            return $data;
        }
        $packed = substr($data, strlen('CACHE_COMPRESSION'));
        foreach (['gzuncompress', 'snappy_uncompress', 'lzf_decompress', 'lz4_uncompress', 'zstd_uncompress'] as $function) {
            if (function_exists($function) && ($value = @$function($packed)) !== false) {
                return $value;
            }
        }

        return $data;
    }
}
