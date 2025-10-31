<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCollectBundle\Entity\ProductCollect;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCollectBundle\Repository\ProductCollectRepository;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;

/**
 * @internal
 */
#[CoversClass(ProductCollectRepository::class)]
#[RunTestsInSeparateProcesses]
#[Group('integration')]
#[Group('database')]
final class ProductCollectRepositoryTest extends AbstractRepositoryTestCase
{
    private ProductCollectRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getService(ProductCollectRepository::class);
        $this->assertNotNull($repository, 'ProductCollectRepository service must be available');
        $this->repository = $repository;
    }

    protected function onTearDown(): void
    {
        // Repository cleanup is handled by parent class
    }

    public function testRepository(): void
    {
        $this->assertInstanceOf(ProductCollectRepository::class, $this->repository);
    }

    public function testCreateUserCollectsQueryBuilder(): void
    {
        $userId = 'test_user_qb';

        $qb = $this->repository->createUserCollectsQueryBuilder($userId);

        $this->assertNotNull($qb);
        $this->assertStringContainsString('pc.userId = :userId', $qb->getDQL());

        $parameters = $qb->getParameters();
        $userParam = null;
        foreach ($parameters as $param) {
            if ('userId' === $param->getName()) {
                $userParam = $param;
                break;
            }
        }
        $this->assertNotNull($userParam);
        $this->assertEquals($userId, $userParam->getValue());
    }

    public function testSaveWithFlushFalse(): void
    {
        $user = $this->createNormalUser('test-flush@example.com', 'password');
        $sku = $this->createSku();

        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);

        // 保存但不立即flush
        $this->repository->save($collect, false);

        // 确认实体已被persist到UnitOfWork中
        $this->assertTrue(self::getEntityManager()->contains($collect));

        // 获取UnitOfWork中的计划插入操作数量
        $uow = self::getEntityManager()->getUnitOfWork();
        $scheduledInsertions = $uow->getScheduledEntityInsertions();
        $this->assertCount(1, $scheduledInsertions);
        $this->assertContains($collect, $scheduledInsertions);

        // 手动flush后，实体应该不再在计划插入列表中
        self::getEntityManager()->flush();
        $scheduledInsertions = $uow->getScheduledEntityInsertions();
        $this->assertCount(0, $scheduledInsertions);

        // 现在应该能通过Repository找到这个实体
        $found = $this->repository->findOneByUserIdAndSku($user->getUserIdentifier(), $sku);
        $this->assertNotNull($found);
        $this->assertEquals($collect->getId(), $found->getId());
    }

    public function testFindOneByShouldRespectSortOrder(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password');
        $sku = $this->createSku();

        // 创建多条记录用于排序测试
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku);
        $collect1->setSortNumber(3);
        $this->repository->save($collect1);

        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($this->createSku()); // 使用不同的SKU避免唯一约束冲突
        $collect2->setSortNumber(1);
        $this->repository->save($collect2);

        $found = $this->repository->findOneBy(['userId' => $user->getUserIdentifier()], ['sortNumber' => 'ASC']);
        $this->assertInstanceOf(ProductCollect::class, $found);
        $this->assertEquals(1, $found->getSortNumber());
    }

    public function testCountWithAssociationShouldWork(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password');
        $sku = $this->createSku();

        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $this->repository->save($collect);

        $count = $this->repository->count(['sku' => $sku]);
        $this->assertEquals(1, $count);
    }

    public function testFindWithAssociationShouldWork(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password');
        $sku = $this->createSku();

        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $this->repository->save($collect);

        $results = $this->repository->findBy(['sku' => $sku]);
        $this->assertCount(1, $results);
        $resultSku = $results[0]->getSku();
        $this->assertNotNull($resultSku);
        $this->assertEquals($sku->getId(), $resultSku->getId());
    }

    public function testCountWithNullFieldsShouldWork(): void
    {
        $user = $this->createNormalUser('nulltest' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        // 创建有null字段的记录
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $collect->setCollectGroup(null); // null字段
        $this->repository->save($collect);

        // 创建有值的记录
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($this->createSku()); // 使用不同的SKU避免唯一约束冲突
        $collect2->setCollectGroup('test-group');
        $this->repository->save($collect2);

        // 测试 IS NULL 查询 - 只计算这个特定用户的记录
        $count = $this->repository->count([
            'collectGroup' => null,
            'userId' => $user->getUserIdentifier(),
        ]);
        $this->assertEquals(1, $count);

        // 测试关联 IS NULL 查询 - 只计算这个特定用户的记录
        $count = $this->repository->count([
            'metadata' => null,
            'userId' => $user->getUserIdentifier(),
        ]);
        $this->assertEquals(2, $count); // 两个记录的metadata都是null
    }

    public function testFindWithNullFieldsShouldWork(): void
    {
        $user = $this->createNormalUser('findnull' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        // 创建有null字段的记录
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $collect->setCollectGroup(null);
        $this->repository->save($collect);

        // 创建有值的记录
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($this->createSku()); // 使用不同的SKU避免唯一约束冲突
        $collect2->setCollectGroup('test-group');
        $this->repository->save($collect2);

        // 测试 IS NULL 查询 - 只查找这个特定用户的记录
        $results = $this->repository->findBy([
            'collectGroup' => null,
            'userId' => $user->getUserIdentifier(),
        ]);
        $this->assertCount(1, $results);
        $this->assertNull($results[0]->getCollectGroup());
    }

    protected function createNewEntity(): object
    {
        $user = $this->createNormalUser('test' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        $entity = new ProductCollect();
        $entity->setUserId($user->getUserIdentifier());
        $entity->setSku($sku);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<ProductCollect>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(ProductCollectRepository::class);
    }

    /**
     * 验证基本功能 - 确保可以创建和保存实体
     * 这个测试会在DataFixtures测试之前运行，确保数据库中有数据
     */
    public function testBasicCreateAndPersist(): void
    {
        $entity = $this->createNewEntity();
        self::getEntityManager()->persist($entity);
        self::getEntityManager()->flush();

        $count = $this->getRepository()->count([]);
        $this->assertGreaterThan(0, $count, '应该能够创建和保存ProductCollect实体');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createSku(array $data = []): Sku
    {
        $spu = new Spu();
        $title = $data['spuTitle'] ?? 'Test Product';
        $spu->setTitle(is_string($title) ? $title : 'Test Product');

        $em = self::getEntityManager();
        $em->persist($spu);

        $sku = new Sku();
        $sku->setSpu($spu);
        $gtin = $data['gtin'] ?? 'SKU' . uniqid();
        $sku->setGtin(is_string($gtin) ? $gtin : 'SKU' . uniqid());
        $unit = $data['unit'] ?? '个';
        $sku->setUnit(is_string($unit) ? $unit : '个');
        $valid = $data['valid'] ?? true;
        $sku->setValid(is_bool($valid) ? $valid : (bool) $valid);

        $em->persist($sku);
        $em->flush();

        return $sku;
    }

    public function testActivateByUserIdAndSkuId(): void
    {
        $user = $this->createNormalUser('activate' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        // 创建一个已取消的收藏
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $collect->cancel();
        $this->repository->save($collect);

        // 测试激活
        $result = $this->repository->activateByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertTrue($result);

        // 验证状态已更改
        $updated = $this->repository->findOneByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isActive());

        // 测试不存在的收藏
        $nonExistentSku = $this->createSku();
        $result = $this->repository->activateByUserIdAndSkuId($user->getUserIdentifier(), $nonExistentSku->getId());
        $this->assertFalse($result);
    }

    public function testCancelByUserIdAndSkuId(): void
    {
        $user = $this->createNormalUser('cancel' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        // 创建一个活跃的收藏
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $this->repository->save($collect);

        // 测试取消
        $result = $this->repository->cancelByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertTrue($result);

        // 验证状态已更改
        $updated = $this->repository->findOneByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isCancelled());

        // 测试不存在的收藏
        $nonExistentSku = $this->createSku();
        $result = $this->repository->cancelByUserIdAndSkuId($user->getUserIdentifier(), $nonExistentSku->getId());
        $this->assertFalse($result);
    }

    public function testCountActiveBySkuId(): void
    {
        $sku = $this->createSku();
        $user1 = $this->createNormalUser('count1' . uniqid() . '@example.com', 'password');
        $user2 = $this->createNormalUser('count2' . uniqid() . '@example.com', 'password');

        // 创建活跃收藏
        $collect1 = new ProductCollect();
        $collect1->setUserId($user1->getUserIdentifier());
        $collect1->setSku($sku);
        $this->repository->save($collect1);

        $collect2 = new ProductCollect();
        $collect2->setUserId($user2->getUserIdentifier());
        $collect2->setSku($sku);
        $this->repository->save($collect2);

        // 创建已取消的收藏
        $user3 = $this->createNormalUser('count3' . uniqid() . '@example.com', 'password');
        $collect3 = new ProductCollect();
        $collect3->setUserId($user3->getUserIdentifier());
        $collect3->setSku($sku);
        $collect3->cancel();
        $this->repository->save($collect3);

        // 测试活跃收藏数量
        $count = $this->repository->countActiveBySkuId($sku->getId());
        $this->assertEquals(2, $count);
    }

    public function testCountActiveByUserId(): void
    {
        $user = $this->createNormalUser('countuser' . uniqid() . '@example.com', 'password');

        // 创建活跃收藏
        $sku1 = $this->createSku();
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku1);
        $this->repository->save($collect1);

        $sku2 = $this->createSku();
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($sku2);
        $this->repository->save($collect2);

        // 创建已取消的收藏
        $sku3 = $this->createSku();
        $collect3 = new ProductCollect();
        $collect3->setUserId($user->getUserIdentifier());
        $collect3->setSku($sku3);
        $collect3->cancel();
        $this->repository->save($collect3);

        // 测试活跃收藏数量
        $count = $this->repository->countActiveByUserId($user->getUserIdentifier());
        $this->assertEquals(2, $count);
    }

    public function testCountBySkuId(): void
    {
        $sku = $this->createSku();
        $user1 = $this->createNormalUser('countsku1' . uniqid() . '@example.com', 'password');
        $user2 = $this->createNormalUser('countsku2' . uniqid() . '@example.com', 'password');

        // 创建不同状态的收藏
        $collect1 = new ProductCollect();
        $collect1->setUserId($user1->getUserIdentifier());
        $collect1->setSku($sku);
        $this->repository->save($collect1);

        $collect2 = new ProductCollect();
        $collect2->setUserId($user2->getUserIdentifier());
        $collect2->setSku($sku);
        $collect2->cancel();
        $this->repository->save($collect2);

        // 测试所有收藏数量
        $count = $this->repository->countBySkuId($sku->getId());
        $this->assertEquals(2, $count);

        // 测试指定状态的收藏数量
        $activeCount = $this->repository->countBySkuId($sku->getId(), CollectStatus::ACTIVE);
        $this->assertEquals(1, $activeCount);

        $cancelledCount = $this->repository->countBySkuId($sku->getId(), CollectStatus::CANCELLED);
        $this->assertEquals(1, $cancelledCount);
    }

    public function testCountByUserId(): void
    {
        $user = $this->createNormalUser('countbyuser' . uniqid() . '@example.com', 'password');

        // 创建不同状态的收藏
        $sku1 = $this->createSku();
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku1);
        $this->repository->save($collect1);

        $sku2 = $this->createSku();
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($sku2);
        $collect2->cancel();
        $this->repository->save($collect2);

        // 测试所有收藏数量
        $count = $this->repository->countByUserId($user->getUserIdentifier());
        $this->assertEquals(2, $count);

        // 测试指定状态的收藏数量
        $activeCount = $this->repository->countByUserId($user->getUserIdentifier(), CollectStatus::ACTIVE);
        $this->assertEquals(1, $activeCount);

        $cancelledCount = $this->repository->countByUserId($user->getUserIdentifier(), CollectStatus::CANCELLED);
        $this->assertEquals(1, $cancelledCount);
    }

    public function testFindActiveByUserId(): void
    {
        $user = $this->createNormalUser('activeuser' . uniqid() . '@example.com', 'password');

        // 创建活跃收藏
        $sku1 = $this->createSku();
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku1);
        $collect1->setSortNumber(2);
        $this->repository->save($collect1);

        $sku2 = $this->createSku();
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($sku2);
        $collect2->setSortNumber(1);
        $collect2->setIsTop(true);
        $this->repository->save($collect2);

        // 创建已取消的收藏
        $sku3 = $this->createSku();
        $collect3 = new ProductCollect();
        $collect3->setUserId($user->getUserIdentifier());
        $collect3->setSku($sku3);
        $collect3->cancel();
        $this->repository->save($collect3);

        // 测试只获取活跃收藏
        $activeCollects = $this->repository->findActiveByUserId($user->getUserIdentifier());
        $this->assertCount(2, $activeCollects);

        // 验证排序：置顶 > 排序号 > 创建时间
        $this->assertTrue($activeCollects[0]->isTop());
        $this->assertEquals(1, $activeCollects[0]->getSortNumber());
    }

    public function testFindByUserIdAndGroup(): void
    {
        $user = $this->createNormalUser('groupuser' . uniqid() . '@example.com', 'password');
        $groupName = 'test-group';

        // 创建分组收藏
        $sku1 = $this->createSku();
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku1);
        $collect1->setCollectGroup($groupName);
        $this->repository->save($collect1);

        // 创建无分组收藏
        $sku2 = $this->createSku();
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($sku2);
        $collect2->setCollectGroup(null);
        $this->repository->save($collect2);

        // 创建其他分组收藏
        $sku3 = $this->createSku();
        $collect3 = new ProductCollect();
        $collect3->setUserId($user->getUserIdentifier());
        $collect3->setSku($sku3);
        $collect3->setCollectGroup('other-group');
        $this->repository->save($collect3);

        // 测试查找指定分组
        $groupCollects = $this->repository->findByUserIdAndGroup($user->getUserIdentifier(), $groupName);
        $this->assertCount(1, $groupCollects);
        $this->assertEquals($groupName, $groupCollects[0]->getCollectGroup());

        // 测试查找无分组收藏
        $noGroupCollects = $this->repository->findByUserIdAndGroup($user->getUserIdentifier(), null);
        $this->assertCount(1, $noGroupCollects);
        $this->assertNull($noGroupCollects[0]->getCollectGroup());

        // 测试指定状态过滤
        $collect1->cancel();
        $this->repository->save($collect1);

        $activeGroupCollects = $this->repository->findByUserIdAndGroup(
            $user->getUserIdentifier(),
            $groupName,
            CollectStatus::ACTIVE
        );
        $this->assertCount(0, $activeGroupCollects);
    }

    public function testFindCollectsBySkuId(): void
    {
        $sku = $this->createSku();
        $user1 = $this->createNormalUser('sku1' . uniqid() . '@example.com', 'password');
        $user2 = $this->createNormalUser('sku2' . uniqid() . '@example.com', 'password');

        // 创建不同用户的收藏
        $collect1 = new ProductCollect();
        $collect1->setUserId($user1->getUserIdentifier());
        $collect1->setSku($sku);
        $this->repository->save($collect1);

        $collect2 = new ProductCollect();
        $collect2->setUserId($user2->getUserIdentifier());
        $collect2->setSku($sku);
        $collect2->cancel();
        $this->repository->save($collect2);

        // 测试获取所有收藏
        $allCollects = $this->repository->findCollectsBySkuId($sku->getId());
        $this->assertCount(2, $allCollects);

        // 测试指定状态过滤
        $activeCollects = $this->repository->findCollectsBySkuId($sku->getId(), CollectStatus::ACTIVE);
        $this->assertCount(1, $activeCollects);
        $this->assertTrue($activeCollects[0]->isActive());
    }

    public function testFindGroupsByUserId(): void
    {
        $user = $this->createNormalUser('groups' . uniqid() . '@example.com', 'password');

        // 创建不同分组的收藏
        $sku1 = $this->createSku();
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku1);
        $collect1->setCollectGroup('group-a');
        $this->repository->save($collect1);

        $sku2 = $this->createSku();
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($sku2);
        $collect2->setCollectGroup('group-a');
        $this->repository->save($collect2);

        $sku3 = $this->createSku();
        $collect3 = new ProductCollect();
        $collect3->setUserId($user->getUserIdentifier());
        $collect3->setSku($sku3);
        $collect3->setCollectGroup('group-b');
        $this->repository->save($collect3);

        // 创建已取消的收藏（不应包含在结果中）
        $sku4 = $this->createSku();
        $collect4 = new ProductCollect();
        $collect4->setUserId($user->getUserIdentifier());
        $collect4->setSku($sku4);
        $collect4->setCollectGroup('group-c');
        $collect4->cancel();
        $this->repository->save($collect4);

        // 创建无分组的收藏（不应包含在结果中）
        $sku5 = $this->createSku();
        $collect5 = new ProductCollect();
        $collect5->setUserId($user->getUserIdentifier());
        $collect5->setSku($sku5);
        $collect5->setCollectGroup(null);
        $this->repository->save($collect5);

        // 测试获取分组统计
        $groups = $this->repository->findGroupsByUserId($user->getUserIdentifier());
        $this->assertCount(2, $groups);

        // 验证分组按收藏数量降序排列
        $this->assertEquals('group-a', $groups[0]['name']);
        $this->assertEquals(2, $groups[0]['count']);
        $this->assertEquals('group-b', $groups[1]['name']);
        $this->assertEquals(1, $groups[1]['count']);
    }

    public function testFindOneByUserIdAndSku(): void
    {
        $user = $this->createNormalUser('onebysku' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        // 创建收藏
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $this->repository->save($collect);

        // 测试查找存在的收藏
        $found = $this->repository->findOneByUserIdAndSku($user->getUserIdentifier(), $sku);
        $this->assertNotNull($found);
        $this->assertEquals($collect->getId(), $found->getId());

        // 测试查找不存在的收藏
        $otherUser = $this->createNormalUser('other' . uniqid() . '@example.com', 'password');
        $notFound = $this->repository->findOneByUserIdAndSku($otherUser->getUserIdentifier(), $sku);
        $this->assertNull($notFound);
    }

    public function testFindOneByUserIdAndSkuId(): void
    {
        $user = $this->createNormalUser('oneskuid' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        // 创建收藏
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $this->repository->save($collect);

        // 测试查找存在的收藏
        $found = $this->repository->findOneByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertNotNull($found);
        $this->assertEquals($collect->getId(), $found->getId());

        // 测试查找不存在的收藏
        $otherSku = $this->createSku();
        $notFound = $this->repository->findOneByUserIdAndSkuId($user->getUserIdentifier(), $otherSku->getId());
        $this->assertNull($notFound);
    }

    public function testFindPopularSkus(): void
    {
        $sku1 = $this->createSku();
        $sku2 = $this->createSku();
        $sku3 = $this->createSku();

        // 为sku1创建3个收藏
        for ($i = 1; $i <= 3; ++$i) {
            $user = $this->createNormalUser("popular{$i}" . uniqid() . '@example.com', 'password');
            $collect = new ProductCollect();
            $collect->setUserId($user->getUserIdentifier());
            $collect->setSku($sku1);
            $this->repository->save($collect);
        }

        // 为sku2创建2个收藏
        for ($i = 1; $i <= 2; ++$i) {
            $user = $this->createNormalUser("popular2{$i}" . uniqid() . '@example.com', 'password');
            $collect = new ProductCollect();
            $collect->setUserId($user->getUserIdentifier());
            $collect->setSku($sku2);
            $this->repository->save($collect);
        }

        // 为sku3创建1个已取消的收藏（不应计入统计）
        $user = $this->createNormalUser('cancelled' . uniqid() . '@example.com', 'password');
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku3);
        $collect->cancel();
        $this->repository->save($collect);

        // 刷新确保数据已保存
        self::getEntityManager()->flush();

        // 测试获取热门SKU
        $popularSkus = $this->repository->findPopularSkus(10);
        $this->assertGreaterThanOrEqual(2, count($popularSkus));

        // 找到我们创建的SKU并验证它们的数据
        $sku1Found = false;
        $sku2Found = false;

        foreach ($popularSkus as $popularSku) {
            if ((string) $popularSku['skuId'] === $sku1->getId()) {
                $this->assertEquals(3, $popularSku['collectCount']);
                $sku1Found = true;
            } elseif ((string) $popularSku['skuId'] === $sku2->getId()) {
                $this->assertEquals(2, $popularSku['collectCount']);
                $sku2Found = true;
            }
        }

        $this->assertTrue($sku1Found, 'SKU1应该在热门SKU列表中');
        $this->assertTrue($sku2Found, 'SKU2应该在热门SKU列表中');
    }

    public function testFindRecentCollectsByUserId(): void
    {
        $user = $this->createNormalUser('recent' . uniqid() . '@example.com', 'password');

        // 创建多个收藏，模拟不同的创建时间
        $sku1 = $this->createSku();
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku1);
        $this->repository->save($collect1);

        $sku2 = $this->createSku();
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($sku2);
        $this->repository->save($collect2);

        // 创建已取消的收藏（不应包含在结果中）
        $sku3 = $this->createSku();
        $collect3 = new ProductCollect();
        $collect3->setUserId($user->getUserIdentifier());
        $collect3->setSku($sku3);
        $collect3->cancel();
        $this->repository->save($collect3);

        // 测试获取最近收藏
        $recentCollects = $this->repository->findRecentCollectsByUserId($user->getUserIdentifier(), 5);
        $this->assertCount(2, $recentCollects);

        // 验证返回的收藏都是活跃状态
        foreach ($recentCollects as $collect) {
            $this->assertTrue($collect->isActive());
        }

        // 测试限制数量
        $limitedCollects = $this->repository->findRecentCollectsByUserId($user->getUserIdentifier(), 1);
        $this->assertCount(1, $limitedCollects);
    }

    public function testFindTopCollectsByUserId(): void
    {
        $user = $this->createNormalUser('top' . uniqid() . '@example.com', 'password');

        // 创建置顶收藏
        $sku1 = $this->createSku();
        $collect1 = new ProductCollect();
        $collect1->setUserId($user->getUserIdentifier());
        $collect1->setSku($sku1);
        $collect1->setIsTop(true);
        $collect1->setSortNumber(2);
        $this->repository->save($collect1);

        $sku2 = $this->createSku();
        $collect2 = new ProductCollect();
        $collect2->setUserId($user->getUserIdentifier());
        $collect2->setSku($sku2);
        $collect2->setIsTop(true);
        $collect2->setSortNumber(1);
        $this->repository->save($collect2);

        // 创建非置顶收藏（不应包含在结果中）
        $sku3 = $this->createSku();
        $collect3 = new ProductCollect();
        $collect3->setUserId($user->getUserIdentifier());
        $collect3->setSku($sku3);
        $collect3->setIsTop(false);
        $this->repository->save($collect3);

        // 创建置顶但已取消的收藏（不应包含在结果中）
        $sku4 = $this->createSku();
        $collect4 = new ProductCollect();
        $collect4->setUserId($user->getUserIdentifier());
        $collect4->setSku($sku4);
        $collect4->setIsTop(true);
        $collect4->cancel();
        $this->repository->save($collect4);

        // 测试获取置顶收藏
        $topCollects = $this->repository->findTopCollectsByUserId($user->getUserIdentifier(), 5);
        $this->assertCount(2, $topCollects);

        // 验证按排序号升序排列
        $this->assertEquals(1, $topCollects[0]->getSortNumber());
        $this->assertEquals(2, $topCollects[1]->getSortNumber());

        // 验证都是置顶的活跃收藏
        foreach ($topCollects as $collect) {
            $this->assertTrue($collect->isTop());
            $this->assertTrue($collect->isActive());
        }

        // 测试限制数量
        $limitedTopCollects = $this->repository->findTopCollectsByUserId($user->getUserIdentifier(), 1);
        $this->assertCount(1, $limitedTopCollects);
    }

    public function testRemoveByUserIdAndSkuId(): void
    {
        $user = $this->createNormalUser('remove' . uniqid() . '@example.com', 'password');
        $sku = $this->createSku();

        // 创建收藏
        $collect = new ProductCollect();
        $collect->setUserId($user->getUserIdentifier());
        $collect->setSku($sku);
        $this->repository->save($collect);

        // 验证收藏存在
        $found = $this->repository->findOneByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertNotNull($found);

        // 测试删除
        $result = $this->repository->removeByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertTrue($result);

        // 验证收藏已被删除
        $notFound = $this->repository->findOneByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertNull($notFound);

        // 测试删除不存在的收藏
        $result = $this->repository->removeByUserIdAndSkuId($user->getUserIdentifier(), $sku->getId());
        $this->assertFalse($result);
    }
}
