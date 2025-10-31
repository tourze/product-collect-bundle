<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCollectBundle\Repository\ProductCollectRepository;
use Tourze\ProductCoreBundle\Entity\Sku;

#[ORM\Entity(repositoryClass: ProductCollectRepository::class)]
#[ORM\Table(
    name: 'product_collects',
    options: ['comment' => '商品收藏表']
)]
#[ORM\UniqueConstraint(name: 'uniq_user_sku', columns: ['user_id', 'sku_id'])]
#[ORM\Index(name: 'product_collects_idx_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'product_collects_idx_sort_top', columns: ['is_top', 'sort_number'])]
class ProductCollect implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: false, options: ['comment' => '用户ID'])]
    #[Assert\NotBlank(message: '用户ID不能为空')]
    #[Assert\Length(max: 32, maxMessage: '用户ID长度不能超过{{ limit }}个字符')]
    private ?string $userId = null;

    #[ORM\ManyToOne(targetEntity: Sku::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'SKU不能为空')]
    private ?Sku $sku = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: CollectStatus::class, options: ['comment' => '收藏状态'])]
    #[Assert\NotNull(message: '收藏状态不能为空')]
    #[Assert\Choice(callback: [CollectStatus::class, 'cases'], message: '无效的收藏状态')]
    private CollectStatus $status = CollectStatus::ACTIVE;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '收藏分组'])]
    #[Assert\Length(max: 50, maxMessage: '收藏分组名称长度不能超过{{ limit }}个字符')]
    private ?string $collectGroup = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '收藏备注'])]
    #[Assert\Length(max: 5000, maxMessage: '收藏备注长度不能超过{{ limit }}个字符')]
    private ?string $note = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0, 'comment' => '排序权重'])]
    #[Assert\PositiveOrZero(message: '排序权重必须大于等于0')]
    private int $sortNumber = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false, 'comment' => '是否置顶'])]
    #[Assert\Type(type: 'boolean', message: '置顶标识必须是布尔值')]
    private bool $isTop = false;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '扩展元数据'])]
    #[Assert\Type(type: 'array', message: '扩展元数据必须是数组类型')]
    private ?array $metadata = null;

    public function __toString(): string
    {
        return sprintf('ProductCollect[%s] User:%s SKU:%s', $this->getId(), $this->userId, $this->sku?->getId());
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getSku(): ?Sku
    {
        return $this->sku;
    }

    public function setSku(?Sku $sku): void
    {
        $this->sku = $sku;
    }

    public function getStatus(): CollectStatus
    {
        return $this->status;
    }

    public function setStatus(CollectStatus $status): void
    {
        $this->status = $status;
    }

    public function getCollectGroup(): ?string
    {
        return $this->collectGroup;
    }

    public function setCollectGroup(?string $collectGroup): void
    {
        $this->collectGroup = $collectGroup;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getSortNumber(): int
    {
        return $this->sortNumber;
    }

    public function setSortNumber(int $sortNumber): void
    {
        $this->sortNumber = $sortNumber;
    }

    public function isTop(): bool
    {
        return $this->isTop;
    }

    public function setIsTop(bool $isTop): void
    {
        $this->isTop = $isTop;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function isHidden(): bool
    {
        return $this->status->isHidden();
    }

    public function cancel(): void
    {
        $this->status = CollectStatus::CANCELLED;
    }

    public function hide(): void
    {
        $this->status = CollectStatus::HIDDEN;
    }

    public function activate(): void
    {
        $this->status = CollectStatus::ACTIVE;
    }

    public function setTop(bool $isTop = true): void
    {
        $this->isTop = $isTop;
    }

    public function getSkuId(): ?string
    {
        return $this->sku?->getId();
    }

    public function getSkuName(): ?string
    {
        return $this->sku?->getSpu()?->getTitle();
    }

    public function getSkuThumb(): ?string
    {
        $thumbs = $this->sku?->getThumbs();
        if (null === $thumbs || [] === $thumbs) {
            return null;
        }

        if (!isset($thumbs[0])) {
            return null;
        }

        if (is_array($thumbs[0])) {
            $url = $thumbs[0]['url'] ?? null;

            return is_string($url) ? $url : null;
        }

        if (is_string($thumbs[0])) {
            return $thumbs[0];
        }

        return null;
    }
}
