<?php

declare(strict_types=1);

namespace Drupal\Tests\email_registration\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests that the anonymous user is never renamed on save.
 *
 * The anonymous account must keep an empty name so that getDisplayName()
 * falls back to the user.settings:anonymous label. Saving user 0 while
 * email_registration is installed used to rename it to "user", because
 * email_registration_user_presave() generated a username from the empty
 * email address.
 *
 * @group email_registration
 */
class EmailRegistrationAnonymousUserTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'email_registration',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
  }

  /**
   * Tests that saving the anonymous user keeps its name empty.
   */
  public function testAnonymousUserKeepsEmptyNameOnSave(): void {
    $anonymous = User::create([
      'uid' => 0,
      'name' => '',
      'status' => 0,
    ]);
    $anonymous->save();
    $this->assertSame('', $anonymous->getAccountName());

    // Re-save the existing entity, as a module that bulk re-saves entities
    // (e.g. a queue worker) would.
    $anonymous = User::load(0);
    $anonymous->save();
    $this->assertSame('', $anonymous->getAccountName());
    $this->assertSame('Anonymous', $anonymous->getDisplayName());
  }

}
