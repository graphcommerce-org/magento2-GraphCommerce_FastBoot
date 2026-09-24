<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Test\Unit\Plugin\Config;

use GraphCommerce\FastBoot\Plugin\Config\ForgetValueMemo;
use GraphCommerce\FastBoot\Plugin\Config\ValueMemo;
use GraphCommerce\FastBootCache\Model\Feature;
use Magento\Framework\App\Config;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ValueMemoTest extends TestCase
{
    private int $reads = 0;

    private function memo(): ValueMemo
    {
        $this->reads = 0;
        $feature = $this->createStub(Feature::class);
        $feature->method('on')->willReturn(true);

        return new ValueMemo($feature);
    }

    private function read(ValueMemo $memo, string $path, string $scope = 'default', mixed $code = null): mixed
    {
        return $memo->aroundGetValue($this->createStub(Config::class), function ($path) {
            $this->reads++;

            return $path === 'a/null' ? null : strtoupper($path);
        }, $path, $scope, $code);
    }

    public function testASecondReadOfTheSameValueSkipsTheConfiguration(): void
    {
        $memo = $this->memo();

        $this->assertSame('A/B', $this->read($memo, 'a/b', 'store'));
        $this->assertSame('A/B', $this->read($memo, 'a/b', 'store'));
        $this->assertNull($this->read($memo, 'a/null'));
        $this->assertNull($this->read($memo, 'a/null'));
        $this->assertSame(2, $this->reads);
    }

    public function testTheScopeAndTheCodeAreInTheKey(): void
    {
        $memo = $this->memo();

        $this->read($memo, 'a/b', 'store');
        $this->read($memo, 'a/b', 'store', 'second');
        $this->read($memo, 'a/b', 'website');

        $this->assertSame(3, $this->reads);
    }

    public function testACleanAMutableSetAndAStoreSwitchEmptyTheMemo(): void
    {
        $memo = $this->memo();
        $forget = new ForgetValueMemo($memo);

        $this->read($memo, 'a/b');
        $memo->afterClean($this->createStub(Config::class), null);
        $this->read($memo, 'a/b');
        $forget->afterSetValue($this->createStub(MutableScopeConfigInterface::class), null);
        $this->read($memo, 'a/b');
        $forget->afterSetCurrentStore(new \stdClass(), null);
        $this->read($memo, 'a/b');

        $this->assertSame(4, $this->reads);
    }

    public function testAScopeObjectAsCodeIsNotMemoised(): void
    {
        $memo = $this->memo();

        $this->read($memo, 'a/b', 'store', new \stdClass());
        $this->read($memo, 'a/b', 'store', new \stdClass());

        $this->assertSame(2, $this->reads);
    }
}
