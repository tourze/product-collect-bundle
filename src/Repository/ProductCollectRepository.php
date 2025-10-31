<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductCollectBundle\Entity\ProductCollect;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * @extends ServiceEntityRepository<ProductCollect>
 */
#[AsRepository(entityClass: ProductCollect::class)]
class ProductCollectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCollect::class);
    }

    public function save(ProductCollect $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductCollect $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ProductCollect[]
     */
    public function findByUserId(string $userId, ?CollectStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('pc')
            ->leftJoin('pc.sku', 's')
            ->leftJoin('s.spu', 'spu')
            ->addSelect('s', 'spu')
            ->where('pc.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('pc.isTop', 'DESC')
            ->addOrderBy('pc.sortNumber', 'ASC')
            ->addOrderBy('pc.createTime', 'DESC')
        ;

        if (null !== $status) {
            $qb->andWhere('pc.status = :status')
                ->setParameter('status', $status)
            ;
        }

        /** @var ProductCollect[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return ProductCollect[]
     */
    public function findActiveByUserId(string $userId): array
    {
        return $this->findByUserId($userId, CollectStatus::ACTIVE);
    }

    /**
     * @return ProductCollect[]
     */
    public function findByUserIdAndGroup(string $userId, ?string $collectGroup = null, ?CollectStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('pc')
            ->leftJoin('pc.sku', 's')
            ->leftJoin('s.spu', 'spu')
            ->addSelect('s', 'spu')
            ->where('pc.userId = :userId')
            ->setParameter('userId', $userId)
        ;

        if (null === $collectGroup) {
            $qb->andWhere('pc.collectGroup IS NULL');
        } else {
            $qb->andWhere('pc.collectGroup = :collectGroup')
                ->setParameter('collectGroup', $collectGroup)
            ;
        }

        if (null !== $status) {
            $qb->andWhere('pc.status = :status')
                ->setParameter('status', $status)
            ;
        }

        $qb->orderBy('pc.isTop', 'DESC')
            ->addOrderBy('pc.sortNumber', 'ASC')
            ->addOrderBy('pc.createTime', 'DESC')
        ;

        /** @var ProductCollect[] */
        return $qb->getQuery()->getResult();
    }

    public function findOneByUserIdAndSku(string $userId, Sku $sku): ?ProductCollect
    {
        return $this->findOneBy([
            'userId' => $userId,
            'sku' => $sku,
        ]);
    }

    public function findOneByUserIdAndSkuId(string $userId, string $skuId): ?ProductCollect
    {
        /** @var ProductCollect|null */
        return $this->createQueryBuilder('pc')
            ->where('pc.userId = :userId')
            ->andWhere('pc.sku = :skuId')
            ->setParameter('userId', $userId)
            ->setParameter('skuId', $skuId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countByUserId(string $userId, ?CollectStatus $status = null): int
    {
        $qb = $this->createQueryBuilder('pc')
            ->select('COUNT(pc.id)')
            ->where('pc.userId = :userId')
            ->setParameter('userId', $userId)
        ;

        if (null !== $status) {
            $qb->andWhere('pc.status = :status')
                ->setParameter('status', $status)
            ;
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countActiveByUserId(string $userId): int
    {
        return $this->countByUserId($userId, CollectStatus::ACTIVE);
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function findGroupsByUserId(string $userId): array
    {
        $result = $this->createQueryBuilder('pc')
            ->select('DISTINCT pc.collectGroup as groupName, COUNT(pc.id) as collectCount')
            ->where('pc.userId = :userId')
            ->andWhere('pc.status = :status')
            ->andWhere('pc.collectGroup IS NOT NULL')
            ->setParameter('userId', $userId)
            ->setParameter('status', CollectStatus::ACTIVE)
            ->groupBy('pc.collectGroup')
            ->orderBy('collectCount', 'DESC')
            ->addOrderBy('pc.collectGroup', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        if (!is_array($result)) {
            return [];
        }

        /** @var array<array{name: string, count: int}> */
        $mappedResult = array_map(function (mixed $row): array {
            if (!is_array($row)) {
                return ['name' => '', 'count' => 0];
            }

            $groupName = $row['groupName'] ?? '';
            $collectCount = $row['collectCount'] ?? 0;

            return [
                'name' => is_string($groupName) ? $groupName : '',
                'count' => is_int($collectCount) ? $collectCount : 0,
            ];
        }, $result);

        return array_values($mappedResult);
    }

    /**
     * @return ProductCollect[]
     */
    public function findTopCollectsByUserId(string $userId, int $limit = 10): array
    {
        /** @var ProductCollect[] */
        return $this->createQueryBuilder('pc')
            ->leftJoin('pc.sku', 's')
            ->leftJoin('s.spu', 'spu')
            ->addSelect('s', 'spu')
            ->where('pc.userId = :userId')
            ->andWhere('pc.status = :status')
            ->andWhere('pc.isTop = :isTop')
            ->setParameter('userId', $userId)
            ->setParameter('status', CollectStatus::ACTIVE)
            ->setParameter('isTop', true)
            ->orderBy('pc.sortNumber', 'ASC')
            ->addOrderBy('pc.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return ProductCollect[]
     */
    public function findRecentCollectsByUserId(string $userId, int $limit = 20): array
    {
        /** @var ProductCollect[] */
        return $this->createQueryBuilder('pc')
            ->leftJoin('pc.sku', 's')
            ->leftJoin('s.spu', 'spu')
            ->addSelect('s', 'spu')
            ->where('pc.userId = :userId')
            ->andWhere('pc.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', CollectStatus::ACTIVE)
            ->orderBy('pc.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function createUserCollectsQueryBuilder(string $userId, ?CollectStatus $status = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('pc')
            ->leftJoin('pc.sku', 's')
            ->leftJoin('s.spu', 'spu')
            ->addSelect('s', 'spu')
            ->where('pc.userId = :userId')
            ->setParameter('userId', $userId)
        ;

        if (null !== $status) {
            $qb->andWhere('pc.status = :status')
                ->setParameter('status', $status)
            ;
        }

        return $qb;
    }

    /**
     * @return array<int, array{skuId: string, collectCount: int}>
     */
    public function findPopularSkus(int $limit = 100): array
    {
        /** @var array<int, array{skuId: string, collectCount: int}> */
        return $this->createQueryBuilder('pc')
            ->select('s.id as skuId, COUNT(pc.id) as collectCount')
            ->leftJoin('pc.sku', 's')
            ->where('pc.status = :status')
            ->setParameter('status', CollectStatus::ACTIVE)
            ->groupBy('s.id')
            ->orderBy('collectCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return ProductCollect[]
     */
    public function findCollectsBySkuId(string $skuId, ?CollectStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('pc')
            ->where('pc.sku = :skuId')
            ->setParameter('skuId', $skuId)
        ;

        if (null !== $status) {
            $qb->andWhere('pc.status = :status')
                ->setParameter('status', $status)
            ;
        }

        $qb->orderBy('pc.createTime', 'DESC');

        /** @var ProductCollect[] */
        return $qb->getQuery()->getResult();
    }

    public function countBySkuId(string $skuId, ?CollectStatus $status = null): int
    {
        $qb = $this->createQueryBuilder('pc')
            ->select('COUNT(pc.id)')
            ->where('pc.sku = :skuId')
            ->setParameter('skuId', $skuId)
        ;

        if (null !== $status) {
            $qb->andWhere('pc.status = :status')
                ->setParameter('status', $status)
            ;
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countActiveBySkuId(string $skuId): int
    {
        return $this->countBySkuId($skuId, CollectStatus::ACTIVE);
    }

    public function removeByUserIdAndSkuId(string $userId, string $skuId): bool
    {
        $collect = $this->findOneByUserIdAndSkuId($userId, $skuId);
        if (null === $collect) {
            return false;
        }

        $this->remove($collect, true);

        return true;
    }

    public function cancelByUserIdAndSkuId(string $userId, string $skuId): bool
    {
        $collect = $this->findOneByUserIdAndSkuId($userId, $skuId);
        if (null === $collect) {
            return false;
        }

        $collect->cancel();
        $this->save($collect, true);

        return true;
    }

    public function activateByUserIdAndSkuId(string $userId, string $skuId): bool
    {
        $collect = $this->findOneByUserIdAndSkuId($userId, $skuId);
        if (null === $collect) {
            return false;
        }

        $collect->activate();
        $this->save($collect, true);

        return true;
    }
}
