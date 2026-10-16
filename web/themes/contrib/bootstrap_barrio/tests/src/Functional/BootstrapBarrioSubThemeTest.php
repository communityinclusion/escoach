<?php

declare(strict_types=1);

namespace Drupal\Tests\bootstrap_barrio\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Bootstrap Barrio subtheme.
 *
 * @group bootstrap_barrio
 */
#[Group('bootstrap_barrio')]
#[RunTestsInSeparateProcesses]
class BootstrapBarrioSubThemeTest extends BootstrapBarrioTest {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'bootstrap_barrio_subtheme';

  /**
   * Tests that subtheme inherits settings from parent.
   */
  public function testSubThemeInheritsSettings(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);
    $this->assertSession()->statusCodeEquals(200);

    // Should have Bootstrap settings from parent theme.
    $this->assertSession()->pageTextContains('Bootstrap settings');
  }

  /**
   * Tests that subtheme can override settings.
   */
  public function testSubThemeCanOverrideSettings(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    // Save custom settings.
    $edit = [
      'bootstrap_barrio_fluid_container' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

}
