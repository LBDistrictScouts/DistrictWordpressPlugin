# Test Coverage Engine for WordPress Plugin

This document describes the comprehensive test coverage engine set up for the District WordPress Plugin.

## Overview

The plugin uses a multi-layered approach to code quality and testing:

1. **Unit Testing** - PHPUnit with Brain Monkey mocking
2. **Code Coverage** - Automated coverage reporting with minimum thresholds
3. **Static Analysis** - PHPStan for type checking and code analysis
4. **Code Standards** - PHP CodeSniffer with WordPress Coding Standards
5. **PHP Syntax Checking** - Parallel Lint for syntax validation

## Components

### 1. PHPUnit + Code Coverage

**Config**: `phpunit.xml.dist`
**Tool**: PHPUnit 10 with php-code-coverage

#### Features:

- **Test Strictness**: Enforces rigorous test standards
  - `beStrictAboutOutputDuringTests` - No unexpected output
  - `beStrictAboutTestsThatDoNotTestAnything` - All tests must have assertions
  - `beStrictAboutTodoTestedCode` - Can't use incomplete tests
  - `failOnRisky` - Fails on risky tests
  - `failOnWarning` - Fails on all warnings

- **Coverage Reporting**:
  - HTML report with color coding (50%-80% thresholds)
  - Clover XML for CI/CD integration
  - Console output for immediate feedback
  - Includes uncovered files for visibility

- **Coverage Targets**:
  - **Included**: `src/` directory only
  - **Excluded**: Test files, vendor directory
  - **Goal**: Minimum 80% coverage

#### Running locally:

```bash
# Run tests
composer run test

# Generate detailed coverage report
composer run test:coverage

# View coverage report
open coverage/index.html
```

### 2. PHPStan Static Analysis

**Config**: `phpstan.neon.dist`
**Tool**: PHPStan 1.10

#### Features:

- **Analysis Level**: 5 (strict)
- **Type Checking**: Catches type-related bugs before runtime
- **WordPress Support**: Configured with WordPress function stubs
- **Non-blocking in CI**: Reports but doesn't fail the build

#### Running locally:

```bash
composer run phpstan
```

#### What it checks:

- Type compatibility
- Undefined variables
- Wrong function arguments
- Missing return types
- Dead code

### 3. WordPress Coding Standards

**Config**: `.phpcs.xml.dist`
**Tool**: PHP CodeSniffer with WPCS

#### Features:

- **Standards**: Full WordPress Coding Standards ruleset
- **Scope**: Checks both `src/` and `tests/` directories
- **Customized**: Allows for WordPress-specific property names

#### Running locally:

```bash
# Check code standards
composer run phpcs

# Automatically fix fixable issues
composer run phpcs:fix
```

#### What it checks:

- Naming conventions
- Indentation and spacing
- Security practices
- Documentation standards
- WordPress best practices

### 4. PHP Syntax Checking

**Tool**: Parallel Lint

#### Running locally:

```bash
composer run lint
```

#### What it checks:

- PHP syntax errors
- Parse errors
- Files with invalid PHP

## Running Quality Checks

### All checks at once:

```bash
composer run quality
```

This runs:

1. Parallel Lint (syntax check)
2. PHPCS (coding standards)
3. PHPStan (static analysis)
4. PHPUnit (unit tests)

### Individual checks:

```bash
composer run lint         # Syntax check
composer run phpcs        # Coding standards
composer run phpstan      # Static analysis
composer run test         # Unit tests
composer run test:coverage # Tests with coverage report
```

## CI/CD Integration

The GitHub Actions workflow (`.github/workflows/ci-cd.yml`) runs:

1. **Tests** (all PHP versions: 8.1, 8.2, 8.3)
   - Runs full test suite

2. **Code Quality** (PHP 8.1 only)
   - Syntax checking (lint)
   - Static analysis (PHPStan) - `continue-on-error: true`
   - Coding standards (PHPCS) - `continue-on-error: true`

3. **Coverage** (PHP 8.1 only)
   - Generates HTML coverage report
   - Uploads to Codecov
   - Archives as artifact (30 days retention)

### Workflow triggers:

- Push to `main` or `develop` branches
- Pull requests to `main` or `develop`
- Manual dispatch (`workflow_dispatch`)

## Coverage Thresholds

Coverage is measured at the HTML report level with color coding:

- **Red** (< 50%): Critical - needs improvement
- **Yellow** (50-80%): Warning - should improve
- **Green** (> 80%): Good - meets standards

### Target Coverage:

- **Minimum**: 80% for main branches
- **Goal**: 90%+ for critical code paths

## Writing Testable Code

### Guidelines for WordPress plugin code:

1. **Dependency Injection**

   ```php
   // Good - testable
   public function __construct(Logger $logger) {
       $this->logger = $logger;
   }

   // Avoid - hard to test
   public function __construct() {
       $this->logger = new Logger();
   }
   ```

2. **Separate WordPress Dependencies**

   ```php
   // Good - can mock add_action
   public function register_hooks() {
       add_action('init', [$this, 'initialize']);
   }
   ```

3. **Use Interfaces**

   ```php
   interface HookProvider {
       public function register(): void;
   }
   ```

4. **Static Methods for Pure Functions**
   ```php
   public static function validate_data($data) {
       // Pure function - easy to test
   }
   ```

## Troubleshooting

### PHPStan reports false positives with WordPress functions

- These are suppressed in `phpstan.neon.dist`
- Add new function patterns as needed
- Set `reportUnmatchedIgnoredErrors: false` to ignore unused suppressions

### PHPCS reports "WordPress not found"

- Run: `composer install --dev`
- Standards need to be installed via composer

### Coverage report shows 0% coverage

- Check that `phpunit.xml.dist` has correct `<include>` paths
- Verify `processUncoveredFiles="true"` in coverage config
- Run: `composer dump-autoload`

### Tests fail but local coverage passes

- Check PHP version (workflow tests 8.1, 8.2, 8.3)
- Verify all dependencies are installed
- Check for environment-specific issues

## Resources

- [PHPUnit Documentation](https://docs.phpunit.de/en/10.5/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHPStan Documentation](https://phpstan.org/)
- [Brain Monkey Docs](https://github.com/Brain-WP/Monkey)
- [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)

## Next Steps

To improve coverage:

1. **Identify untested code**: Check `coverage/index.html`
2. **Write targeted tests**: Add test cases in `tests/`
3. **Verify mocks**: Ensure WordPress functions are properly mocked
4. **Run coverage report**: `composer run test:coverage`
5. **Iterate**: Repeat until coverage reaches goals
