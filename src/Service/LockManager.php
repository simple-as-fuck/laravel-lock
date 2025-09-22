<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Service;

use Illuminate\Contracts\Config\Repository;
use SimpleAsFuck\LaravelLock\Data\FakeLock;
use SimpleAsFuck\LaravelLock\Data\ArrayLock;
use SimpleAsFuck\LaravelLock\Model\Lock;
use SimpleAsFuck\Validator\Factory\Validator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class LockManager
{
    /** @var \WeakMap<LockInterface, non-empty-string> */
    private static \WeakMap $locksMap;

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly Repository $config
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

        $lockConfiguration = Validator::make($this->config->get('lock'), 'Config lock')->array();
        $appConfiguration = Validator::make($this->config->get('app'), 'Config app')->array();

        $keyPrefix = $lockConfiguration->key('prefix')->string()->nullable()
            ??
            $appConfiguration->key('name')->string()->nullable()
            ??
            ''
        ;
        if ($lockConfiguration->key('old_store')->nullable() !== null) {
            $oldKeyPrefix = $lockConfiguration->key('old_prefix')->string()->nullable()
                ??
                $appConfiguration->key('name')->string()->nullable()
                ??
                ''
            ;

            if ($keyPrefix !== $oldKeyPrefix) {
                $lock = new ArrayLock([
                    $this->lockFactory->createLock($keyPrefix.$key, null, true),
                    $this->lockFactory->createLock($oldKeyPrefix.$key, null, true),
                ]);
                self::$locksMap[$lock] = $key;
                return $lock;
            }
        }

        $lock = $this->lockFactory->createLock($keyPrefix.$key, null, true);
        self::$locksMap[$lock] = $key;
        return $lock;
    }
}
