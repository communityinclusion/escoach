# Memcache

Serves Drupal's caches from [memcached](https://memcached.org/), and optionally
its locks and flood control too.

- [Requirements](#requirements)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
- [Settings reference](#settings-reference)
- [Common setups](#common-setups)
  - [More than one memcached server](#more-than-one-memcached-server)
  - [Sharing memcached between sites](#sharing-memcached-between-sites)
  - [More than one web server](#more-than-one-web-server)
  - [When a server goes down](#when-a-server-goes-down)
  - [Persistent connections](#persistent-connections)
  - [Authentication](#authentication)
  - [Switching memcache off on an environment](#switching-memcache-off-on-an-environment)
  - [Behind a proxy, or if you need replication](#behind-a-proxy-or-if-you-need-replication)
  - [A private memcached on every web server](#a-private-memcached-on-every-web-server)
- [Optional services: locks, flood control, cache tags](#optional-services-locks-flood-control-cache-tags)
- [Advanced: caching the container in memcache](#advanced-caching-the-container-in-memcache)
- [Operating memcache](#operating-memcache)
  - [Status report](#status-report)
  - [Memcache Admin](#memcache-admin)
  - [Debugging](#debugging)
- [Troubleshooting](#troubleshooting)
- [Notes for developers](#notes-for-developers)
- [Appendix: the PECL memcache extension](#appendix-the-pecl-memcache-extension)
- [Appendix: network and key tuning](#appendix-network-and-key-tuning)

## Requirements

- Drupal 9.5, 10 or 11.
- PHP 8.1 or later.
- A memcached server (1.6 or later recommended).
- The [memcached PECL extension](https://pecl.php.net/package/memcached),
  **3.2.0 or later**. Earlier releases silently break cache clears on PHP 8.1+
  (they store the timestamps this module relies on with the wrong sign); the
  status report reports such a build as an error. This includes distribution
  packages that call themselves 3.1.5 with PHP 8.1 support patched in.

The older PECL `memcache` extension (no "d") still works but is deprecated and
lacks most of the features below; see the [appendix](#appendix-the-pecl-memcache-extension).

## Quick start

For a single site talking to one memcached on the same machine, this is the
whole setup:

1. Install the memcached server and the PHP extension, and restart PHP.
2. Enable the module: `drush pm:enable memcache`.
3. Add to `settings.php`:
   ```php
   $settings['cache']['default'] = 'cache.backend.memcache';
   ```
4. Rebuild caches (`drush cr`) and open Reports > Status report. The
   "Memcache servers" row should read "1 server available".

That is it. The module connects to `127.0.0.1:11211` unless told otherwise.

If memcached runs elsewhere, or you want a prefix so several sites can share
it, add those two lines as well:
```php
$settings['memcache']['servers'] = ['cache.internal:11211' => 'default'];
$settings['memcache']['key_prefix'] = 'mysite';
```

Two things to know before going further:

- **Enable the module before pointing `$settings['cache']` at it.** The
  `cache.backend.memcache` service only exists once the module is enabled;
  set the cache setting first and Drupal fails with "You have requested a
  non-existent service". If you need both in one deploy step, or want to
  install Drupal with memcache already configured, see
  [Configuring memcache before the module is enabled](#configuring-memcache-before-the-module-is-enabled).
- **Not every bin moves to memcache with that one line**, and that is fine.
  Core's `bootstrap`, `config`, `discovery` and `routes` bins use the
  "chained fast" backend: APCu in front, with your default backend (now
  memcache) behind it. See [Which bins go where](#which-bins-go-where) if you
  want to change that.

## How it works

You do not need this section to run memcache, but it makes the rest of the
file easier to follow.

**Servers, clusters, bins.** A *bin* is one of Drupal's caches (`render`,
`data`, `entity`, ...). A *cluster* is a named group of memcached servers that
act as one memory pool. `$settings['memcache']['servers']` maps each server
to a cluster; `$settings['memcache']['bins']` maps each bin to a cluster. Bins
that are not listed use the `default` cluster, so most sites never touch the
`bins` setting.

**Sharding, not replication.** When a cluster has several servers, each key
is stored on exactly one of them, chosen by hashing the key. Adding a server
adds capacity; losing one loses its share of the cache until it is rebuilt.
Nothing is copied between servers. If you need every key on every server, put
a replicating proxy in front (see
[Behind a proxy](#behind-a-proxy-or-if-you-need-replication)).

**Invalidation by timestamp.** memcached cannot delete "everything in this
bin" or "everything tagged `node:1`". Instead the module stores a timestamp
per bin (and, optionally, per cache tag) and treats an item as valid only if
it was written after the newest timestamp that applies to it. `drush cr`, a
cache clear, or an entity save therefore *writes a timestamp*; the old items
stay in memcached until they expire or are evicted, but are never served.

**Everything is stored permanently, with its own expiry recorded.** The module
does not use memcached's expiry, so an expired item can still be returned to
code that asks for invalid items (`$allow_invalid`), as core's backends do.

**Consequences.** Every web server must use the same server list, the same
cluster mapping and the same `key_prefix`, and their clocks must agree; see
[More than one web server](#more-than-one-web-server). Everything is
configured in `settings.php`; the module has no configuration UI.

## Settings reference

Everything lives under `$settings['memcache']` in `settings.php`. The
sections linked in the last column explain when to change a value.

| Key | Default | Meaning |
|---|---|---|
| `servers` | `['127.0.0.1:11211' => 'default']` | Servers and the cluster each belongs to. [More than one server](#more-than-one-memcached-server) |
| `bins` | `['default' => 'default']` | Bins and the cluster each uses. Unlisted bins use `default`. [More than one server](#more-than-one-memcached-server) |
| `key_prefix` | `''` | Prefix on every key, to share a memcached between sites. [Sharing memcached](#sharing-memcached-between-sites) |
| `extension` | auto | `'Memcached'` or `'Memcache'`; which PHP extension to use when both are installed. |
| `options` | see below | `\Memcached` options applied to every connection. [Appendix](#appendix-network-and-key-tuning) |
| `persistent` | `FALSE` | Keep connections open between requests. [Persistent connections](#persistent-connections) |
| `persistent_id` | `'drupal_memcache'` | Label for the persistent pool. [Persistent connections](#persistent-connections) |
| `ascii_auth` | unset | `['username' => …, 'password' => …]` for servers started with `-Y`. [Authentication](#authentication) |
| `sasl` | unset | `['username' => …, 'password' => …]` for SASL over the binary protocol (deprecated upstream). [Authentication](#authentication) |
| `failure_log_interval` | `60` | Seconds between "server unreachable" log entries across the PHP pool (needs APCu); `0` logs once per request. [When a server goes down](#when-a-server-goes-down) |
| `status_connect_timeout` | `1000` | Milliseconds the status report waits for each server. [Status report](#status-report) |
| `report_evictions` | `TRUE` | Warn on the status report when a server has evicted items. [Status report](#status-report) |
| `collect_statistics` | `FALSE` | Record every memcache operation for the Memcache Admin per-page table. Debugging only. [Debugging](#debugging) |
| `debug` | `FALSE` | Log every cache operation and every failure. [Debugging](#debugging) |
| `key_hash_algorithm` | `'sha1'` | Hash used for keys over 250 bytes. [Appendix](#appendix-network-and-key-tuning) |
| `invalidate_all_as_delete` | `TRUE` | Treat the deprecated `invalidateAll()` as `deleteAll()`. [Notes for developers](#notes-for-developers) |

Default `options` for the Memcached extension, chosen through performance
testing; anything you set is merged over them:
```php
$settings['memcache']['options'] = [
  \Memcached::OPT_COMPRESSION => TRUE,
  \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT,
];
```
Every `\Memcached::OPT_*` constant is accepted:
https://www.php.net/manual/en/memcached.constants.php

Beyond `settings.php`, three things are configured through your site's
`services.yml` instead, because they replace core services: locks, flood
control and cache-tag checksums. See
[Optional services](#optional-services-locks-flood-control-cache-tags).

## Common setups

### More than one memcached server

List every server with the cluster it belongs to. All of these forms work:
```php
$settings['memcache']['servers'] = [
  '127.0.0.1:11211' => 'default',        // host and port
  '127.0.0.1' => 'default',              // port defaults to 11211
  'cache1.internal:11211' => 'default',  // hostname
  '[2001:db8::1]:11211' => 'default',    // IPv6 with a port needs brackets
  '[::1]' => 'default',                  // IPv6, default port
  'unix:///var/run/memcached.sock' => 'default',
];
```

Servers in the same cluster shard the data between them (see
[How it works](#how-it-works)). To keep some bins on separate servers, name a
second cluster and map the bins to it:
```php
$settings['memcache'] = [
  'servers' => [
    'cache1.internal:11211' => 'default',
    'cache2.internal:11211' => 'default',
    'render.internal:11211' => 'render',
  ],
  'bins' => [
    'default' => 'default',
    'render' => 'render',
    'dynamic_page_cache' => 'render',
  ],
];
```
If no server is assigned to a cluster called `default`, unlisted bins fall
back to the cluster the `default` bin is mapped to, or to the only cluster in
use.

#### Which bins go where

`$settings['cache']['default']` sets the backend for every bin that does not
say otherwise. Two things override it:

- A per-bin setting, which always wins:
  ```php
  $settings['cache']['bins']['render'] = 'cache.backend.memcache';
  $settings['cache']['bins']['config'] = 'cache.backend.database';
  ```
- A bin's own declared backend. Core's `bootstrap`, `config`, `discovery` and
  `routes` bins declare `cache.backend.chainedfast`: APCu in front of your
  default backend. With memcache as the default those bins are APCu backed by
  memcache, and they are invalidated on cache rebuilds like every other bin.
  No configuration is needed for that to work. If APCu is not available on
  every web node, or you want those bins in memcache directly, set them per
  bin as above.

### Sharing memcached between sites

Give each site its own prefix, otherwise they read each other's cache:
```php
$settings['memcache']['key_prefix'] = 'mysite';
```
The prefix is part of every key, including the bin and tag timestamps, so two
sites with different prefixes are fully isolated even on one server.

### More than one web server

Three rules, and the module cannot check the first two for you:

1. **Identical `servers`, `bins` and `key_prefix` on every web server.** A
   server list that differs between web nodes means each node hashes the same
   key to a different place, which looks exactly like cache entries randomly
   disappearing.
2. **Synchronised clocks.** Invalidations are timestamps taken from the web
   server's clock (see [How it works](#how-it-works)). Run NTP or chrony on
   every node. A node whose clock is behind writes invalidations "in the past"
   that others already consider superseded; the module then pushes the
   timestamp forward, which rebuilds the affected items on every request on
   that node until its clock catches up. A node whose clock is ahead can store
   an item built from stale data and have it accepted at once.
3. **Do not run a memcached on each web node with only that node's address in
   its own settings**, unless you configure it as described in
   [A private memcached on every web server](#a-private-memcached-on-every-web-server).
   Each node would have a private cache with no way to hear about the
   others' invalidations.

#### The timestamp tolerance

The module absorbs a little clock disagreement through the *tolerance*: every
invalidation is stamped that many seconds into the future, and until a node's
own clock passes the stamp, items it writes with that tag or into that bin are
rebuilt on every request rather than cached. The default, 0.001 s, is right for
a single web server or for nodes that share a clock. With several nodes, set it
above the largest clock offset you actually measure (`chronyc tracking`,
`ntpq -p` or `timedatectl timesync-status`); on a LAN with working time sync
that is a few milliseconds, so `0.01`–`0.05` leaves headroom. A large value has
a real cost: after every `drush cr` nothing in the rebuilt bins can be cached
anywhere for that many seconds, and every content save causes a burst of
rebuilds for shared tags such as `node_list` or `rendered`. Where memcached
itself runs does not matter; only web server clocks are compared.

The tolerance is the last argument of the `memcache.timestamp.invalidator.bin`
service. Override it in your site's `services.yml`:
```yaml
services:
  memcache.timestamp.invalidator.bin:
    class: Drupal\memcache\Invalidator\MemcacheTimestampInvalidator
    arguments: ['@memcache.factory', 'memcache_bin_timestamps', 0.05]
```
If you also use the memcache-backed cache-tag checksum or a bootstrap container
(both below), give their `memcache.timestamp.invalidator.*` definitions the
same value: the smallest one is the protection you actually get.

### When a server goes down

By default the Memcached extension keeps trying a dead server: every request
that touches a key on it waits out the connection timeout (one second unless
changed) and then fails every operation on that server for the rest of the
request. To drop a failed server from the ring instead, and have its keys
served by the surviving servers:
```php
$settings['memcache']['options'] = [
  \Memcached::OPT_REMOVE_FAILED_SERVERS => TRUE,
  \Memcached::OPT_SERVER_FAILURE_LIMIT => 1,
  \Memcached::OPT_CONNECT_TIMEOUT => 100,
  \Memcached::OPT_POLL_TIMEOUT => 100,
];
```
`OPT_SERVER_FAILURE_LIMIT` must be `1`: libmemcached counts at most one
failure per operation and will not retry a failed server for
`OPT_RETRY_TIMEOUT` seconds (at least a whole second), so with a higher limit
the server is never removed within a request.

`OPT_POLL_TIMEOUT` matters for a server that is up but hung: one that accepts
the connection and then stays silent. That is not counted as a failure, so
`OPT_REMOVE_FAILED_SERVERS` never removes it; the connection is closed and
reopened on the next operation, and every operation that hashes to that server
waits out `OPT_POLL_TIMEOUT`, whose default in the Memcached extension is
**5000 ms**.

Every request still pays for discovering the dead server once per connection
(the bootstrap container and the main container each open their own, so
usually twice): the connect timeout above, or, when the server's hostname no
longer resolves, however long the system resolver takes to say so.
`OPT_CONNECT_TIMEOUT` does not bound DNS lookups. That is why a "dead server"
test in a container environment is often far slower than the timeout suggests:
stopping a container removes its name from Docker's DNS, and a resolver that
forwards the miss to slow upstream servers can take seconds per lookup. Run
`getent hosts <server>` from the web container to measure it. A production
server that is down normally still resolves; to test that path, use IP
addresses in `servers`, or pause the container instead of stopping it.

To stop paying that cost on every request, combine failed-server removal with
[persistent connections](#persistent-connections) and set `OPT_DEAD_TIMEOUT`.
The pool then remembers the dead server for the life of the PHP worker and
re-probes it every `OPT_DEAD_TIMEOUT` seconds, so a server that comes back is
used again. The probe is an ordinary cache operation, so while the server stays
down one key fails once per `OPT_DEAD_TIMEOUT` per worker; that is the whole
cost:
```php
$settings['memcache']['persistent'] = TRUE;
$settings['memcache']['options'] = [
  \Memcached::OPT_REMOVE_FAILED_SERVERS => TRUE,
  \Memcached::OPT_SERVER_FAILURE_LIMIT => 1,
  \Memcached::OPT_CONNECT_TIMEOUT => 100,
  \Memcached::OPT_POLL_TIMEOUT => 100,
  \Memcached::OPT_DEAD_TIMEOUT => 30,
];
```
Without `OPT_DEAD_TIMEOUT` a persistent pool never retries a removed server:
it stays out until the PHP workers restart, long after it is back. Set it
whenever the two are combined.

Whether or not you remove failed servers, you will hear about them: the
[status report](#status-report) lists every configured server that cannot be
reached, and the first failed operation in a request is logged to the
`memcache` channel with the result code and the servers involved. When APCu is
available the log is throttled to one entry per `failure_log_interval` seconds
(default 60) across the whole PHP pool; `0` logs once per request instead.

### Persistent connections

By default each request opens its own connections and closes them at the end.
On a high-latency network, or with many servers, that handshake is measurable:
```php
$settings['memcache']['persistent'] = TRUE;
```
The Memcached extension then keeps one connection pool per cluster for the life
of the PHP worker process. The pool's id is derived from the cluster name and a
hash of the servers, options and credentials, so a settings change starts a
fresh pool rather than inheriting stale servers. Two sites with identical
settings share a pool, which is safe because their keys differ by
`key_prefix`. `persistent_id` only labels the pool for diagnostics. The
Memcache Admin report shows whether the connections in use are persistent.

Before 8.x-2.9 this setting had no effect with the Memcached extension, so a
site that already had it set gains persistent pools on update. When combined
with `OPT_REMOVE_FAILED_SERVERS`, also set `OPT_DEAD_TIMEOUT`; see
[When a server goes down](#when-a-server-goes-down).

### Authentication

memcached does not require authentication by default; anyone who can reach the
port can read and write the cache. If the servers are not on a private network,
authenticate. There are two mechanisms; use the first.

**ASCII protocol authentication** (memcached 1.5.15+, 1.6.4+ recommended).
Start every server with `-Y <authfile>`, where the file holds up to eight
`username:password` lines, and give the module one of the pairs:
```php
$settings['memcache']['ascii_auth'] = [
  'username' => 'drupal',
  'password' => 'secret',
];
```
Neither part may contain whitespace. Every new connection is authenticated
before use, and a write refused because libmemcached silently reopened a
socket (after a server restart or a timeout) is re-authenticated and retried.
Reads on such a socket look like misses until the next write, which is
normally the rebuild that miss triggers. The status report shows an error when
the credentials are refused or the settings conflict, and a warning when a
server accepts commands *without* credentials, meaning it was not started with
`-Y` and the setting protects nothing.

**SASL** works only over the binary protocol, which the memcached project has
deprecated, and needs memcached built with `--enable-sasl` and started with
`-S`. It cannot be combined with `-Y`, which switches the binary protocol off:
```php
$settings['memcache']['sasl'] = ['username' => 'user', 'password' => 'secret'];
$settings['memcache']['options'] = [\Memcached::OPT_BINARY_PROTOCOL => TRUE];
```

With either mechanism the credentials cross the network in plain text: the PHP
extensions cannot speak TLS to memcached. Authentication keeps unauthenticated
clients out; it does not hide the traffic from anyone who can capture it. Put
the servers on a private network or tunnel the connection if that matters.

### Switching memcache off on an environment

To keep the module enabled everywhere but not use memcache on, say, a local
environment, set both `servers` and `bins` to empty arrays in that
environment's `settings.local.php`, and undo whatever `settings.php` sends to
memcache:
```php
$settings['memcache']['servers'] = [];
$settings['memcache']['bins'] = [];

$settings['cache']['default'] = 'cache.backend.database';
unset($settings['cache']['bins']);
// Also leave out any services file or bootstrap container definition that
// uses memcache.
$settings['container_yamls'] = array_diff(
  $settings['container_yamls'] ?? [],
  [$app_root . '/' . $site_path . '/memcache.yml']
);
unset($settings['bootstrap_container_definition']);
```
With both settings empty no PHP extension is needed, the module can be
installed (for example by `drush site:install` from an install profile), the
status report shows "Memcache: Disabled", and anything that still asks for a
memcache connection fails with an exception saying so. Both settings must be
explicitly empty: a missing setting means "use the default server".

### Behind a proxy, or if you need replication

memcached cannot replicate and this module does not emulate it. If any single
server must be able to serve every key, put a proxy in front of the pool and
list the proxy as the only server: [mcrouter](https://github.com/facebook/mcrouter)
and [twemproxy](https://github.com/twitter/twemproxy) both speak the memcached
protocol and support replicated pools. The cache and the status report work
unchanged through either. The Memcache Admin statistics page needs the
`stats` command, which twemproxy does not implement; the page says so rather
than reporting a broken configuration.

### A private memcached on every web server

Pointing each web server at a memcached on the same machine avoids a network
round trip per cache operation, at the price of every node having its own
cache. The nodes then have to agree on what is stale through something they do
share, the database:

- Cache-tag invalidation already goes through the database as long as you keep
  core's default `cache_tags.invalidator.checksum` service. Do not use the
  memcache-backed checksum or the "pure memcache" bootstrap container in this
  setup.
- Full bin deletion (`drush cr`, "Clear all caches") does **not** by default:
  the bin timestamp lives in memcache, so a rebuild on one node leaves the
  others serving the old cache. Move that timestamp into the database by
  aliasing the invalidator in your site's `services.yml`:
  ```yaml
  services:
    memcache.timestamp.invalidator.bin:
      alias: memcache.timestamp.invalidator.bin.key_value
  ```
  This costs one key-value read per request. If you also cache the container
  in memcache, the bootstrap container needs the same replacement; see
  [that section](#advanced-caching-the-container-in-memcache).
- Single-item operations (`delete()`, `invalidate()`, overwriting an item)
  still only affect the node that performs them. Most of core relies on cache
  tags, but the config storage cache in particular is maintained with direct
  writes and deletes, so a configuration change on one node stays invisible to
  the others until the next full rebuild. Keep such bins on the database:
  ```php
  $settings['cache']['bins']['config'] = 'cache.backend.database';
  ```
- Memcache locks and flood control are per node in this setup and cannot
  coordinate the nodes; leave them on the database.

## Optional services: locks, flood control, cache tags

These replace core services, so they are configured in your site's
`services.yml` (`sites/default/services.yml`, or any file you list in
`$settings['container_yamls']`), not in `settings.php`. The module ships all
three in `example.services.yml`; copy the ones you want, or load the file as a
whole. The module must be enabled for any of them to work.

**Locks.** Drupal's lock API stops several requests from doing the same
expensive work at once, for example rebuilding the router. Core keeps locks in
the database; moving them to memcache takes that traffic off the database:
```yaml
services:
  lock:
    class: Drupal\Core\Lock\LockBackendInterface
    factory: ['@memcache.lock.factory', get]
```

**Flood control** (Drupal 10.1 or later) is the limit on failed logins,
password resets, contact-form submissions and the like:
```yaml
services:
  flood:
    class: Drupal\Core\Flood\FloodInterface
    factory: ['@memcache.flood.factory', get]
```
Events are stored in the `flood` bin (map it to a cluster like any other bin),
expire on their own without cron, and are counted with a compare-and-swap so
concurrent requests are never lost. Flood state is lost when memcached restarts
or evicts under memory pressure; give the bin its own server if that matters.

**Cache-tag checksums in memcache.** By default cache-tag invalidation is
tracked in the database, which means the database is read on every cache hit
that carries tags. `example.services.yml` replaces that with a memcache-backed
checksum (`TimestampCacheTagsChecksum`), which is what makes a fully
database-free cache hit possible and is required for the "pure memcache"
bootstrap container below. It uses the timestamp mechanism described in
[How it works](#how-it-works), so the notes under
[More than one web server](#more-than-one-web-server) apply to tags as well,
and the `memcache.timestamp.invalidator.tag` service takes the same tolerance
argument. Do not use it with
[a private memcached per web server](#a-private-memcached-on-every-web-server).

## Advanced: caching the container in memcache

**You can skip this section.** It is an optimisation for high-traffic sites,
and a mistake in it produces a fatal error before Drupal can render an error
page. Everything above works without it.

The `cache.container` bin holds the compiled service container and is read
before that container exists, so the settings above cannot reach it; it stays
on the database. To serve it from memcache too, `settings.php` has to describe
a tiny "bootstrap" container by hand: which classes to load, and which
services build the cache backend. Do this only if you are comfortable
debugging a white screen from `settings.php`.

Paste the following into `settings.php`, adjusting the module path if it is
not `modules/contrib/memcache`. The `class_exists()` guard keeps a shared
`settings.php` from breaking an environment without the extension.
```php
if (class_exists('Memcached', FALSE) || class_exists('Memcache', FALSE)) {
  $class_loader->addPsr4('Drupal\\memcache\\', $app_root . '/modules/contrib/memcache/src');

  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'database' => [
        'class' => 'Drupal\Core\Database\Connection',
        'factory' => 'Drupal\Core\Database\Database::getConnection',
        'arguments' => ['default'],
      ],
      'settings' => [
        'class' => 'Drupal\Core\Site\Settings',
        'factory' => 'Drupal\Core\Site\Settings::getInstance',
      ],
      'memcache.settings' => [
        'class' => 'Drupal\memcache\MemcacheSettings',
        'arguments' => ['@settings'],
      ],
      'memcache.factory' => [
        'class' => 'Drupal\memcache\Driver\MemcacheDriverFactory',
        'arguments' => ['@memcache.settings'],
      ],
      'memcache.timestamp.invalidator.bin' => [
        'class' => 'Drupal\memcache\Invalidator\MemcacheTimestampInvalidator',
        // Same tolerance as your services.yml; see "The timestamp tolerance".
        'arguments' => ['@memcache.factory', 'memcache_bin_timestamps', 0.001],
      ],
      'memcache.backend.cache.container' => [
        'class' => 'Drupal\memcache\DrupalMemcacheInterface',
        'factory' => ['@memcache.factory', 'get'],
        'arguments' => ['container'],
      ],
      // Cache tags stay in the database.
      'cache_tags_provider.container' => [
        'class' => 'Drupal\Core\Cache\DatabaseCacheTagsChecksum',
        'arguments' => ['@database'],
      ],
      'cache.container' => [
        'class' => 'Drupal\memcache\MemcacheBackend',
        'arguments' => ['container', '@memcache.backend.cache.container', '@cache_tags_provider.container', '@memcache.timestamp.invalidator.bin'],
      ],
    ],
  ];
}
```

**Pure memcache variant.** If you also use the memcache-backed cache-tag
checksum from `example.services.yml`, the bootstrap container can avoid the
database entirely. Remove the `database` service and replace
`cache_tags_provider.container` with:
```php
      'memcache.timestamp.invalidator.tag' => [
        'class' => 'Drupal\memcache\Invalidator\MemcacheTimestampInvalidator',
        // Must match the definition in your services.yml exactly.
        'arguments' => ['@memcache.factory', 'memcache_tag_timestamps', 0.001],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\memcache\Cache\TimestampCacheTagsChecksum',
        'arguments' => ['@memcache.timestamp.invalidator.tag'],
      ],
```
Both invalidator definitions here read the same timestamps that the main
container writes, so their bin names and tolerances must match your
`services.yml`.

**Private memcached per web server.** Replace the
`memcache.timestamp.invalidator.bin` service with the key-value implementation
and add its two dependencies:
```php
      'serialization.phpserialize' => [
        'class' => 'Drupal\Component\Serialization\PhpSerialize',
      ],
      'keyvalue.database' => [
        'class' => 'Drupal\Core\KeyValueStore\KeyValueDatabaseFactory',
        'arguments' => ['@serialization.phpserialize', '@database'],
      ],
      'memcache.timestamp.invalidator.bin' => [
        'class' => 'Drupal\memcache\Invalidator\KeyValueTimestampInvalidator',
        'arguments' => ['@keyvalue.database', 'memcache_bin_timestamps', 0.001],
      ],
```

### Configuring memcache before the module is enabled

Drupal only reads a module's `services.yml` once the module is enabled, so
`cache.backend.memcache` does not exist before that. Two lines make it
available regardless, which lets one deploy both enable the module and switch
the cache, or install Drupal with memcache already configured:
```php
$memcache_path = $app_root . '/modules/contrib/memcache';
if (file_exists($memcache_path . '/memcache.services.yml')
  && (class_exists('Memcache', FALSE) || class_exists('Memcached', FALSE))) {
  $class_loader->addPsr4('Drupal\\memcache\\', $memcache_path . '/src');
  $settings['container_yamls'][] = $memcache_path . '/memcache.services.yml';
  $settings['cache']['default'] = 'cache.backend.memcache';
}
```
`$app_root` and `$class_loader` are both in scope in `settings.php`. Once the
module is enabled the kernel loads its `services.yml` itself; the entry is then
redundant but harmless. During `drush site:install` Drupal swaps every bin for
an in-memory backend, so these settings only take effect on the first request
after install. Until the module is enabled its status-report checks do not run,
so an unreachable server is not reported.

## Operating memcache

### Status report

Reports > Status report shows, without any extra module:

- **Memcache servers** — every configured server is contacted and a test
  value is stored and read back. An unreachable server is an error: the cache
  silently stops working for the keys on it.
- **Memcache memory** — memory in use and items evicted per server. memcached
  drops the least recently used items once its memory limit is reached, so any
  evictions mean cached data is being thrown away before it expires; a server
  left at the default 64 MB is the usual cause (raise it with memcached's `-m`).
  Sites that deliberately run a small cache can silence the warning with
  `$settings['memcache']['report_evictions'] = FALSE;`. Reads "Not available"
  behind twemproxy.
- **Memcache authentication** — only when `ascii_auth` is set; see
  [Authentication](#authentication).
- **Memcached PHP extension** — the client library version (not the server's),
  with an error for builds older than 3.2.0 on PHP 8.1+.

Each check waits `status_connect_timeout` milliseconds (default 1000) per
server, so expect the page to be slow while a server is down.

### Memcache Admin

The optional `memcache_admin` submodule adds Reports > Memcache statistics
(`admin/reports/memcache`): uptime, memory, hit rates, evictions and
connections per server and per cluster, from memcached's `stats` command. It
is safe to leave enabled in production: it does nothing on normal page loads
and only talks to the servers when someone with the `access memcache
statistics` permission opens the report. Do not poll it from monitoring; talk
to memcached directly for that.

Two things belong to debugging sessions only:

- "Show memcache statistics at the bottom of each page"
  (`admin/config/system/memcache`) appends a table of every memcache operation
  of the request to each page, for users with the permission. It shows nothing
  unless `$settings['memcache']['collect_statistics'] = TRUE;` is also set,
  which is the switch that makes every cache operation record itself. Leave
  both off in production.
- The `access slab cachedump` permission (PECL memcache extension only) exposes
  memcached's `stats cachedump`, which locks a slab while it runs and returns
  raw cache keys containing paths, entity IDs and user IDs. Grant it only while
  you are actually looking at a slab.

The report needs every server to answer `stats`; twemproxy does not, and the
page says so after confirming with a plain read that the servers are up.

### Debugging

```php
$settings['memcache']['debug'] = TRUE;
```
logs every cache backend operation at debug level and every failed memcache
operation (instead of only the first per request). Combine it with
`collect_statistics` and Memcache Admin's per-page table to trace one request.
Turn all of it off afterwards; each costs time on every cache operation.

## Troubleshooting

**Content is "one save behind", or stays stale after a save.** Fixed in
8.x-2.9: deletions and tag invalidations are now deferred until the database
transaction commits, so a concurrent request can no longer repopulate the
cache from uncommitted data. Also check the memcached extension version
(3.2.0+ on PHP 8.1+; see [Requirements](#requirements)) — older builds drop
every invalidation.

**Cache clears work on one web server but not the others**, or entries
"randomly" disappear. See [More than one web server](#more-than-one-web-server):
differing server lists, a different `key_prefix`, unsynchronised clocks, or a
private memcached per node without the key-value invalidator.

**Everything misses; nothing is ever cached.** Usually an unreachable server —
the module fails silently on the hot path by design. The status report says
which server, and the first failure per request is logged to the `memcache`
channel.

**"SERVER_ERROR object too large for cache".** memcached's default item limit
is 1 MB. Raise it in memcached's configuration, for example
`--max-item-size=5M` in `/etc/memcached.conf`. Items over the limit are split
by the module, but very large render arrays can still exceed it.

**"You have requested a non-existent service cache.backend.memcache".** The
cache setting was added before the module was enabled. See
[Quick start](#quick-start) or, to do both at once,
[Configuring memcache before the module is enabled](#configuring-memcache-before-the-module-is-enabled).

**Memcache Admin says there may be a problem with the configuration.** The
servers do not answer `stats`; see [Memcache Admin](#memcache-admin).

## Notes for developers

**Database transactions.** Memcache is not part of the database transaction.
Deletions (`delete()`, `deleteMultiple()`, `deleteAll()`, `removeBin()`,
`invalidateAll()`) and cache-tag invalidations performed while a transaction
is open are applied when the root transaction commits and discarded on
rollback; within the request that performs them they take effect immediately.
Writes are never delayed, but an item whose tags have an invalidation pending
commit is skipped, since it could never be read back as valid. This matches
core's database checksum and the Redis module, and applies to both core's
`cache_tags.invalidator.checksum` and the memcache implementation.

**`invalidateAll()` is handled as `deleteAll()`.** Core deprecated
`CacheBackendInterface::invalidateAll()` in Drupal 11.2 and removes it in 12.
By default the module deletes the bin instead of marking it invalid, so the
items can no longer be fetched with `$allow_invalid = TRUE`; in return items
are stored without the internal `memcache:<bin>` tag that only existed for
this, saving a tag check on every read. To keep the old behaviour until the
module requires Drupal 12:
`$settings['memcache']['invalidate_all_as_delete'] = FALSE;`.

**Cache-tag preloading.** On Drupal 11.1+ the memcache checksum service
implements `CacheTagsChecksumPreloadInterface`: the tags of every item a
`getMultiple()` returns, or a `setMultiple()` writes, are looked up in one
`getMulti()` instead of one per item. On older cores the class stays loadable
through an aliased stand-in interface.

**Development environment.** The repository ships an optional
[DDEV](https://ddev.com) configuration in `.ddev/` (ddev-drupal-contrib +
ddev-memcached) with a Drupal core checkout, a real memcached, and the same
phpunit, phpcs and phpstan commands as the GitLab CI pipeline:
```
ddev start
ddev poser            # Drupal core + dev dependencies into web/ and vendor/
ddev symlink-project  # links the module into web/modules/custom/memcache
ddev phpunit web/modules/custom/memcache/tests/src/Kernel
ddev phpcs
ddev phpstan
```
`web/`, `vendor/` and the downloaded phpcs/phpstan configs are gitignored. Do
not mount a checkout that contains `web/` as another site's contrib module:
core's extension discovery would find a second copy of every core module in
it. The tests read `MEMCACHED_HOST` (exported by `.ddev/config.memcache.yaml`
as `memcached:11211`), and skip the multi-server and authentication tests
unless `MEMCACHED_HOSTS=host1:11211,host2:11211` and
`MEMCACHED_AUTH_HOST=host:11211` (a server started with `-Y`, authfile
`drupal:secret`; override with `MEMCACHED_AUTH_USER`/`MEMCACHED_AUTH_PASSWORD`)
are set as well.

## Appendix: the PECL memcache extension

The original `memcache` extension (https://pecl.php.net/package/memcache) is
still supported for existing sites, but it is deprecated and the Memcached
extension is used by default when both are installed. Select it explicitly
with `$settings['memcache']['extension'] = 'Memcache';`.

It does not support: persistent connection pools
(it uses `pconnect` per server), compare-and-swap for flood control, SASL or
ASCII authentication, per-request failure logging (it raises PHP notices of
its own instead), the binary protocol, or the `status_connect_timeout`
setting. It is configured through `php.ini` rather than `settings.php`; with
more than one server set `memcache.hash_strategy=consistent` there and reload
PHP. Memcache Admin can additionally browse `slabs`, `items` and `sizes`
statistics and dump slab keys with it.

It does its own failover and needs none of the options under
[When a server goes down](#when-a-server-goes-down): `memcache.allow_failover`
(on by default) reroutes a key past a server that fails, within the same
request, for up to `memcache.max_failover_attempts` servers. Each failure
raises a PHP notice ("Server ... failed with: Network timeout") from every
pool object that hits it, usually twice per request with this module, and
costs that server's connect timeout (one second) once per pool object per
request. With `$settings['memcache']['persistent'] = TRUE` the failed server
is remembered across requests served by the same PHP process and retried
every 15 seconds.

## Appendix: network and key tuning

Things that are occasionally useful and never required.

**Keys longer than 250 bytes** (memcached's limit) are hashed with
`key_hash_algorithm` (default `sha1`, chosen for speed and low collision rate;
any `hash_algos()` name works) plus a readable suffix. Nothing to configure
unless you have a reason.

**Binary protocol** (`\Memcached::OPT_BINARY_PROTOCOL => TRUE`). Deprecated by
the memcached project, required only for SASL, and reported by several users to
be *slower*. Do not enable it without measuring.

**`\Memcached::OPT_TCP_NODELAY => TRUE`** sends packets without waiting to fill
them; it has been reported to help the binary protocol on some networks.
Measure before keeping it.

**Compression** is on by default (`OPT_COMPRESSION`); it lets more items fit
in the same memory and so reduces evictions. Turn it off only if CPU on the web
nodes is the bottleneck and the cache is small.
