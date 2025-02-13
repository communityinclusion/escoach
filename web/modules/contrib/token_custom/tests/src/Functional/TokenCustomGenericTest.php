<?php

namespace Drupal\Tests\token_custom\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;

/**
 * Generic module test for token_custom.
 *
 * @group token_custom
 */
class TokenCustomGenericTest extends GenericModuleTestBase {

  /**
   * {@inheritDoc}
   */
  protected function assertHookHelp(string $module): void {
    // Don't do anything here. Just overwrite this useless method, so we do
    // don't have to implement hook_help().
  }

}
