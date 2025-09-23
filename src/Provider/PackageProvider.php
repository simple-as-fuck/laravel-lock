<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Provider;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use SimpleAsFuck\LaravelLock\Factory\PostgreSqlFactory;
use SimpleAsFuck\LaravelLock\Service\LockManager;
use SimpleAsFuck\Validator\Factory\Validator;
use SimpleAsFuck\Validator\Rule\ArrayRule\ArrayRule;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Strategy\UnanimousStrategy;

class PackageProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LockManager::class);

        $this->app->singleton(LockFactory::class, function (): LockFactory {
            /** @var Repository $config */
            $config = $this->app->make(Repository::class);
            $lockConfiguration = Validator::make($config->get('lock'), 'Config lock')->array();
            $storeName = $lockConfiguration->key('store')
                ->string()
                ->in(['semaphore', 'flock', 'pgsql'])
                ->notNull()
            ;
            $storeConfiguration = $lockConfiguration->key($storeName.'_store')->array();
            $store = $this->makeStore($storeName, $storeConfiguration);

            $oldStoreName = $lockConfiguration->key('old_store')
                ->string()
                ->in(['semaphore', 'flock', 'pgsql'])
                ->nullable()
            ;
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

            return new LockFactory($store);
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/lock.php', 'lock');
    }

    /**
     * @param 'semaphore'|'flock'|'pgsql' $storeName
     */
    private function makeStore(
        string $storeName,
        ArrayRule $storeConfiguration,
    ): PersistingStoreInterface {
        if ($storeName === 'pgsql') {
            /** @var PostgreSqlFactory $postgreSqlFactory */
            $postgreSqlFactory = $this->app->make(PostgreSqlFactory::class);
            return $postgreSqlFactory->make($storeConfiguration);
        }

        return match($storeName) {
            'semaphore' => new SemaphoreStore(),
            'flock' => new FlockStore(sys_get_temp_dir().DIRECTORY_SEPARATOR.'php-locks'),
        };
    }
}
