<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Constraint\IsType;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use Tourze\ProductCollectBundle\Service\AdminMenu;
use Tourze\ProductCoreBundle\Service\TagService;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    protected mixed $linkGenerator;

    protected AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        // 使用真实的服务
        $this->adminMenu = self::getService(AdminMenu::class);
    }

    public function testProvideMenu(): void
    {
        $menu = $this->createMock(ItemInterface::class);
        $productMenu = $this->createMock(ItemInterface::class);

        $menu->expects($this->once())
            ->method('getChild')
            ->with('product')
            ->willReturn($productMenu)
        ;

        $productMenu->expects($this->once())
            ->method('addChild')
            ->with('商品收藏', new IsType(IsType::TYPE_ARRAY))
        ;

        $this->adminMenu->provideMenu($menu);
    }

    public function testProvideMenuWhenProductMenuNotExists(): void
    {
        $menu = $this->createMock(ItemInterface::class);

        $menu->expects($this->once())
            ->method('getChild')
            ->with('product')
            ->willReturn(null)
        ;

        $menu->expects($this->never())
            ->method('addChild')
        ;

        $this->adminMenu->provideMenu($menu);
    }
}
