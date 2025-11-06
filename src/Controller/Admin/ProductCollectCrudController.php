<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\ProductCollectBundle\Entity\ProductCollect;
use Tourze\ProductCollectBundle\Enum\CollectStatus;
use Tourze\ProductCollectBundle\Repository\ProductCollectRepository;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * @template-extends AbstractCrudController<ProductCollect>
 */
#[AdminCrud(routePath: '/product-collect/product-collect', routeName: 'product_collect_product_collect')]
#[Autoconfigure(public: true)]
final class ProductCollectCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductCollectRepository $productCollectRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductCollect::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('商品收藏')
            ->setEntityLabelInPlural('商品收藏管理')
            ->setPageTitle('index', '商品收藏列表')
            ->setPageTitle('detail', '收藏详情')
            ->setPageTitle('new', '添加收藏')
            ->setPageTitle('edit', '编辑收藏')
            ->setHelp('index', '管理用户的商品收藏记录，支持按状态、分组、用户等条件筛选')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['id', 'userId', 'collectGroup', 'note'])
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        // 基本字段
        yield TextField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm()
        ;

        yield TextField::new('userId', '用户ID')
            ->setRequired(true)
            ->setHelp('收藏该商品的用户唯一标识')
        ;

        yield AssociationField::new('sku', '商品SKU')
            ->setRequired(true)
            ->autocomplete()
            ->setHelp('被收藏的商品SKU')
            ->formatValue(function ($value) {
                if (!$value instanceof Sku) {
                    return '';
                }

                return sprintf('%s (%s)', $value->getId(), $value->getSpu()?->getTitle() ?? '未知商品');
            })
        ;

        // 状态和分类字段
        yield ChoiceField::new('status', '收藏状态')
            ->setRequired(true)
            ->setFormType(EnumType::class)
            ->setFormTypeOptions(['class' => CollectStatus::class])
            ->formatValue(function ($value) {
                return $value instanceof CollectStatus ? $value->getLabel() : '';
            })
            ->renderAsBadges([
                CollectStatus::ACTIVE->value => 'success',
                CollectStatus::CANCELLED->value => 'warning',
                CollectStatus::HIDDEN->value => 'secondary',
            ])
        ;

        yield TextField::new('collectGroup', '收藏分组')
            ->setMaxLength(50)
            ->setHelp('可选的收藏分组名称，如"我的最爱"、"待购买"等')
        ;

        yield TextareaField::new('note', '收藏备注')
            ->setMaxLength(5000)
            ->hideOnIndex()
            ->setHelp('用户对该收藏的个人备注')
        ;

        // 排序和置顶字段
        yield IntegerField::new('sortNumber', '排序权重')
            ->setHelp('数值越小排序越靠前，默认为0')
        ;

        yield BooleanField::new('isTop', '是否置顶')
            ->renderAsSwitch(false)
            ->setHelp('置顶的收藏会优先显示')
        ;

        // 时间戳字段
        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnIndex()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 添加详情操作
        $actions->add(Crud::PAGE_INDEX, Action::DETAIL);

        // 添加自定义批量操作
        $batchActivate = Action::new('batchActivate', '批量激活')
            ->linkToCrudAction('batchActivate')
            ->addCssClass('btn btn-success')
            ->setIcon('fa fa-check-circle')
        ;

        $batchCancel = Action::new('batchCancel', '批量取消')
            ->linkToCrudAction('batchCancel')
            ->addCssClass('btn btn-warning')
            ->setIcon('fa fa-times-circle')
        ;

        $batchHide = Action::new('batchHide', '批量隐藏')
            ->linkToCrudAction('batchHide')
            ->addCssClass('btn btn-secondary')
            ->setIcon('fa fa-eye-slash')
        ;

        // 添加单个记录操作
        $toggleTop = Action::new('toggleTop', '切换置顶')
            ->linkToCrudAction('toggleTop')
            ->addCssClass('btn btn-info btn-sm')
            ->setIcon('fa fa-star')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $batchActivate)
            ->add(Crud::PAGE_INDEX, $batchCancel)
            ->add(Crud::PAGE_INDEX, $batchHide)
            ->add(Crud::PAGE_INDEX, $toggleTop)
            ->add(Crud::PAGE_DETAIL, $toggleTop)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        // 构建状态选择选项
        $statusChoices = [];
        foreach (CollectStatus::cases() as $case) {
            $statusChoices[$case->getLabel()] = $case->value;
        }

        return $filters
            ->add(TextFilter::new('userId', '用户ID'))
            ->add(EntityFilter::new('sku', '商品SKU'))
            ->add(ChoiceFilter::new('status', '收藏状态')->setChoices($statusChoices))
            ->add(TextFilter::new('collectGroup', '收藏分组'))
            ->add(BooleanFilter::new('isTop', '是否置顶'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
        ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.sku', 'sku')
            ->leftJoin('sku.spu', 'spu')
            ->addSelect('sku', 'spu')
            ->orderBy('entity.isTop', 'DESC')
            ->addOrderBy('entity.sortNumber', 'ASC')
            ->addOrderBy('entity.createTime', 'DESC')
        ;
    }

    /**
     * 切换置顶状态
     */
    #[AdminAction(routePath: '{entityId}/toggleTop', routeName: 'product_collect_toggle_top')]
    public function toggleTop(AdminContext $context, Request $request): Response
    {
        $productCollect = $context->getEntity()->getInstance();
        assert($productCollect instanceof ProductCollect);

        $isTop = !$productCollect->isTop();
        $productCollect->setIsTop($isTop);

        $this->entityManager->flush();

        $status = $isTop ? '置顶' : '取消置顶';
        $this->addFlash('success', sprintf('收藏记录已%s', $status));

        $referer = $context->getRequest()->headers->get('referer');
        if (null === $referer || '' === $referer) {
            $referer = '/admin?crudAction=index&crudControllerFqcn=' . urlencode(self::class);
        }

        return $this->redirect($referer);
    }

    /**
     * 批量激活收藏.
     */
    #[AdminAction(routePath: 'batchActivate', routeName: 'product_collect_batch_activate')]
    public function batchActivate(AdminContext $context, Request $request): Response
    {
        $entityIds = $request->request->all('batchActionEntityIds');

        if ([] === $entityIds) {
            $this->addFlash('warning', '请选择要激活的收藏记录');

            $referer = $context->getRequest()->headers->get('referer');
            if (null === $referer || '' === $referer) {
                $referer = '/admin?crudAction=index&crudControllerFqcn=' . urlencode(self::class);
            }

            return $this->redirect($referer);
        }

        $updatedCount = 0;
        foreach ($entityIds as $entityId) {
            $productCollect = $this->productCollectRepository->find($entityId);
            if (null !== $productCollect) {
                $productCollect->setStatus(CollectStatus::ACTIVE);
                ++$updatedCount;
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('已激活 %d 条收藏记录', $updatedCount));

        $referer = $context->getRequest()->headers->get('referer');
        if (null === $referer || '' === $referer) {
            $referer = '/admin?crudAction=index&crudControllerFqcn=' . urlencode(self::class);
        }

        return $this->redirect($referer);
    }

    /**
     * 批量取消收藏.
     */
    #[AdminAction(routePath: 'batchCancel', routeName: 'product_collect_batch_cancel')]
    public function batchCancel(AdminContext $context, Request $request): Response
    {
        $entityIds = $request->request->all('batchActionEntityIds');

        if ([] === $entityIds) {
            $this->addFlash('warning', '请选择要取消的收藏记录');

            $referer = $context->getRequest()->headers->get('referer');
            if (null === $referer || '' === $referer) {
                $referer = '/admin?crudAction=index&crudControllerFqcn=' . urlencode(self::class);
            }

            return $this->redirect($referer);
        }

        $updatedCount = 0;
        foreach ($entityIds as $entityId) {
            $productCollect = $this->productCollectRepository->find($entityId);
            if (null !== $productCollect) {
                $productCollect->setStatus(CollectStatus::CANCELLED);
                ++$updatedCount;
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('已取消 %d 条收藏记录', $updatedCount));

        $referer = $context->getRequest()->headers->get('referer');
        if (null === $referer || '' === $referer) {
            $referer = '/admin?crudAction=index&crudControllerFqcn=' . urlencode(self::class);
        }

        return $this->redirect($referer);
    }

    /**
     * 批量隐藏收藏.
     */
    #[AdminAction(routePath: 'batchHide', routeName: 'product_collect_batch_hide')]
    public function batchHide(AdminContext $context, Request $request): Response
    {
        $entityIds = $request->request->all('batchActionEntityIds');

        if ([] === $entityIds) {
            $this->addFlash('warning', '请选择要隐藏的收藏记录');

            $referer = $context->getRequest()->headers->get('referer');
            if (null === $referer || '' === $referer) {
                $referer = '/admin?crudAction=index&crudControllerFqcn=' . urlencode(self::class);
            }

            return $this->redirect($referer);
        }

        $updatedCount = 0;
        foreach ($entityIds as $entityId) {
            $productCollect = $this->productCollectRepository->find($entityId);
            if (null !== $productCollect) {
                $productCollect->setStatus(CollectStatus::HIDDEN);
                ++$updatedCount;
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('已隐藏 %d 条收藏记录', $updatedCount));

        $referer = $context->getRequest()->headers->get('referer');
        if (null === $referer || '' === $referer) {
            $referer = '/admin?crudAction=index&crudControllerFqcn=' . urlencode(self::class);
        }

        return $this->redirect($referer);
    }
}
