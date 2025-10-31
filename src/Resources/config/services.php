<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tourze\ProductCollectBundle\Controller\Admin\ProductCollectCrudController;
use Tourze\ProductCollectBundle\Repository\ProductCollectRepository;
use Tourze\ProductCollectBundle\Service\AdminMenu;
use Tourze\ProductCollectBundle\Service\ProductCollectService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    // Services
    $services->set(AdminMenu::class);
    $services->set(ProductCollectService::class);

    // Controllers
    $services->set(ProductCollectCrudController::class)
        ->public()
    ;

    // Repository
    $services->set(ProductCollectRepository::class)
        ->tag('doctrine.repository_service')
    ;
};
