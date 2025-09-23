<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;
use SimpleAsFuck\LaravelLock\Model\Lock;
use SimpleAsFuck\LaravelLock\Service\LockManager;

final class LockTest extends TestCase
{
    private LockManager $lockManager;

    protected function setUp(): void
    {
        $config = $this->createMock(Repository::class);
        $config->method('get')->willReturnCallback(static function (string $key): ?array {
            $data = [
                'lock' => [
                    'store' => 'semaphore',
                ],
            ];
            return $data[$key] ?? null;
        });

        $databaseManager = $this->createMock(DatabaseManager::class);

        $this->lockManager = new LockManager(null, $config, $databaseManager);
    }

    public function testDuplicateRelease(): void
    {
        $oldLock = $this->lockManager->acquireNotBlocking('test');
        self::assertNotNull($oldLock);
        /** @var Lock $oldLock */

        $oldLock->release();

        self::assertFalse($oldLock->acquired());

        $newLock = $this->lockManager->acquireNotBlocking('test');

        $oldLock->release();

        self::assertFalse($oldLock->acquired());
        self::assertNotNull($newLock);
        /** @var Lock $newLock */
        self::assertTrue($newLock->acquired());

        $newLock->release();

        self::assertFalse($oldLock->acquired());
        self::assertFalse($newLock->acquired());
    }

    public function testRecursionRelease(): void
    {
        $lock = $this->lockManager->acquireNotBlocking('test');
        $recursionLock = $this->lockManager->acquireNotBlocking('test');

        self::assertNotNull($lock);
        /** @var Lock $lock */
        self::assertNotNull($recursionLock);
        /** @var Lock $recursionLock */
        self::assertTrue($lock->acquired());
        self::assertTrue($recursionLock->acquired());

        $recursionLock->release();

        self::assertFalse($recursionLock->acquired());
        self::assertTrue($lock->acquired());

        $lock->release();

        self::assertFalse($recursionLock->acquired());
        self::assertFalse($lock->acquired());
    }
}
