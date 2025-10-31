<?php

declare(strict_types=1);

namespace Tourze\ProductCollectBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\ProductCollectBundle\ProductCollectBundle;

/**
 * @internal
 */
#[CoversClass(ProductCollectBundle::class)]
#[RunTestsInSeparateProcesses]
final class ProductCollectBundleTest extends AbstractBundleTestCase
{
}
