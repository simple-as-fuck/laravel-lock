<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Provider;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use SimpleAsFuck\LaravelLock\Service\LockManager;

class PackageProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LockManager::class, function (): LockManager {
            /** @var Repository $config */
            $config = $this->app->make(Repository::class);
            /** @var DatabaseManager $databaseManager */
            $databaseManager = $this->app->make(DatabaseManager::class);

            return new LockManager(
                null,
                $config,
                $databaseManager,
            );
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/lock.php', 'lock');
    }
}
