<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Factory;

use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;

final class FlockFactory extends StoreFactory
{
    public function make(): BlockingStoreInterface
    {
        return new FlockStore(sys_get_temp_dir().DIRECTORY_SEPARATOR.'php-locks');
    }
}
