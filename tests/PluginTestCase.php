<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

abstract class PluginTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
