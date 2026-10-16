<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Connection\AsciiAuthenticator;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheException;
use Drupal\memcache\MemcacheSettings;

/**
 * Tests ASCII protocol authentication against a memcached started with -Y.
 *
 * Needs a server that requires authentication, given as "host:port" in the
 * MEMCACHED_AUTH_HOST environment variable, whose authfile contains the pair
 * in MEMCACHED_AUTH_USER / MEMCACHED_AUTH_PASSWORD (default drupal:secret),
 * and the usual MEMCACHED_HOST server that does not require any.
 *
 * @group memcache
 *
 * @see \Drupal\memcache\Connection\AsciiAuthenticator
 * @see https://www.drupal.org/project/memcache/issues/3150893
 */
class MemcacheAsciiAuthTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * The server that requires authentication.
   *
   * @var string
   */
  protected $authHost;

  /**
   * The credentials the server's authfile contains.
   *
   * @var string[]
   */
  protected $credentials;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!class_exists('Memcached')) {
      $this->markTestSkipped('The Memcached PECL extension is not installed.');
    }
    $this->authHost = (string) getenv('MEMCACHED_AUTH_HOST');
    if ($this->authHost === '') {
      $this->markTestSkipped('Set MEMCACHED_AUTH_HOST to a memcached started with -Y to run the ASCII authentication tests.');
    }
    $this->credentials = [
      'username' => getenv('MEMCACHED_AUTH_USER') ?: 'drupal',
      'password' => getenv('MEMCACHED_AUTH_PASSWORD') ?: 'secret',
    ];
  }

  /**
   * Builds a driver factory for the given server and settings.
   *
   * @param string $server
   *   The server address.
   * @param array $settings
   *   Extra $settings['memcache'] values.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The configured factory.
   */
  protected function factoryFor($server, array $settings = []) {
    $this->setSetting('memcache', $settings + [
      'extension' => \Memcached::class,
      'servers' => [$server => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
    ]);
    $memcache_settings = new MemcacheSettings(Settings::getInstance());
    // The requirements read the settings service, which was built at boot.
    $this->container->set('memcache.settings', $memcache_settings);
    $factory = new MemcacheDriverFactory($memcache_settings);
    $this->container->set('memcache.factory', $factory);

    return $factory;
  }

  /**
   * Returns the ASCII authentication requirement row for the current settings.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory $factory
   *   The factory built by factoryFor().
   *
   * @return array
   *   The 'memcache_ascii_auth' requirement.
   */
  protected function requirement(MemcacheDriverFactory $factory) {
    $this->container->get('module_handler')->loadInclude('memcache', 'install');
    $requirements = _memcache_requirements_ascii_auth($factory);
    $this->assertArrayHasKey('memcache_ascii_auth', $requirements);
    return $requirements['memcache_ascii_auth'];
  }

  /**
   * Tests that the server refuses a client without credentials.
   *
   * This is the situation the setting exists for; it also shows why the
   * driver cannot rely on reads to detect a missing authentication.
   */
  public function testAnonymousClientIsRefused() {
    $driver = $this->factoryFor($this->authHost)->get('default');

    $this->assertFalse($driver->set('foo', 'bar'), 'Writes are refused.');
    $this->assertSame(\Memcached::RES_CLIENT_ERROR, $driver->getMemcache()->getResultCode());
    $this->assertFalse($driver->get('foo'), 'Reads look like misses.');
    $this->assertSame([], $driver->getMulti(['foo']));
  }

  /**
   * Tests that the configured credentials make the server usable.
   */
  public function testAuthenticatedRoundTrip() {
    $factory = $this->factoryFor($this->authHost, ['ascii_auth' => $this->credentials]);
    $driver = $factory->get('default');

    $this->assertTrue($driver->set('foo', 'bar'));
    $this->assertSame('bar', $driver->get('foo'));
    $this->assertTrue($driver->add('lock', 1));
    $this->assertFalse($driver->add('lock', 1), 'add() on an existing key still reports the normal outcome.');
    $this->assertSame(['foo' => 'bar', 'lock' => 1], $driver->getMulti(['foo', 'lock', 'missing']));
    $this->assertTrue($driver->delete('foo'));
    $this->assertFalse($driver->get('foo'));
    $this->assertTrue($driver->flush());

    $this->assertSame([$this->authHost => TRUE], $factory->checkServers());
    $this->assertSame([$this->authHost => 'authenticated'], $factory->checkAsciiAuthentication());
    $stats = $factory->getServerStats();
    $this->assertIsArray($stats[$this->authHost], 'Statistics are readable over an authenticated connection.');
  }

  /**
   * Tests that the credentials never end up stored in the cache.
   *
   * On a connection that is already authenticated the authentication "set"
   * is an ordinary write, which would store "username password" under a key
   * that any authenticated client can read.
   */
  public function testCredentialsAreNotStored() {
    $driver = $this->factoryFor($this->authHost, ['ascii_auth' => $this->credentials])->get('default');
    $memcache = $driver->getMemcache();

    $this->assertFalse($memcache->get(AsciiAuthenticator::AUTH_KEY));

    // Authenticate again over the already authenticated socket.
    $authenticator = AsciiAuthenticator::fromSettings($this->container->get('memcache.settings'));
    $this->assertTrue($authenticator->authenticateAll($memcache));
    $this->assertFalse($memcache->get(AsciiAuthenticator::AUTH_KEY));
  }

  /**
   * Tests that a socket libmemcached reopened is authenticated again.
   */
  public function testReconnectIsReauthenticated() {
    $driver = $this->factoryFor($this->authHost, ['ascii_auth' => $this->credentials])->get('default');
    $this->assertTrue($driver->set('foo', 'bar'));

    // quit() closes the socket; the next command opens a fresh, unauthenticated
    // one, as happens after a server restart.
    $driver->getMemcache()->quit();
    $this->assertTrue($driver->set('after', 'reconnect'), 'A refused write is retried after authenticating.');
    $this->assertSame('reconnect', $driver->get('after'));
    $this->assertSame('bar', $driver->get('foo'));

    $driver->getMemcache()->quit();
    $this->assertTrue($driver->delete('foo'), 'A refused delete is retried after authenticating.');
    $this->assertFalse($driver->get('foo'));

    $driver->getMemcache()->quit();
    $this->assertTrue($driver->add('added', 1), 'A refused add is retried after authenticating.');

    $driver->getMemcache()->quit();
    $this->assertTrue($driver->flush(), 'A refused flush is retried after authenticating.');
    $this->assertFalse($driver->get('added'));
  }

  /**
   * Tests that wrong credentials make the server count as unreachable.
   */
  public function testWrongCredentials() {
    $factory = $this->factoryFor($this->authHost, ['ascii_auth' => ['username' => 'drupal', 'password' => 'wrong']]);

    $this->assertSame([$this->authHost => 'rejected'], $factory->checkAsciiAuthentication());

    $requirement = $this->requirement($factory);
    $this->assertSame(REQUIREMENT_ERROR, $requirement['severity']);
    $this->assertStringContainsString('refused the user drupal', strip_tags((string) $requirement['description']));

    $this->expectException(MemcacheException::class);
    $this->expectExceptionMessage('ASCII authentication is enabled');
    $factory->get('default');
  }

  /**
   * Tests the status report with a server that authenticated.
   */
  public function testRequirementsAuthenticated() {
    $requirement = $this->requirement($this->factoryFor($this->authHost, ['ascii_auth' => $this->credentials]));

    $this->assertContains($requirement['severity'], [REQUIREMENT_OK, REQUIREMENT_WARNING]);
    $this->assertStringContainsString('Authenticated as ' . $this->credentials['username'], strip_tags((string) $requirement['value']));
  }

  /**
   * Tests that a server which does not ask for credentials is reported.
   */
  public function testRequirementsServerWithoutAuthentication() {
    $server = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $factory = $this->factoryFor($server, ['ascii_auth' => $this->credentials]);

    $this->assertSame([$server => 'not_required'], $factory->checkAsciiAuthentication());
    $requirement = $this->requirement($factory);
    $this->assertSame(REQUIREMENT_WARNING, $requirement['severity']);
    $this->assertSame('Not enforced', (string) $requirement['value']);

    // The cache itself keeps working: the authentication "set" is just a
    // write the server accepts and the module deletes again.
    $driver = $factory->get('default');
    $this->assertTrue($driver->set('foo', 'bar'));
    $this->assertSame('bar', $driver->get('foo'));
    $this->assertFalse($driver->getMemcache()->get(AsciiAuthenticator::AUTH_KEY));
  }

  /**
   * Tests that settings the server cannot honour together are reported.
   */
  public function testRequirementsConflictingSettings() {
    $factory = $this->factoryFor($this->authHost, [
      'ascii_auth' => $this->credentials,
      'options' => [\Memcached::OPT_BINARY_PROTOCOL => TRUE],
    ]);

    $requirement = $this->requirement($factory);
    $this->assertSame(REQUIREMENT_ERROR, $requirement['severity']);
    $this->assertSame('Conflicting settings', (string) $requirement['value']);
  }

  /**
   * Tests that a malformed setting is rejected rather than sent.
   */
  public function testMalformedSetting() {
    foreach (['drupal secret', ['username' => 'dru pal', 'password' => 'x'], ['username' => 'drupal']] as $value) {
      $this->setSetting('memcache', ['ascii_auth' => $value]);
      try {
        AsciiAuthenticator::fromSettings(new MemcacheSettings(Settings::getInstance()));
        $this->fail('A malformed ascii_auth setting is rejected.');
      }
      catch (MemcacheException $e) {
        $this->assertStringContainsString('ascii_auth', $e->getMessage());
      }
    }
  }

  /**
   * Tests that the credentials are part of the persistent pool identity.
   *
   * Otherwise a pool authenticated with one set of credentials would be
   * reused after the credentials changed in settings.php.
   */
  public function testPersistentPoolIdIncludesCredentials() {
    $id = function (array $settings) {
      $factory = $this->factoryFor($this->authHost, $settings + ['persistent' => TRUE]);
      $method = new \ReflectionMethod($factory, 'getPersistentId');
      $method->setAccessible(TRUE);
      return $method->invoke($factory, 'default');
    };

    $with_credentials = $id(['ascii_auth' => $this->credentials]);
    $other_password = $id([
      'ascii_auth' => ['username' => 'drupal', 'password' => 'other'],
    ]);

    $this->assertNotSame($id([]), $with_credentials);
    $this->assertNotSame($with_credentials, $other_password);
  }

}
