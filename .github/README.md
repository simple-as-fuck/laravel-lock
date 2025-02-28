# Simple as fuck / Laravel lock

Laravel integration for [symfony/lock](https://symfony.com/doc/current/components/lock.html).

## Installation

```console
composer require simple-as-fuck/laravel-lock
```

## Support

If any PHP platform requirements in [composer.json](../composer.json) ends with security support,
consider package version as unsupported except last version.

[PHP supported versions](https://www.php.net/supported-versions.php).

## Configuration

Add into your .env_example and configure your environment on server.

```dotenv
LOCK_STORE=semaphore
LOCK_PREFIX=null
#LOCK_PGSQL_STORE_CONNECTION=null
```

Supported symfony lock store are only with native blocking lock, because it is fucking effective.

- `LOCK_STORE=semaphore` [SemaphoreStore](https://symfony.com/doc/current/components/lock.html#semaphorestore)
recommended for simple production without application server replication (lock are stored in local ram)

- `LOCK_STORE=flock` [FlockStore](https://symfony.com/doc/current/components/lock.html#id3)
recommended for local development, (lock are stored in local filesystem, so it should work everywhere)

- `LOCK_STORE=pgsql` [PostgreSqlStore](https://symfony.com/doc/current/components/lock.html#postgresqlstore)
recommended for big production with application server replication
(lock are stored remotely by postgres database),
you can use special database for locks using setting laravel database connection name
`LOCK_PGSQL_STORE_CONNECTION=some_postgre_sql_connection_name`, by default is used default database connection


- `LOCK_PREFIX=null`
package will use `'app.name'` config value as lock keys prefix

- `LOCK_PREFIX=empty` or `LOCK_PREFIX=""`
env sets prefix to empty string, simply turn off lock keys prefixing

- `LOCK_PREFIX="some_prefix"`
env sets custom prefix and override prefix from application name

⚠ Changing lock configuration is [dangerous operation](https://symfony.com/doc/current/components/lock.html#overall). ⚠

If you need change lock store, you can use environment variables with `OLD_` prefix.

```dotenv
OLD_LOCK_STORE=semaphore
OLD_LOCK_PREFIX=null
#OLD_LOCK_PGSQL_STORE_CONNECTION=null
```

- For save configuration change copy your current lock configuration
and prefix all environment keys with `OLD_` prefix,
change basic environment variables with usage of new lock configuration.
- Keep your application running with both configurations for a while.
- After all processes with unchanged configuration ends or dies,
remove all environment keys with `OLD_` prefix.

⚠ If your change of multiple environment variables is not atomic operation,
you should change variables in specific order. First create variables with `OLD_` prefix,
`OLD_LOCK_STORE` create as last one, second if you need, prepare specific variables for specific store,
third if you need, change `LOCK_PREFIX` at last if you need, change `LOCK_STORE`.
While you cleaning `OLD_` prefix, remove `OLD_LOCK_STORE` as first.

Is not recommended run application with old configuration for long time
because locking with old store is less effective.

## Usage

```php
/** @var \SimpleAsFuck\LaravelLock\Service\LockManager $lockManager */
$lockManager = app()->make(\SimpleAsFuck\LaravelLock\Service\LockManager::class);

$lock = $lockManager->acquire('some_lock_key');
try {
    //happy run some critical code synchronously
} finally {
    $lock->release();
}
```
