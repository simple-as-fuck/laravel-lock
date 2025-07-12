<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Data;

use Symfony\Component\Lock\LockInterface;

final class FakeLock implements LockInterface
{
    private bool $acquired = false;

    public function acquire(bool $blocking = false): bool
    {
        $this->acquired = true;
        return true;
    }

    public function refresh(?float $ttl = null): void
    {
    }

    public function isAcquired(): bool
    {
        return $this->acquired;
    }

    public function release(): void
    {
        $this->acquired = false;
    }

    public function isExpired(): bool
    {
        return false;
    }

    public function getRemainingLifetime(): ?float
    {
        return null;
    }
}
