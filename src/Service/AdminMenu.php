<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\ProductCollectBundle\Controller\Admin\ProductCollectCrudController;

/**
 * 商品收藏菜单服务
 */
#[Autoconfigure(public: true)]
class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private readonly LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function provideMenu(ItemInterface $menu): void
    {
        $productMenu = $menu->getChild('product');
        if (null === $productMenu) {
            return;
        }

        $productMenu->addChild('商品收藏', [
            'uri' => $this->linkGenerator->getCurdListPage(ProductCollectCrudController::class),
            'extras' => [
                'icon' => 'fas fa-heart',
                'badge' => null,
            ],
        ]);
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('商品管理')) {
            $item->addChild('商品管理');
        }

        $productMenu = $item->getChild('商品管理');
        if (null === $productMenu) {
            return;
        }

        // 商品收藏管理菜单
        $productMenu->addChild('收藏管理')
            ->setUri($this->linkGenerator->getCurdListPage(ProductCollectCrudController::class))
            ->setAttribute('icon', 'fas fa-heart')
            ->setAttribute('tooltip', '管理用户的商品收藏记录')
        ;
    }
}
