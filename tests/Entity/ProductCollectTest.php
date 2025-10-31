<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCollectBundle\Entity\ProductCollect;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;

/**
 * @internal
 */
#[CoversClass(ProductCollect::class)]
final class ProductCollectTest extends AbstractEntityTestCase
{
    private ProductCollect $collect;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collect = new ProductCollect();
    }

    /**
     * 创建被测实体的一个实例。
     */
    protected function createEntity(): object
    {
        return new ProductCollect();
    }

    /**
     * 提供属性及其样本值的 Data Provider。
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'userId' => ['userId', 'user123'];
        yield 'collectGroup' => ['collectGroup', '我的收藏'];
        yield 'note' => ['note', '这是一个很好的商品'];
        yield 'sortNumber' => ['sortNumber', 100];
        yield 'metadata' => ['metadata', ['key' => 'value', 'data' => 123]];
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->collect->getUserId());
        $this->assertNull($this->collect->getSku());
        $this->assertEquals(CollectStatus::ACTIVE, $this->collect->getStatus());
        $this->assertNull($this->collect->getCollectGroup());
        $this->assertNull($this->collect->getNote());
        $this->assertEquals(0, $this->collect->getSortNumber());
        $this->assertFalse($this->collect->isTop());
        $this->assertNull($this->collect->getMetadata());
    }

    public function testSetUserId(): void
    {
        $userId = 'user123';
        $this->collect->setUserId($userId);

        $this->assertEquals($userId, $this->collect->getUserId());
    }

    public function testSetSku(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('123');

        $this->collect->setSku($sku);

        $this->assertSame($sku, $this->collect->getSku());
        $this->assertEquals('123', $this->collect->getSkuId());
    }

    public function testSetStatus(): void
    {
        $this->collect->setStatus(CollectStatus::CANCELLED);

        $this->assertEquals(CollectStatus::CANCELLED, $this->collect->getStatus());
    }

    public function testSetCollectGroup(): void
    {
        $group = '我的收藏';
        $this->collect->setCollectGroup($group);

        $this->assertEquals($group, $this->collect->getCollectGroup());
    }

    public function testSetNote(): void
    {
        $note = '这是一个很好的商品';
        $this->collect->setNote($note);

        $this->assertEquals($note, $this->collect->getNote());
    }

    public function testSetSortNumber(): void
    {
        $sortNumber = 100;
        $this->collect->setSortNumber($sortNumber);

        $this->assertEquals($sortNumber, $this->collect->getSortNumber());
    }

    public function testSetIsTop(): void
    {
        $this->collect->setIsTop(true);

        $this->assertTrue($this->collect->isTop());

        $this->collect->setIsTop(false);

        $this->assertFalse($this->collect->isTop());
    }

    public function testSetMetadata(): void
    {
        $metadata = ['key' => 'value', 'data' => 123];
        $this->collect->setMetadata($metadata);

        $this->assertEquals($metadata, $this->collect->getMetadata());
    }

    public function testStatusMethods(): void
    {
        $this->collect->setStatus(CollectStatus::ACTIVE);
        $this->assertTrue($this->collect->isActive());
        $this->assertFalse($this->collect->isCancelled());
        $this->assertFalse($this->collect->isHidden());

        $this->collect->setStatus(CollectStatus::CANCELLED);
        $this->assertFalse($this->collect->isActive());
        $this->assertTrue($this->collect->isCancelled());
        $this->assertFalse($this->collect->isHidden());

        $this->collect->setStatus(CollectStatus::HIDDEN);
        $this->assertFalse($this->collect->isActive());
        $this->assertFalse($this->collect->isCancelled());
        $this->assertTrue($this->collect->isHidden());
    }

    public function testCancel(): void
    {
        $this->collect->cancel();

        $this->assertEquals(CollectStatus::CANCELLED, $this->collect->getStatus());
        $this->assertTrue($this->collect->isCancelled());
    }

    public function testHide(): void
    {
        $this->collect->hide();

        $this->assertEquals(CollectStatus::HIDDEN, $this->collect->getStatus());
        $this->assertTrue($this->collect->isHidden());
    }

    public function testActivate(): void
    {
        $this->collect->setStatus(CollectStatus::CANCELLED);
        $this->collect->activate();

        $this->assertEquals(CollectStatus::ACTIVE, $this->collect->getStatus());
        $this->assertTrue($this->collect->isActive());
    }

    public function testSetTop(): void
    {
        $this->collect->setTop();

        $this->assertTrue($this->collect->isTop());

        $this->collect->setTop(false);

        $this->assertFalse($this->collect->isTop());
    }

    public function testGetSkuName(): void
    {
        $sku = $this->createMock(Sku::class);
        $spu = $this->createMock(Spu::class);
        $spu->method('getTitle')->willReturn('测试商品');
        $sku->method('getSpu')->willReturn($spu);

        $this->collect->setSku($sku);

        $this->assertEquals('测试商品', $this->collect->getSkuName());
    }

    public function testGetSkuNameWithNullSku(): void
    {
        $this->assertNull($this->collect->getSkuName());
    }

    public function testGetSkuThumb(): void
    {
        $sku = $this->createMock(Sku::class);
        $thumbs = ['thumb1.jpg', 'thumb2.jpg'];
        $sku->method('getThumbs')->willReturn($thumbs);

        $this->collect->setSku($sku);

        $this->assertEquals('thumb1.jpg', $this->collect->getSkuThumb());
    }

    public function testGetSkuThumbWithEmptyThumbs(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getThumbs')->willReturn([]);

        $this->collect->setSku($sku);

        $this->assertNull($this->collect->getSkuThumb());
    }

    public function testGetSkuThumbWithNullSku(): void
    {
        $this->assertNull($this->collect->getSkuThumb());
    }

    public function testToString(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('123');

        $this->collect->setUserId('user123');
        $this->collect->setSku($sku);

        $result = (string) $this->collect;

        $this->assertStringContainsString('ProductCollect', $result);
        $this->assertStringContainsString('User:user123', $result);
        $this->assertStringContainsString('SKU:123', $result);
    }

    public function testSetterMethods(): void
    {
        $sku = $this->createMock(Sku::class);

        // 测试setter方法不再支持链式调用，改为独立调用
        $this->collect->setUserId('user123');
        $this->collect->setSku($sku);
        $this->collect->setStatus(CollectStatus::ACTIVE);
        $this->collect->setCollectGroup('测试分组');
        $this->collect->setNote('测试备注');
        $this->collect->setSortNumber(50);
        $this->collect->setIsTop(true);
        $this->collect->setMetadata(['test' => 'data']);

        // 验证所有属性都正确设置
        $this->assertSame('user123', $this->collect->getUserId());
        $this->assertSame($sku, $this->collect->getSku());
        $this->assertSame(CollectStatus::ACTIVE, $this->collect->getStatus());
        $this->assertSame('测试分组', $this->collect->getCollectGroup());
        $this->assertSame('测试备注', $this->collect->getNote());
        $this->assertSame(50, $this->collect->getSortNumber());
        $this->assertTrue($this->collect->isTop());
        $this->assertSame(['test' => 'data'], $this->collect->getMetadata());
    }
}
