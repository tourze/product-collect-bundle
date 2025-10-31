<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum CollectStatus: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case HIDDEN = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => '已收藏',
            self::CANCELLED => '已取消',
            self::HIDDEN => '已隐藏',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this;
    }

    public function isHidden(): bool
    {
        return self::HIDDEN === $this;
    }

    public function getName(): string
    {
        return $this->getLabel();
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::CANCELLED => 'secondary',
            self::HIDDEN => 'warning',
        };
    }
}
