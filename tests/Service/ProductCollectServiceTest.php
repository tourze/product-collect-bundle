<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\ProductCollectBundle\Entity\ProductCollect;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCollectBundle\Exception\ProductCollectException;
use Tourze\ProductCollectBundle\Repository\ProductCollectRepository;
use Tourze\ProductCollectBundle\Service\ProductCollectService;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * @internal
 *
 * 此测试类使用Mock对象进行单元测试，不需要Symfony容器，
 * 因此继承TestCase而非AbstractIntegrationTestCase是正确的选择
 * @phpstan-ignore-next-line
 */
#[CoversClass(ProductCollectService::class)]
final class ProductCollectServiceTest extends TestCase
{
    // 注：此测试使用mock对象，不需要容器依赖注入，继承TestCase是合适的

    private ProductCollectRepository&MockObject $repository;

    private EntityManagerInterface&MockObject $entityManager;

    private ProductCollectService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProductCollectRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new ProductCollectService($this->repository, $this->entityManager);
    }

    public function testAddToCollectionWithNewSkuShouldCreateNewCollect(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $collectGroup = 'favorites';
        $note = 'test note';

        $this->repository
            ->expects($this->once())
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn(null)
        ;

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with(self::isInstanceOf(ProductCollect::class), true)
        ;

        // Act
        $result = $this->service->addToCollection($userId, $sku, $collectGroup, $note);

        // Assert
        $this->assertInstanceOf(ProductCollect::class, $result);
        $this->assertSame($userId, $result->getUserId());
        $this->assertSame($sku, $result->getSku());
        $this->assertSame($collectGroup, $result->getCollectGroup());
        $this->assertSame($note, $result->getNote());
    }

    public function testAddToCollectionWithExistingActiveCollectShouldThrowException(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);

        $existingCollect = $this->createMock(ProductCollect::class);
        $existingCollect->method('isCancelled')->willReturn(false);
        $existingCollect->method('isHidden')->willReturn(false);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn($existingCollect)
        ;

        // Act & Assert
        $this->expectException(ProductCollectException::class);
        $this->service->addToCollection($userId, $sku);
    }

    #[DataProvider('cancelledOrHiddenCollectProvider')]
    public function testAddToCollectionWithCancelledOrHiddenCollectShouldReactivate(bool $isCancelled, bool $isHidden): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $collectGroup = 'new-group';
        $note = 'new note';

        $existingCollect = $this->createMock(ProductCollect::class);
        $existingCollect->method('isCancelled')->willReturn($isCancelled);
        $existingCollect->method('isHidden')->willReturn($isHidden);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn($existingCollect)
        ;

        $existingCollect->expects($this->once())->method('activate');
        $existingCollect->expects($this->once())->method('setCollectGroup')->with($collectGroup);
        $existingCollect->expects($this->once())->method('setNote')->with($note);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($existingCollect, true)
        ;

        // Act
        $result = $this->service->addToCollection($userId, $sku, $collectGroup, $note);

        // Assert
        $this->assertSame($existingCollect, $result);
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function cancelledOrHiddenCollectProvider(): array
    {
        return [
            'cancelled collect' => [true, false],
            'hidden collect' => [false, true],
            'cancelled and hidden collect' => [true, true],
        ];
    }

    public function testRemoveFromCollectionWithExistingCollectShouldRemove(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $existingCollect = $this->createMock(ProductCollect::class);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn($existingCollect)
        ;

        $this->repository
            ->expects($this->once())
            ->method('remove')
            ->with($existingCollect, true)
        ;

        // Act
        $this->service->removeFromCollection($userId, $sku);
    }

    public function testRemoveFromCollectionWithNonExistingCollectShouldThrowException(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn(null)
        ;

        // Act & Assert
        $this->expectException(ProductCollectException::class);
        $this->service->removeFromCollection($userId, $sku);
    }

    public function testToggleCollectionWithNonExistingCollectShouldAddToCollection(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $collectGroup = 'favorites';
        $note = 'test note';

        $this->repository
            ->expects($this->exactly(2))
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn(null)
        ;

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with(self::isInstanceOf(ProductCollect::class), true)
        ;

        // Act
        $result = $this->service->toggleCollection($userId, $sku, $collectGroup, $note);

        // Assert
        $this->assertInstanceOf(ProductCollect::class, $result);
    }

    public function testToggleCollectionWithActiveCollectShouldCancel(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $existingCollect = $this->createMock(ProductCollect::class);

        $existingCollect->method('isActive')->willReturn(true);
        $existingCollect->expects($this->once())->method('cancel');

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn($existingCollect)
        ;

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($existingCollect, true)
        ;

        // Act
        $result = $this->service->toggleCollection($userId, $sku);

        // Assert
        $this->assertSame($existingCollect, $result);
    }

    public function testToggleCollectionWithInactiveCollectShouldActivate(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $existingCollect = $this->createMock(ProductCollect::class);

        $existingCollect->method('isActive')->willReturn(false);
        $existingCollect->expects($this->once())->method('activate');

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn($existingCollect)
        ;

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($existingCollect, true)
        ;

        // Act
        $result = $this->service->toggleCollection($userId, $sku);

        // Assert
        $this->assertSame($existingCollect, $result);
    }

    public function testIsCollectedWithActiveCollectShouldReturnTrue(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $collect = $this->createMock(ProductCollect::class);
        $collect->method('isActive')->willReturn(true);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn($collect)
        ;

        // Act
        $result = $this->service->isCollected($userId, $sku);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsCollectedWithInactiveCollectShouldReturnFalse(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);
        $collect = $this->createMock(ProductCollect::class);
        $collect->method('isActive')->willReturn(false);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn($collect)
        ;

        // Act
        $result = $this->service->isCollected($userId, $sku);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsCollectedWithNoCollectShouldReturnFalse(): void
    {
        // Arrange
        $userId = 'user123';
        $sku = $this->createMock(Sku::class);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->with($userId, $sku)
            ->willReturn(null)
        ;

        // Act
        $result = $this->service->isCollected($userId, $sku);

        // Assert
        $this->assertFalse($result);
    }

    public function testGetCollectionStatisticsShouldReturnCorrectStructure(): void
    {
        // Arrange
        $userId = 'user123';

        $this->repository
            ->expects($this->exactly(4))
            ->method('countByUserId')
            ->willReturnOnConsecutiveCalls(10, 7, 2, 1)
        ;

        // Act
        $result = $this->service->getCollectionStatistics($userId);

        // Assert
        $expected = [
            'total' => 10,
            'active' => 7,
            'cancelled' => 2,
            'hidden' => 1,
        ];
        $this->assertSame($expected, $result);
    }

    public function testBatchAddToCollectionShouldCreateMultipleCollectsAndFlush(): void
    {
        // Arrange
        $userId = 'user123';
        $sku1 = $this->createMock(Sku::class);
        $sku2 = $this->createMock(Sku::class);
        $skus = [$sku1, $sku2];
        $collectGroup = 'batch-favorites';

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->willReturn(null) // No existing collects
        ;

        $this->repository
            ->expects($this->exactly(2))
            ->method('save')
            ->with(self::isInstanceOf(ProductCollect::class), false)
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        // Act
        $results = $this->service->batchAddToCollection($userId, $skus, $collectGroup);

        // Assert
        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertInstanceOf(ProductCollect::class, $result);
            $this->assertSame($userId, $result->getUserId());
            $this->assertSame($collectGroup, $result->getCollectGroup());
        }
    }

    public function testBatchAddToCollectionWithExistingCollectsShouldSkipDuplicates(): void
    {
        // Arrange
        $userId = 'user123';
        $sku1 = $this->createMock(Sku::class);
        $sku2 = $this->createMock(Sku::class);
        $skus = [$sku1, $sku2];

        $existingCollect = $this->createMock(ProductCollect::class);

        $this->repository
            ->method('findOneByUserIdAndSku')
            ->willReturnMap([
                [$userId, $sku1, $existingCollect],
                [$userId, $sku2, null],
            ])
        ;

        $this->repository
            ->expects($this->once()) // Only one save for sku2
            ->method('save')
            ->with(self::isInstanceOf(ProductCollect::class), false)
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        // Act
        $results = $this->service->batchAddToCollection($userId, $skus);

        // Assert
        $this->assertCount(1, $results); // Only sku2 was added
    }
}
