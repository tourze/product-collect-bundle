<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\ProductCollectBundle\Entity\ProductCollect;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCollectBundle\Exception\ProductCollectException;
use Tourze\ProductCollectBundle\Repository\ProductCollectRepository;
use Tourze\ProductCoreBundle\Entity\Sku;

#[Autoconfigure(public: true)]
readonly class ProductCollectService
{
    public function __construct(
        private ProductCollectRepository $productCollectRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function addToCollection(string $userId, Sku $sku, ?string $collectGroup = null, ?string $note = null): ProductCollect
    {
        $existingCollect = $this->productCollectRepository->findOneByUserIdAndSku($userId, $sku);
        if (null !== $existingCollect) {
            if ($existingCollect->isCancelled() || $existingCollect->isHidden()) {
                $existingCollect->activate();
                if (null !== $collectGroup) {
                    $existingCollect->setCollectGroup($collectGroup);
                }
                if (null !== $note) {
                    $existingCollect->setNote($note);
                }
                $this->productCollectRepository->save($existingCollect, true);

                return $existingCollect;
            }

            throw ProductCollectException::alreadyCollected();
        }

        $collect = new ProductCollect();
        $collect->setUserId($userId);
        $collect->setSku($sku);
        $collect->setCollectGroup($collectGroup);
        $collect->setNote($note);

        $this->productCollectRepository->save($collect, true);

        return $collect;
    }

    public function removeFromCollection(string $userId, Sku $sku): void
    {
        $existingCollect = $this->productCollectRepository->findOneByUserIdAndSku($userId, $sku);
        if (null === $existingCollect) {
            throw ProductCollectException::notCollected();
        }

        $this->productCollectRepository->remove($existingCollect, true);
    }

    public function cancelCollection(string $userId, Sku $sku): void
    {
        $existingCollect = $this->productCollectRepository->findOneByUserIdAndSku($userId, $sku);
        if (null === $existingCollect) {
            throw ProductCollectException::notCollected();
        }

        $existingCollect->cancel();
        $this->productCollectRepository->save($existingCollect, true);
    }

    public function restoreCollection(string $userId, Sku $sku): void
    {
        $existingCollect = $this->productCollectRepository->findOneByUserIdAndSku($userId, $sku);
        if (null === $existingCollect) {
            throw ProductCollectException::notCollected();
        }

        $existingCollect->activate();
        $this->productCollectRepository->save($existingCollect, true);
    }

    public function toggleCollection(string $userId, Sku $sku, ?string $collectGroup = null, ?string $note = null): ProductCollect
    {
        $collect = $this->productCollectRepository->findOneByUserIdAndSku($userId, $sku);

        if (null === $collect) {
            return $this->addToCollection($userId, $sku, $collectGroup, $note);
        }

        if ($collect->isActive()) {
            $collect->cancel();
        } else {
            $collect->activate();
        }

        $this->productCollectRepository->save($collect, true);

        return $collect;
    }

    public function isCollected(string $userId, Sku $sku): bool
    {
        $collect = $this->productCollectRepository->findOneByUserIdAndSku($userId, $sku);

        return null !== $collect && $collect->isActive();
    }

    /**
     * @return ProductCollect[]
     */
    public function getUserCollections(string $userId, ?CollectStatus $status = null): array
    {
        return $this->productCollectRepository->findByUserId($userId, $status);
    }

    /**
     * @return ProductCollect[]
     */
    public function getUserActiveCollections(string $userId): array
    {
        return $this->productCollectRepository->findActiveByUserId($userId);
    }

    /**
     * @return ProductCollect[]
     */
    public function getUserCollectionsByGroup(string $userId, ?string $collectGroup = null, ?CollectStatus $status = null): array
    {
        return $this->productCollectRepository->findByUserIdAndGroup($userId, $collectGroup, $status);
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function getUserCollectionGroups(string $userId): array
    {
        return $this->productCollectRepository->findGroupsByUserId($userId);
    }

    public function getUserCollectionCount(string $userId, ?CollectStatus $status = null): int
    {
        return $this->productCollectRepository->countByUserId($userId, $status);
    }

    public function getUserActiveCollectionCount(string $userId): int
    {
        return $this->productCollectRepository->countActiveByUserId($userId);
    }

    /**
     * @return ProductCollect[]
     */
    public function getTopCollections(string $userId, int $limit = 10): array
    {
        return $this->productCollectRepository->findTopCollectsByUserId($userId, $limit);
    }

    /**
     * @return ProductCollect[]
     */
    public function getRecentCollections(string $userId, int $limit = 20): array
    {
        return $this->productCollectRepository->findRecentCollectsByUserId($userId, $limit);
    }

    public function updateCollectionGroup(string $collectId, ?string $collectGroup): void
    {
        $collect = $this->productCollectRepository->find($collectId);
        if (null === $collect) {
            throw ProductCollectException::collectNotFound();
        }

        $collect->setCollectGroup($collectGroup);
        $this->productCollectRepository->save($collect, true);
    }

    public function updateCollectionNote(string $userId, string $skuId, ?string $note): bool
    {
        $collect = $this->productCollectRepository->findOneByUserIdAndSkuId($userId, $skuId);
        if (null === $collect) {
            return false;
        }

        $collect->setNote($note);
        $this->productCollectRepository->save($collect, true);

        return true;
    }

    public function updateCollectionTop(string $userId, string $skuId, bool $isTop = true): bool
    {
        $collect = $this->productCollectRepository->findOneByUserIdAndSkuId($userId, $skuId);
        if (null === $collect) {
            return false;
        }

        $collect->setTop($isTop);
        $this->productCollectRepository->save($collect, true);

        return true;
    }

    public function updateCollectionSort(string $userId, string $skuId, int $sortNumber): bool
    {
        $collect = $this->productCollectRepository->findOneByUserIdAndSkuId($userId, $skuId);
        if (null === $collect) {
            return false;
        }

        $collect->setSortNumber($sortNumber);
        $this->productCollectRepository->save($collect, true);

        return true;
    }

    public function getSkuCollectionCount(string $skuId, ?CollectStatus $status = null): int
    {
        return $this->productCollectRepository->countBySkuId($skuId, $status);
    }

    public function getSkuActiveCollectionCount(string $skuId): int
    {
        return $this->productCollectRepository->countActiveBySkuId($skuId);
    }

    /**
     * @return ProductCollect[]
     */
    public function getSkuCollections(string $skuId, ?CollectStatus $status = null): array
    {
        return $this->productCollectRepository->findCollectsBySkuId($skuId, $status);
    }

    /**
     * @return array<int, array{skuId: string, collectCount: int}>
     */
    public function getPopularSkus(int $limit = 100): array
    {
        return $this->productCollectRepository->findPopularSkus($limit);
    }

    public function cleanupCancelledCollections(int $daysOld = 30): int
    {
        $date = new \DateTime();
        $date->modify("-{$daysOld} days");

        $qb = $this->productCollectRepository->createQueryBuilder('pc');
        $qb->delete()
            ->where('pc.status = :status')
            ->andWhere('pc.updateTime < :date')
            ->setParameter('status', CollectStatus::CANCELLED)
            ->setParameter('date', $date)
        ;

        $result = $qb->getQuery()->execute();

        return is_int($result) ? $result : 0;
    }

    /**
     * @return array{total: int, active: int, cancelled: int, hidden: int}
     */
    public function getCollectionStatistics(string $userId): array
    {
        return [
            'total' => $this->productCollectRepository->countByUserId($userId, null),
            'active' => $this->productCollectRepository->countByUserId($userId, CollectStatus::ACTIVE),
            'cancelled' => $this->productCollectRepository->countByUserId($userId, CollectStatus::CANCELLED),
            'hidden' => $this->productCollectRepository->countByUserId($userId, CollectStatus::HIDDEN),
        ];
    }

    /**
     * @param Sku[] $skus
     *
     * @return ProductCollect[]
     */
    public function batchAddToCollection(string $userId, array $skus, ?string $collectGroup = null): array
    {
        $results = [];

        foreach ($skus as $sku) {
            $existingCollect = $this->productCollectRepository->findOneByUserIdAndSku($userId, $sku);
            if (null === $existingCollect) {
                $collect = new ProductCollect();
                $collect->setUserId($userId);
                $collect->setSku($sku);
                $collect->setCollectGroup($collectGroup);

                $this->productCollectRepository->save($collect, false);
                $results[] = $collect;
            }
        }

        $this->entityManager->flush();

        return $results;
    }

    /**
     * @return array{total_collections: int, active_collections: int, cancelled_collections: int, unique_users: int, unique_skus: int, avg_collections_per_user: float}
     */
    public function getGlobalCollectionStatistics(): array
    {
        $totalCollections = $this->productCollectRepository->count([]);
        $activeCollections = $this->productCollectRepository->count(['status' => CollectStatus::ACTIVE]);
        $cancelledCollections = $this->productCollectRepository->count(['status' => CollectStatus::CANCELLED]);

        $uniqueUsers = (int) $this->entityManager->createQuery('SELECT COUNT(DISTINCT pc.userId) FROM ' . ProductCollect::class . ' pc')
            ->getSingleScalarResult()
        ;

        $uniqueSkus = (int) $this->entityManager->createQuery('SELECT COUNT(DISTINCT IDENTITY(pc.sku)) FROM ' . ProductCollect::class . ' pc')
            ->getSingleScalarResult()
        ;

        return [
            'total_collections' => $totalCollections,
            'active_collections' => $activeCollections,
            'cancelled_collections' => $cancelledCollections,
            'unique_users' => $uniqueUsers,
            'unique_skus' => $uniqueSkus,
            'avg_collections_per_user' => $uniqueUsers > 0 ? round((float) $activeCollections / (float) $uniqueUsers, 2) : 0.0,
        ];
    }
}
