<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Service;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use SimpleAsFuck\LaravelLock\Data\FakeLock;
use SimpleAsFuck\LaravelLock\Data\ArrayLock;
use SimpleAsFuck\LaravelLock\Model\Lock;
use SimpleAsFuck\Validator\Factory\Validator;
use SimpleAsFuck\Validator\Rule\ArrayRule\ArrayRule;
use SimpleAsFuck\Validator\Rule\General\Rules;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\PostgreSqlStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Strategy\UnanimousStrategy;

class LockManager
{
    /** @var \WeakMap<LockInterface, non-empty-string> */
    private static \WeakMap $locksMap;

    public function __construct(
        private ?LockFactory $lockFactory,
        private readonly Repository $config,
        private readonly DatabaseManager $databaseManager,
    ) {
        /** @var \WeakMap<LockInterface, non-empty-string> $weakMap */
        $weakMap = new \WeakMap();
        self::$locksMap ??= $weakMap;
    }

    /**
     * method will wait for unlocked key by another process and always return successfully acquired lock
     * @param non-empty-string $key
     */
    public function acquire(string $key): Lock
    {
        $lock = $this->makeSymfonyLock($key);

        $lock->acquire(true);

        return new Lock($lock);
    }

    /**
     * method will try to acquire lock for key, if key is in current time locked by another process return null
     * @param non-empty-string $key
     */
    public function acquireNotBlocking(string $key): ?Lock
    {
        $lock = $this->makeSymfonyLock($key);

        if (! $lock->acquire(false)) {
            return null;
        }

        return new Lock($lock);
    }

    /**
     * @param non-empty-array<non-empty-string> $keys
     */
    public function acquireMultiple(array $keys): Lock
    {
        \sort($keys, \SORT_STRING);
        $lock = new ArrayLock(\array_map(fn (string $key): LockInterface => $this->makeSymfonyLock($key), $keys));

        $lock->acquire(true);

        return new Lock($lock);
    }

    /**
     * @param non-empty-array<non-empty-string> $keys
     */
    public function acquireMultipleNotBlocking(array $keys): ?Lock
    {
        \sort($keys, \SORT_STRING);
        $lock = new ArrayLock(\array_map(fn (string $key): LockInterface => $this->makeSymfonyLock($key), $keys));

        if (! $lock->acquire(false)) {
            return null;
        }

        return new Lock($lock);
    }

    /**
     * @param non-empty-string $key
     */
    private function makeSymfonyLock(string $key): LockInterface
    {
        foreach (self::$locksMap as $lock => $lockedKey) {
            if ($lockedKey === $key && $lock->isAcquired()) {
                return new FakeLock();
            }
        }

        $lockConfiguration = $this->getConfig('lock')->array();
        $lockFactory = $this->makeLockFactory($lockConfiguration);

        $keyPrefix = $lockConfiguration->key('prefix')->string()->nullable()
            ??
            $this->getConfig('app.name')->string()->nullable()
            ??
            ''
        ;
        if ($lockConfiguration->key('old_store')->nullable() !== null) {
            $oldKeyPrefix = $lockConfiguration->key('old_prefix')->string()->nullable()
                ??
                $this->getConfig('app.name')->string()->nullable()
                ??
                ''
            ;

            if ($keyPrefix !== $oldKeyPrefix) {
                $lock = new ArrayLock([
                    $lockFactory->createLock($keyPrefix.$key, null, true),
                    $lockFactory->createLock($oldKeyPrefix.$key, null, true),
                ]);
                self::$locksMap[$lock] = $key;
                return $lock;
            }
        }

        $lock = $lockFactory->createLock($keyPrefix.$key, null, true);
        self::$locksMap[$lock] = $key;
        return $lock;
    }

    private function makeLockFactory(ArrayRule $lockConfiguration): LockFactory
    {
        if ($this->lockFactory !== null) {
            return $this->lockFactory;
        }

        $storeName = $lockConfiguration->key('store')->string()->in(['semaphore', 'flock', 'pgsql'])->notNull();
        $storeConfiguration = $lockConfiguration->key($storeName.'_store')->array();
        $store = $this->makeStore($storeName, $storeConfiguration);

        $oldStoreName = $lockConfiguration->key('old_store')->string()->in(['semaphore', 'flock', 'pgsql'])->nullable();
        if ($oldStoreName !== null) {
            $oldStoreConfiguration = $lockConfiguration->key('old_' . $oldStoreName . '_store')->array();
            $storeConfigurationValue = $storeConfiguration->nullable() ?? [];
            $oldStoreConfigurationValue = $oldStoreConfiguration->nullable() ?? [];

            ksort($storeConfigurationValue);
            ksort($oldStoreConfigurationValue);

            if ($storeName !== $oldStoreName || $storeConfigurationValue !== $oldStoreConfigurationValue) {
                $oldStore = $this->makeStore($oldStoreName, $oldStoreConfiguration);
                $store = new CombinedStore([$store, $oldStore], new UnanimousStrategy());
            }
        }

        $this->lockFactory = new LockFactory($store);
        return $this->lockFactory;
    }

    /**
     * @param 'semaphore'|'flock'|'pgsql' $storeName
     */
    private function makeStore(string $storeName, ArrayRule $storeConfiguration): BlockingStoreInterface
    {
        return match($storeName) {
            'semaphore' => new SemaphoreStore(),
            'flock' => new FlockStore(sys_get_temp_dir().DIRECTORY_SEPARATOR.'php-locks'),
            'pgsql' => $this->makePostgreStore($storeConfiguration),
        };
    }

    private function makePostgreStore(ArrayRule $storeConfiguration): PostgreSqlStore
    {
        $connectionName = $storeConfiguration->key('connection')->string()->nullable();

        $connection = $this->databaseManager->connection($connectionName);
        if ($connection->getDriverName() !== 'pgsql') {
            throw new \RuntimeException('Database connection: "'.$connectionName.'" for "pgsql" lock store must have "pgsql" driver');
        }

        return new PostgreSqlStore($connection->getPdo());
    }

    /**
     * @param literal-string $key
     */
    private function getConfig(string $key): Rules
    {
        return Validator::make($this->config->get($key), 'Config ' . $key);
    }
}
