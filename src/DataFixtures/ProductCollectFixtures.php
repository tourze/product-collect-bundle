<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ProductCollectBundle\Entity\ProductCollect;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;

class ProductCollectFixtures extends Fixture
{
    public const COLLECT_1_REFERENCE = 'collect-1';
    public const COLLECT_2_REFERENCE = 'collect-2';
    public const COLLECT_3_REFERENCE = 'collect-3';

    public function load(ObjectManager $manager): void
    {
        // 创建测试用的Spu和Sku
        $spu1 = new Spu();
        $spu1->setTitle('测试商品A');
        $manager->persist($spu1);

        $spu2 = new Spu();
        $spu2->setTitle('测试商品B');
        $manager->persist($spu2);

        $sku1 = new Sku();
        $sku1->setSpu($spu1);
        $sku1->setGtin('TEST_SKU_001');
        $sku1->setUnit('个');
        $sku1->setValid(true);
        $manager->persist($sku1);

        $sku2 = new Sku();
        $sku2->setSpu($spu2);
        $sku2->setGtin('TEST_SKU_002');
        $sku2->setUnit('件');
        $sku2->setValid(true);
        $manager->persist($sku2);

        $collect1 = new ProductCollect();
        $collect1->setUserId('user_001');
        $collect1->setSku($sku1);
        $collect1->setStatus(CollectStatus::ACTIVE);
        $collect1->setCollectGroup('我的最爱');
        $collect1->setNote('这是一个非常好的商品');
        $collect1->setIsTop(true);
        $collect1->setSortNumber(1);
        $manager->persist($collect1);

        $collect2 = new ProductCollect();
        $collect2->setUserId('user_001');
        $collect2->setSku($sku2);
        $collect2->setStatus(CollectStatus::ACTIVE);
        $collect2->setCollectGroup('待购买');
        $collect2->setNote('等发工资就买');
        $collect2->setIsTop(false);
        $collect2->setSortNumber(2);
        $manager->persist($collect2);

        $collect3 = new ProductCollect();
        $collect3->setUserId('user_002');
        $collect3->setSku($sku1);
        $collect3->setStatus(CollectStatus::CANCELLED);
        $collect3->setCollectGroup(null);
        $collect3->setNote('不想要了');
        $collect3->setIsTop(false);
        $collect3->setSortNumber(0);
        $manager->persist($collect3);

        $manager->flush();

        $this->addReference(self::COLLECT_1_REFERENCE, $collect1);
        $this->addReference(self::COLLECT_2_REFERENCE, $collect2);
        $this->addReference(self::COLLECT_3_REFERENCE, $collect3);
    }
}
