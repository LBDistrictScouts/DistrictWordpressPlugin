<?php

/**
 * Base test case for plugin tests.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin\Tests
 */

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Shared test setup for WordPress plugin smoke tests.
 */
abstract class PluginTestCase extends TestCase {




	/**
	 * Cleans up Brain Monkey after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
