<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Exception;

class ProductCollectException extends \Exception
{
    public static function skuNotFound(string $skuId): self
    {
        return new self("SKU [{$skuId}] 不存在", 404);
    }

    public static function alreadyCollected(): self
    {
        return new self('该商品已在收藏列表中', 409);
    }

    public static function collectNotFound(): self
    {
        return new self('收藏记录不存在', 404);
    }

    public static function notCollected(): self
    {
        return new self('该商品未在收藏列表中', 404);
    }

    public static function collectionLimitExceeded(int $limit): self
    {
        return new self("收藏数量超过限制 [{$limit}]", 429);
    }

    public static function invalidStatus(string $status): self
    {
        return new self("无效的收藏状态 [{$status}]", 400);
    }

    public static function collectionNotFound(string $collectId): self
    {
        return new self("收藏记录 [{$collectId}] 不存在", 404);
    }
}
