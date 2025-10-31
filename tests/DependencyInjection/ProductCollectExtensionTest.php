<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\ProductCollectBundle\DependencyInjection\ProductCollectExtension;

/**
 * @internal
 */
#[CoversClass(ProductCollectExtension::class)]
#[Group('integration')]
final class ProductCollectExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
