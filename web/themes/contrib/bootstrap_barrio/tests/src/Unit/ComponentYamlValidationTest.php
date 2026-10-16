<?php

declare(strict_types=1);

namespace Drupal\Tests\bootstrap_barrio\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the SDC component YAML definitions.
 *
 * @group bootstrap_barrio
 */
#[Group('bootstrap_barrio')]
class ComponentYamlValidationTest extends UnitTestCase {

  /**
   * Path to the components directory.
   *
   * @var string
   */
  protected string $componentsPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->componentsPath = dirname(__DIR__, 3) . '/components';
  }

  /**
   * Returns all component directories.
   *
   * @return array
   *   Array of component directory paths.
   */
  protected function getComponentDirectories(): array {
    $directories = [];
    $iterator = new \DirectoryIterator($this->componentsPath);

    foreach ($iterator as $item) {
      if ($item->isDir() && !$item->isDot()) {
        $directories[] = $item->getPathname();
      }
    }

    return $directories;
  }

  /**
   * Tests that all components have required files.
   */
  public function testComponentsHaveRequiredFiles(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);

      // Check for component.yml file.
      $ymlFile = $dir . '/' . $componentName . '.component.yml';
      $this->assertFileExists($ymlFile, "Component '$componentName' should have a .component.yml file");

      // Check for twig template.
      $twigFile = $dir . '/' . $componentName . '.twig';
      $this->assertFileExists($twigFile, "Component '$componentName' should have a .twig template");
    }
  }

  /**
   * Tests that all component YAML files are valid.
   */
  public function testComponentYamlIsValid(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $content = file_get_contents($ymlFile);

      try {
        $parsed = Yaml::parse($content);
        $this->assertIsArray($parsed, "Component '$componentName' YAML should parse to an array");
      }
      catch (\Exception $e) {
        $this->fail("Component '$componentName' has invalid YAML: " . $e->getMessage());
      }
    }
  }

  /**
   * Tests that all components have a name property.
   */
  public function testComponentsHaveName(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      $this->assertArrayHasKey('name', $parsed, "Component '$componentName' should have a 'name' property");
      $this->assertNotEmpty($parsed['name'], "Component '$componentName' name should not be empty");
    }
  }

  /**
   * Tests that all components have a status property.
   */
  public function testComponentsHaveStatus(): void {
    $directories = $this->getComponentDirectories();
    $validStatuses = ['experimental', 'stable', 'deprecated', 'obsolete'];

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      if (isset($parsed['status'])) {
        $this->assertContains(
          $parsed['status'],
          $validStatuses,
          "Component '$componentName' status should be one of: " . implode(', ', $validStatuses)
        );
      }
    }
  }

  /**
   * Tests that components with props have valid JSON Schema structure.
   */
  public function testComponentPropsHaveValidSchema(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      if (isset($parsed['props'])) {
        $this->assertArrayHasKey('type', $parsed['props'], "Component '$componentName' props should have a 'type' property");
        $this->assertEquals('object', $parsed['props']['type'], "Component '$componentName' props type should be 'object'");

        if (isset($parsed['props']['properties'])) {
          $this->assertIsArray($parsed['props']['properties'], "Component '$componentName' props properties should be an array");

          foreach ($parsed['props']['properties'] as $propName => $propDef) {
            $this->assertIsArray($propDef, "Component '$componentName' prop '$propName' should be an array");
          }
        }
      }
    }
  }

  /**
   * Tests that components with slots have valid structure.
   */
  public function testComponentSlotsHaveValidStructure(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      if (isset($parsed['slots'])) {
        $this->assertIsArray($parsed['slots'], "Component '$componentName' slots should be an array");

        foreach ($parsed['slots'] as $slotName => $slotDef) {
          $this->assertIsArray($slotDef, "Component '$componentName' slot '$slotName' should be an array");
          $this->assertArrayHasKey('title', $slotDef, "Component '$componentName' slot '$slotName' should have a 'title'");
        }
      }
    }
  }

  /**
   * Tests Canvas compatibility - components should have description and group.
   */
  public function testCanvasCompatibility(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      // Canvas-compatible components should have description.
      $this->assertArrayHasKey(
        'description',
        $parsed,
        "Component '$componentName' should have a 'description' for Canvas compatibility"
      );

      // Canvas-compatible components should have group.
      $this->assertArrayHasKey(
        'group',
        $parsed,
        "Component '$componentName' should have a 'group' for Canvas compatibility"
      );
    }
  }

  /**
   * Tests that props with enum have corresponding meta:enum for Canvas.
   */
  public function testEnumPropsHaveMetaEnum(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      if (!isset($parsed['props']['properties'])) {
        continue;
      }

      foreach ($parsed['props']['properties'] as $propName => $propDef) {
        if (isset($propDef['enum']) && is_array($propDef['enum']) && count($propDef['enum']) > 1) {
          $this->assertArrayHasKey(
            'meta:enum',
            $propDef,
            "Component '$componentName' prop '$propName' with enum should have 'meta:enum' for Canvas"
          );
        }
      }
    }
  }

  /**
   * Tests that required props have examples for Canvas defaults.
   */
  public function testRequiredPropsHaveExamples(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      if (!isset($parsed['props']['required']) || !isset($parsed['props']['properties'])) {
        continue;
      }

      foreach ($parsed['props']['required'] as $requiredProp) {
        if (isset($parsed['props']['properties'][$requiredProp])) {
          $propDef = $parsed['props']['properties'][$requiredProp];
          $this->assertArrayHasKey(
            'examples',
            $propDef,
            "Component '$componentName' required prop '$requiredProp' should have 'examples' for Canvas"
          );
        }
      }
    }
  }

  /**
   * Tests that array props use 'items' not 'item' (JSON Schema compliance).
   */
  public function testArrayPropsUseItemsNotItem(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      if (!file_exists($ymlFile)) {
        continue;
      }

      $parsed = Yaml::parseFile($ymlFile);

      if (!isset($parsed['props']['properties'])) {
        continue;
      }

      foreach ($parsed['props']['properties'] as $propName => $propDef) {
        if (isset($propDef['type']) && $propDef['type'] === 'array') {
          $this->assertArrayNotHasKey(
            'item',
            $propDef,
            "Component '$componentName' array prop '$propName' should use 'items' not 'item'"
          );
        }
      }
    }
  }

  /**
   * Tests that CSS files exist if referenced.
   */
  public function testCssFilesExist(): void {
    $directories = $this->getComponentDirectories();

    foreach ($directories as $dir) {
      $componentName = basename($dir);
      $cssFile = $dir . '/' . $componentName . '.css';
      $ymlFile = $dir . '/' . $componentName . '.component.yml';

      // CSS file is optional, but if the directory contains one, check it's valid.
      if (file_exists($cssFile)) {
        $this->assertFileIsReadable($cssFile, "Component '$componentName' CSS file should be readable");
        $content = file_get_contents($cssFile);
        $this->assertNotEmpty(trim($content), "Component '$componentName' CSS file should not be empty");
      }
    }
  }

  /**
   * Data provider returning all component names.
   *
   * @return array
   *   Array of component names.
   */
  public static function componentNamesProvider(): array {
    $componentsPath = dirname(__DIR__, 3) . '/components';
    $components = [];

    if (is_dir($componentsPath)) {
      $iterator = new \DirectoryIterator($componentsPath);
      foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isDot()) {
          $components[$item->getFilename()] = [$item->getFilename()];
        }
      }
    }

    return $components;
  }

  /**
   * Tests individual component validity.
   *
   * @dataProvider componentNamesProvider
   */
  #[DataProvider('componentNamesProvider')]
  public function testIndividualComponent(string $componentName): void {
    $dir = $this->componentsPath . '/' . $componentName;
    $ymlFile = $dir . '/' . $componentName . '.component.yml';

    $this->assertFileExists($ymlFile);

    $parsed = Yaml::parseFile($ymlFile);

    $this->assertArrayHasKey('name', $parsed);
    $this->assertNotEmpty($parsed['name']);

    // Ensure props structure is valid if present.
    if (isset($parsed['props'])) {
      $this->assertEquals('object', $parsed['props']['type'] ?? null);
    }
  }

}
