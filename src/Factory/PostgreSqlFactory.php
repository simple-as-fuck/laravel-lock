<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Factory;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use SimpleAsFuck\Validator\Factory\Validator;
use SimpleAsFuck\Validator\Rule\ArrayRule\ArrayRule;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Store\PostgreSqlStore;

final class PostgreSqlFactory extends StoreFactory
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly Repository $config
    ) {
    }

    /**
     * @todo $configuration will be in 0.3 not null
     */
    public function make(?ArrayRule $configuration = null): BlockingStoreInterface
    {
        if ($configuration !== null) {
            $connectionName = $configuration->key('connection')->string()->nullable();
        } else {
            $connectionName = Validator::make($this->config->get('lock.pgsql_store.connection'))->string()->nullable();
        }

        $connection = $this->databaseManager->connection($connectionName);

        if ($connection->getDriverName() !== 'pgsql') {
            throw new \RuntimeException('Database connection: "'.$connectionName.'" for "pgsql" lock store must have "pgsql" driver');
        }

        return new PostgreSqlStore($connection->getPdo());
    }
}
