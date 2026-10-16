# Bootstrap Barrio Theme Tests

This directory contains the automated test suite for the Bootstrap Barrio Drupal theme.

## Test Structure

```
tests/
├── phpunit.xml              # PHPUnit configuration
├── README.md                # This file
└── src/
    ├── Functional/          # Browser-based functional tests
    │   ├── BootstrapBarrioTest.php
    │   └── BootstrapBarrioSubThemeTest.php
    ├── Kernel/              # Kernel tests (partial bootstrap)
    │   ├── ThemeSettingsTest.php
    │   └── PreprocessFunctionsTest.php
    └── Unit/                # Unit tests (no Drupal bootstrap)
        ├── ColorUtilityTest.php
        └── ComponentYamlValidationTest.php
```

## Test Types

### Unit Tests

Unit tests run without Drupal bootstrap and test isolated functions:

- **ColorUtilityTest**: Tests color utility functions (`_hex2rgb`, `_hex2rgba`, `_color_luminance`, `_getContrastColor`, `_get_color`)
- **ComponentYamlValidationTest**: Validates SDC component YAML files for schema compliance and Canvas compatibility

### Kernel Tests

Kernel tests use partial Drupal bootstrap:

- **ThemeSettingsTest**: Tests theme installation, regions, libraries, and settings
- **PreprocessFunctionsTest**: Tests theme preprocess hook functions

### Functional Tests

Functional tests use a full Drupal installation with a web browser:

- **BootstrapBarrioTest**: Tests theme settings pages, page rendering, and configuration
- **BootstrapBarrioSubThemeTest**: Tests subtheme functionality and inheritance

## Running Tests

### Prerequisites

1. Install dev dependencies:
   ```bash
   composer install
   ```

2. Ensure you have PHPUnit installed (included with Drupal core dev dependencies)

### Run All Tests

From the Drupal root directory:

```bash
# Run all Bootstrap Barrio tests
./vendor/bin/phpunit themes/contrib/bootstrap_barrio/tests

# Or using the theme's phpunit.xml
cd themes/contrib/bootstrap_barrio/tests
../../../../vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Unit tests only
./vendor/bin/phpunit themes/contrib/bootstrap_barrio/tests/src/Unit

# Kernel tests only
./vendor/bin/phpunit themes/contrib/bootstrap_barrio/tests/src/Kernel

# Functional tests only
./vendor/bin/phpunit themes/contrib/bootstrap_barrio/tests/src/Functional

# Single test file
./vendor/bin/phpunit themes/contrib/bootstrap_barrio/tests/src/Unit/ColorUtilityTest.php
```

### Run Tests by Group

```bash
# All bootstrap_barrio tests
./vendor/bin/phpunit --group bootstrap_barrio
```

## Test Coverage

The test suite covers:

### Color Utilities
- Hex to RGB conversion
- Hex to RGBA conversion
- Color luminance adjustment (lighten/darken)
- Contrast color calculation (WCAG accessibility)
- Color lookup/fallback

### Theme Configuration
- Theme installation
- Region definitions
- Library attachments
- Core version requirements
- Theme settings schema

### Preprocess Functions
- HTML preprocess (layout classes)
- Page preprocess (navbar, sidebars)
- Menu preprocess
- Table preprocess
- Status messages preprocess

### SDC Components
- YAML file validation
- Required files (component.yml, .twig)
- Props schema structure
- Slots structure
- Canvas compatibility (description, group, examples, meta:enum)
- JSON Schema compliance

### Functional Behavior
- Theme settings page
- Color scheme settings
- Navbar configuration
- Page rendering (front, 404, 403, maintenance)
- Body classes
- Meta tags

## Adding New Tests

1. Create test class in appropriate directory (Unit, Kernel, or Functional)
2. Extend the appropriate base class:
   - `Drupal\Tests\UnitTestCase` for unit tests
   - `Drupal\KernelTests\KernelTestBase` for kernel tests
   - `Drupal\Tests\BrowserTestBase` for functional tests
3. Add `@group bootstrap_barrio` annotation
4. Follow existing test patterns

## CI/CD Integration

These tests are designed to run in CI/CD pipelines. For GitHub Actions, GitLab CI, or similar:

```yaml
# Example GitHub Actions step
- name: Run Bootstrap Barrio tests
  run: |
    ./vendor/bin/phpunit --group bootstrap_barrio --coverage-text
```

## Troubleshooting

### Unit tests fail to find theme functions

Ensure the theme file is included in setUp():

```php
require_once dirname(__DIR__, 3) . '/bootstrap_barrio.theme';
```

### Kernel tests fail with missing tables

Install required schemas in setUp():

```php
$this->installEntitySchema('user');
$this->installConfig(['system']);
```

### Functional tests timeout

Increase PHPUnit timeout or optimize tests:

```xml
<phpunit ... timeoutForSmallTests="30" ...>
```
