<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\ProductCollectBundle\Exception\ProductCollectException;

/**
 * @internal
 */
#[CoversClass(ProductCollectException::class)]
final class ProductCollectExceptionTest extends AbstractExceptionTestCase
{
    public function testSkuNotFound(): void
    {
        $skuId = 'sku123';
        $exception = ProductCollectException::skuNotFound($skuId);

        $this->assertInstanceOf(ProductCollectException::class, $exception);
        $this->assertSame("SKU [{$skuId}] 不存在", $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
    }

    public function testAlreadyCollected(): void
    {
        $exception = ProductCollectException::alreadyCollected();

        $this->assertInstanceOf(ProductCollectException::class, $exception);
        $this->assertSame('该商品已在收藏列表中', $exception->getMessage());
        $this->assertSame(409, $exception->getCode());
    }

    public function testNotCollected(): void
    {
        $exception = ProductCollectException::notCollected();

        $this->assertInstanceOf(ProductCollectException::class, $exception);
        $this->assertSame('该商品未在收藏列表中', $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
    }

    public function testCollectionLimitExceeded(): void
    {
        $limit = 100;
        $exception = ProductCollectException::collectionLimitExceeded($limit);

        $this->assertInstanceOf(ProductCollectException::class, $exception);
        $this->assertSame("收藏数量超过限制 [{$limit}]", $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
    }

    public function testInvalidStatus(): void
    {
        $status = 'invalid-status';
        $exception = ProductCollectException::invalidStatus($status);

        $this->assertInstanceOf(ProductCollectException::class, $exception);
        $this->assertSame("无效的收藏状态 [{$status}]", $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
    }

    public function testCollectionNotFound(): void
    {
        $collectId = 'collect123';
        $exception = ProductCollectException::collectionNotFound($collectId);

        $this->assertInstanceOf(ProductCollectException::class, $exception);
        $this->assertSame("收藏记录 [{$collectId}] 不存在", $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
    }
}
