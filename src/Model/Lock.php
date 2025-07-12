<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Model;

use Symfony\Component\Lock\LockInterface;

class Lock
{
    public function __construct(
        private readonly LockInterface $lock
    ) {
    }

    public function __destruct()
    {
        $this->release();
    }

    public function release(): void
    {
        if ($this->lock->isAcquired()) {
            $this->lock->release();
        }
    }

    public function acquired(): bool
    {
        return $this->lock->isAcquired();
    }
}
