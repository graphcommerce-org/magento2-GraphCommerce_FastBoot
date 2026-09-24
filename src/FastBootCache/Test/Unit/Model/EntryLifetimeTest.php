<?php

declare(strict_types=1);

namespace GraphCommerce\FastBootCache\Test\Unit\Model;

use GraphCommerce\FastBootCache\Model\EntryLifetime;
use Magento\Framework\Cache\Frontend\Adapter\Symfony;
use Magento\Framework\Cache\Frontend\Decorator\Compression;
use PHPUnit\Framework\TestCase;

class EntryLifetimeTest extends TestCase
{
    public function testPromotionUsesActualExpiryAndRejectsChangedPayload(): void
    {
        if (!class_exists(Symfony::class)) {
            self::markTestSkipped('Symfony frontend is not present in this Magento version.');
        }
        $dir = sys_get_temp_dir().'/fastboot-native-'.bin2hex(random_bytes(8));
        $frontend = new Symfony(static fn () => new \Symfony\Component\Cache\Adapter\FilesystemAdapter('lifetime', 7200, $dir));
        $reader = new EntryLifetime();
        try {
            $frontend->save('before', 'some.id', ['CONFIG'], 2);
            $ttl = $reader->remaining($frontend, 'some.id', 'before');
            self::assertIsInt($ttl);
            self::assertGreaterThanOrEqual(0, $ttl);
            self::assertLessThanOrEqual(2, $ttl);
            $frontend->save('after', 'some.id', ['CONFIG'], 2);
            self::assertFalse($reader->remaining($frontend, 'some.id', 'before'));
            usleep(2050000);
            self::assertFalse($frontend->load('some.id'));
            self::assertFalse($reader->remaining($frontend, 'some.id', 'after'));
        } finally {
            $frontend->clean();
        }
    }
    public function testAnEntryStoredThroughTheCompressionDecoratorIsPromotedWithItsExpiry(): void
    {
        if (!class_exists(Symfony::class) || !class_exists(Compression::class)) {
            self::markTestSkipped('The compression decorator is not present in this Magento version.');
        }
        $dir = sys_get_temp_dir().'/fastboot-compressed-'.bin2hex(random_bytes(8));
        $frontend = new Compression(new Symfony(static fn () => new \Symfony\Component\Cache\Adapter\FilesystemAdapter('lifetime', 7200, $dir)), 8);
        $reader = new EntryLifetime();
        $value = str_repeat('translated text ', 64);
        try {
            $frontend->save($value, 'big.id', ['TRANSLATE'], 30);
            self::assertSame($value, $frontend->load('big.id'));
            $ttl = $reader->remaining($frontend, 'big.id', $value);
            self::assertIsInt($ttl);
            self::assertGreaterThan(0, $ttl);
            self::assertLessThanOrEqual(30, $ttl);
            self::assertFalse($reader->remaining($frontend, 'big.id', $value.'changed'));
        } finally {
            $frontend->clean();
        }
    }

    public function testLegacyZendFrontendPreservesExpiryAndPermanentEntries(): void
    {
        $dir = sys_get_temp_dir().'/fastboot-zend-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $frontend = new \Magento\Framework\Cache\Frontend\Adapter\Zend(static fn () => \Zend_Cache::factory('Core', 'File', ['lifetime' => 7200], ['cache_dir' => $dir]));
        $reader = new EntryLifetime();
        try {
            $frontend->save('before', 'mixed_case_key', ['CONFIG'], 1);
            $ttl = $reader->remaining($frontend, 'mixed_case_key', 'before');
            self::assertIsInt($ttl);
            self::assertGreaterThanOrEqual(0, $ttl);
            self::assertLessThanOrEqual(2, $ttl);
            $frontend->save('after', 'mixed_case_key', ['CONFIG'], 1);
            self::assertFalse($reader->remaining($frontend, 'mixed_case_key', 'before'));
            $frontend->save('permanent', 'permanent', ['CONFIG'], null);
            $permanent = $reader->remaining($frontend, 'permanent', 'permanent');
            self::assertTrue($permanent === null || $permanent > 7200);
            usleep(2100000);
            self::assertFalse($frontend->load('mixed_case_key'));
            self::assertFalse($reader->remaining($frontend, 'mixed_case_key', 'after'));
        } finally {
            $frontend->clean();
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
