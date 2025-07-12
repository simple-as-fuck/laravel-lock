<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Data;

use Symfony\Component\Lock\LockInterface;

final readonly class ArrayLock implements LockInterface
{
    /**
     * @param non-empty-array<LockInterface> $locks
     */
    public function __construct(
        private array $locks
    ) {
    }

    public function acquire(bool $blocking = false): bool
    {
        $acquiredLocks = [];
        foreach ($this->locks as $lock) {
            try {
                $acquired = $lock->acquire($blocking);
            } catch (\Throwable $exception) {
                if (count($acquiredLocks) !== 0) {
                    (new self($acquiredLocks))->release();
                }
                throw $exception;
            }
            if ($acquired === false) {
                if (count($acquiredLocks) !== 0) {
                    (new self($acquiredLocks))->release();
                }
                return false;
            }

            $acquiredLocks[] = $lock;
        }

        return true;
    }

    public function refresh(?float $ttl = null): void
    {
        foreach ($this->locks as $lock) {
            try {
                $lock->refresh($ttl);
            } catch (\Throwable $exception) {
                (new self($this->locks))->release();

                throw $exception;
            }
        }
    }

    public function isAcquired(): bool
    {
        foreach ($this->locks as $lock) {
            if (! $lock->isAcquired()) {
                return false;
            }
        }

        return true;
    }

    public function release(): void
    {
        $lastException = null;
        foreach ($this->locks as $lock) {
            try {
                $lock->release();
            } catch (\Throwable $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }
    }

    public function isExpired(): bool
    {
        foreach ($this->locks as $lock) {
            if ($lock->isExpired()) {
                return true;
            }
        }

        return false;
    }

    public function getRemainingLifetime(): ?float
    {
        $minimum = null;
        foreach ($this->locks as $lock) {
            if ($lock->getRemainingLifetime() === null) {
                continue;
            }

            if ($minimum === null) {
                $minimum = $lock->getRemainingLifetime();
            }

            $minimum = min($lock->getRemainingLifetime(), $minimum);
        }

        return $minimum;
    }
}
