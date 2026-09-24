<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootCache\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

/** Immutable content-addressed PHP values; mutable expiry/version metadata stays outside OPcache. */
class PhpFiles
{
    private ?string $root = null;

    /** @var array<string, array{value: mixed, expires: ?float}> the entries this process read or wrote, by index path */
    private array $loaded = [];
    public const LOADED_LIFETIME = 0; // Unknown backend lifetimes must never be extended.
    public function __construct(private Filesystem $filesystem, private Version $version, private DeploymentConfig $deploymentConfig, private Release $release)
    {
    }
    public static function lifetime($lifeTime): ?int
    {
        return $lifeTime === null ? null : ($lifeTime === false ? 7200 : max(0, (int)$lifeTime));
    }
    /** Capture before deriving data; a same-request invalidation must fence the later write. */
    public function generation(): string
    {
        return $this->version->current();
    }
    public function namespaceDirectory(): string
    {
        if ($this->root === null) {
            $identity = [$this->deploymentConfig->get('cache/frontend/default'), $this->release->id(), defined('BP') ? BP : __DIR__];
            $var = $this->filesystem->getDirectoryRead(DirectoryList::CACHE)->getAbsolutePath();
            $this->root = rtrim($var, '/').'/fastboot/v2/'.hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        }
        return $this->root;
    }
    private function path(string $group, string $id, ?string $version = null): string
    {
        return $this->namespaceDirectory().'/indexes/'.hash('sha256', $version ?? $this->version->current()).'/'.hash('sha256', $group).'/'.hash('sha256', $id).'.json';
    }
    public function read(string $group, string $id): mixed
    {
        if ($this->release->id() === null) {
            return null;
        }
        $index = $this->path($group, $id);
        if (isset($this->loaded[$index]) && ($this->loaded[$index]['expires'] === null || $this->loaded[$index]['expires'] > microtime(true))) {
            return $this->loaded[$index]['value'];
        }
        $record = is_file($index) ? json_decode((string)@file_get_contents($index), true) : null;
        if (!is_array($record) || !isset($record['hash']) || !is_string($record['hash']) || !preg_match('/^[a-f0-9]{64}$/D', $record['hash']) || !array_key_exists('expires', $record) || ($record['expires'] !== null && !is_numeric($record['expires'])) || ($record['expires'] !== null && $record['expires'] <= microtime(true))) {
            return null;
        }
        $file = $this->namespaceDirectory().'/blobs/'.$record['hash'].'.php';
        if (!is_file($file)) {
            return null;
        }
        try {
            $value = @include $file;
        } catch (\ParseError) {
            $value = null;
        }
        if (!is_array($value) || !array_key_exists('value', $value)) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
            @unlink($index);
            @unlink($file);
            return null;
        }
        $this->remember($index, $value['value'], $record['expires'] === null ? null : (float)$record['expires']);
        return $value['value'];
    }

    /**
     * A worker process answers the entry from memory until it expires; the index path carries
     * the version, so a bump leaves the memory behind.
     */
    private function remember(string $index, mixed $value, ?float $expires): void
    {
        if (count($this->loaded) >= 512) {
            $this->loaded = [];
        }
        $this->loaded[$index] = ['value' => $value, 'expires' => $expires];
    }
    public function write(string $group, string $id, mixed $value, ?int $lifeTime = null, ?string $expectedVersion = null): void
    {
        if ($this->release->id() === null) {
            return;
        }
        if (!self::exportable($value) || ($lifeTime !== null && $lifeTime <= 0)) {
            return;
        }
        $version = $this->version->current();
        if ($expectedVersion !== null && $version !== $expectedVersion) {
            return;
        }
        $content = "<?php\nreturn ".var_export(['value' => $value], true).";\n";
        $options = (array)$this->deploymentConfig->get('fastboot/files', []);
        if (strlen($content) > ($options['max_entry_bytes'] ?? 2097152)) {
            return;
        }
        $root = $this->namespaceDirectory();
        $hash = hash('sha256', $content);
        $blob = $root.'/blobs/'.$hash.'.php';
        $index = $this->path($group, $id, $version);
        if (!is_dir($root.'/blobs') && !@mkdir($root.'/blobs', 0700, true) && !is_dir($root.'/blobs')) {
            return;
        }
        $lock = @fopen($root.'/write.lock', 'c');
        if (!$lock) {
            return;
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                return;
            }
            if (in_array($group, ['PARSED','VALIDATED'], true) && !is_file($index) && count(glob(dirname($index).'/*.json') ?: []) >= ($options['max_queries'] ?? 256)) {
                return;
            }
            $quotaFile = $root.'/quota.json';
            $quota = is_file($quotaFile) ? json_decode((string)@file_get_contents($quotaFile), true) : null;
            if (!is_array($quota) || !isset($quota['bytes'],$quota['files'])) {
                $files = glob($root.'/blobs/*.php') ?: [];
                $quota = ['files' => count($files),'bytes' => array_sum(array_map('filesize', $files))];
            }
            if (!is_file($blob)) {
                if ($quota['files'] >= ($options['max_files'] ?? 2048) || $quota['bytes'] + strlen($content) > ($options['max_bytes'] ?? 67108864)) {
                    return;
                }
                if (!$this->atomic($blob, $content)) {
                    return;
                }
                $quota['files']++;
                $quota['bytes'] += strlen($content);
                if (!$this->atomic($quotaFile, json_encode($quota, JSON_THROW_ON_ERROR))) {
                    @unlink($blob);
                    return;
                }
            }
            // Arbitrary user-supplied queries cannot create unbounded index files either.
            if (!is_dir(dirname($index)) && !@mkdir(dirname($index), 0700, true) && !is_dir(dirname($index))) {
                return;
            }
            $expires = $lifeTime === null ? null : microtime(true) + $lifeTime;
            if ($this->atomic($index, json_encode(['hash' => $hash,'expires' => $expires], JSON_THROW_ON_ERROR))) {
                $this->remember($index, $value, $expires);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    private function atomic(string $file, string $content): bool
    {
        $temporary = $file.'.'.bin2hex(random_bytes(8)).'.tmp';
        if (@file_put_contents($temporary, $content) !== strlen($content)) {
            @unlink($temporary);
            return false;
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $file)) {
            @unlink($temporary);
            return false;
        }
        return true;
    }
    private static function exportable(mixed $value, int $depth = 0): bool
    {
        if ($depth > 64) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::exportable($item, $depth + 1)) {
                    return false;
                }
            } return true;
        }
        return $value === null || is_scalar($value);
    }
    public function remove(string $group, string $id): void
    {
        unset($this->loaded[$this->path($group, $id)]);
        @unlink($this->path($group, $id));
    }
    /** Remove obsolete indexes only. Blobs stay reusable and bounded until release cleanup/FPM restart. */
    public function sweep(): void
    {
        $this->loaded = [];
        $root = $this->namespaceDirectory().'/indexes';
        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $this->removeDirectory($dir);
        }
    }
    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
        }
        @rmdir($dir);
    }
}
