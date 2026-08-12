# District WordPress Plugin - Test Suite

This directory contains the test suite for the District WordPress Plugin.

## Running Tests Locally

### Prerequisites

- PHP 8.1 or higher
- Composer

### Installation

Install test dependencies:

```bash
composer install
```

### Running Tests

Run all tests:

```bash
composer run test
```

Run tests with coverage report:

```bash
composer run test:coverage
```

This will generate an HTML coverage report in the `coverage/` directory.

### Test Structure

The test suite includes:

1. **PluginStructureTest** - Smoke tests for plugin file structure and basic integrity
   - Checks that essential files exist
   - Validates composer.json
   - Verifies classes are loadable

2. **PluginInitializationTest** - Tests for plugin initialization
   - Plugin instantiation
   - Constant definitions
   - Singleton pattern

3. **ActivationTest** - Tests for plugin activation and deactivation
   - Activation with/without permissions
   - Rewrite rules flushing
   - Deactivation cleanup

4. **AdminTest** - Tests for admin functionality
   - Admin class instantiation
   - Hook registration
   - Options page rendering

## CI/CD Pipeline

Tests run automatically on:

- Push to `main` or `develop` branches
- Pull requests to `main` or `develop` branches
- Manual workflow dispatch

The GitHub Actions workflow tests against PHP 8.2 and 8.3, and generates coverage reports.

## Adding New Tests

1. Create a new test file in the `tests/` directory
2. Extend the `PluginTestCase` base class
3. Add test methods that start with `test_`
4. Use Brain\Monkey to mock WordPress functions

Example:

```php
<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;

class MyTest extends PluginTestCase {
    public function test_example(): void {
        Functions\expect( 'some_wp_function' )
            ->andReturn( 'expected value' );

        $this->assertEquals( 'expected value', some_wp_function() );
    }
}
```

## Mocking WordPress Functions

This test suite uses [Brain Monkey](https://brain-wp.github.io/nonces/) for mocking WordPress functions. This allows testing the plugin without requiring a full WordPress installation.

Common usage:

```php
// Mock a function to return a value
Functions\expect( 'function_name' )
    ->andReturn( 'return value' );

// Mock with arguments
Functions\expect( 'function_name' )
    ->with( 'arg1', 'arg2' )
    ->andReturn( 'return value' );

// Mock to return null
Functions\expect( 'function_name' )
    ->andReturnNull();

// Expect a function to never be called
Functions\expect( 'function_name' )
    ->never();
```

## Coverage Goals

- Aim for at least 80% code coverage
- Focus on critical paths and business logic
- Write integration tests for hooks and filters
