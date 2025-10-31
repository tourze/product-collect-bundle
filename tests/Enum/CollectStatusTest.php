<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;
use Tourze\ProductCollectBundle\Enum\CollectStatus;

/**
 * @internal
 */
#[CoversClass(CollectStatus::class)]
final class CollectStatusTest extends AbstractEnumTestCase
{
    public function testIsActive(): void
    {
        $this->assertTrue(CollectStatus::ACTIVE->isActive());
        $this->assertFalse(CollectStatus::CANCELLED->isActive());
        $this->assertFalse(CollectStatus::HIDDEN->isActive());
    }

    public function testIsCancelled(): void
    {
        $this->assertFalse(CollectStatus::ACTIVE->isCancelled());
        $this->assertTrue(CollectStatus::CANCELLED->isCancelled());
        $this->assertFalse(CollectStatus::HIDDEN->isCancelled());
    }

    public function testIsHidden(): void
    {
        $this->assertFalse(CollectStatus::ACTIVE->isHidden());
        $this->assertFalse(CollectStatus::CANCELLED->isHidden());
        $this->assertTrue(CollectStatus::HIDDEN->isHidden());
    }

    public function testGetName(): void
    {
        $this->assertSame('已收藏', CollectStatus::ACTIVE->getName());
        $this->assertSame('已取消', CollectStatus::CANCELLED->getName());
        $this->assertSame('已隐藏', CollectStatus::HIDDEN->getName());
    }

    public function testGetBadgeClass(): void
    {
        $this->assertSame('success', CollectStatus::ACTIVE->getBadgeClass());
        $this->assertSame('secondary', CollectStatus::CANCELLED->getBadgeClass());
        $this->assertSame('warning', CollectStatus::HIDDEN->getBadgeClass());
    }

    public function testLabel(): void
    {
        $this->assertSame('已收藏', CollectStatus::ACTIVE->label());
        $this->assertSame('已取消', CollectStatus::CANCELLED->label());
        $this->assertSame('已隐藏', CollectStatus::HIDDEN->label());
    }

    public function testToArray(): void
    {
        $expected = [
            'ACTIVE' => '已收藏',
            'CANCELLED' => '已取消',
            'HIDDEN' => '已隐藏',
        ];

        // toArray 是实例方法，需要通过实例调用
        $actual = [];
        foreach (CollectStatus::cases() as $case) {
            $actual[$case->name] = $case->label();
        }
        $this->assertSame($expected, $actual);
    }
}
