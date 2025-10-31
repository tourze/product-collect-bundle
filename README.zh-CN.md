# ProductCollectBundle

[English](README.md) | [中文](README.zh-CN.md)

商品收藏模块 - 提供用户商品收藏功能

## 功能特性

- 🔖 商品收藏管理（添加、取消、恢复）
- 📁 收藏分组功能
- 📌 置顶收藏支持
- 🔢 自定义排序
- 📊 收藏统计分析
- 🚀 批量操作支持
- 💾 软删除机制

## 安装

```bash
composer require tourze/product-collect-bundle
```

## 使用示例

### 基本收藏操作

```php
use Tourze\ProductCollectBundle\Service\ProductCollectService;

// 添加收藏
$collect = $collectService->addToCollection($userId, $skuId, '我的收藏', '备注信息');

// 切换收藏状态
$result = $collectService->toggleCollection($userId, $skuId);

// 检查是否已收藏
$isCollected = $collectService->isCollected($userId, $skuId);

// 获取用户收藏列表
$collections = $collectService->getUserActiveCollections($userId);
```

### 分组管理

```php
// 按分组获取收藏
$collections = $collectService->getUserCollectionsByGroup($userId, '我的最爱');

// 获取用户所有分组
$groups = $collectService->getUserCollectionGroups($userId);

// 批量移动到分组
$results = $collectService->moveToGroup($userId, [$skuId1, $skuId2], '新分组');
```

### 高级功能

```php
// 获取置顶收藏
$topCollections = $collectService->getTopCollections($userId, 10);

// 获取最近收藏
$recentCollections = $collectService->getRecentCollections($userId, 20);

// 获取热门商品
$popularSkus = $collectService->getPopularSkus(50);

// 收藏统计
$stats = $collectService->getCollectionStatistics();
```

## 实体结构

### ProductCollect

核心收藏实体，包含以下字段：

- `id`: 雪花ID主键
- `userId`: 用户ID（字符串类型）
- `sku`: 关联的SKU实体
- `status`: 收藏状态（活跃/取消/隐藏）
- `collectGroup`: 收藏分组
- `note`: 收藏备注
- `sortNumber`: 排序权重
- `isTop`: 是否置顶
- `metadata`: 扩展元数据
- `createdAt/updatedAt`: 时间戳

### CollectStatus 枚举

- `ACTIVE`: 已收藏
- `CANCELLED`: 已取消
- `HIDDEN`: 已隐藏

## 数据库索引

系统自动创建以下索引以优化查询性能：

- `uniq_user_sku`: 用户和SKU的唯一约束
- `idx_user_status`: 用户ID和状态索引
- `idx_collect_group`: 收藏分组索引
- `idx_sort_top`: 置顶和排序索引
- `idx_created_at`: 创建时间索引

## 许可证

MIT License
