<?php

declare(strict_types=1);

namespace SimpleAsFuck\LaravelLock\Factory;

use Illuminate\Database\DatabaseManager;
use SimpleAsFuck\Validator\Rule\ArrayRule\ArrayRule;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Store\PostgreSqlStore;

final readonly class PostgreSqlFactory
{
    public function __construct(
        private DatabaseManager $databaseManager,
    ) {
    }

    public function make(ArrayRule $configuration): BlockingStoreInterface
    {
        $connectionName = $configuration->key('connection')->string()->nullable();
        $connection = $this->databaseManager->connection($connectionName);

        if ($connection->getDriverName() !== 'pgsql') {
            throw new \RuntimeException('Database connection: "'.$connectionName.'" for "pgsql" lock store must have "pgsql" driver');
        }

        return new PostgreSqlStore($connection->getPdo());
    }
}
