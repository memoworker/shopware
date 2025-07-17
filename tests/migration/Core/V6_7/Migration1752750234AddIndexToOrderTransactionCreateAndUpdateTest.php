<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1752750234AddIndexToOrderTransactionCreateAndUpdate;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1752750234AddIndexToOrderTransactionCreateAndUpdate::class)]
class Migration1752750234AddIndexToOrderTransactionCreateAndUpdateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1752750234AddIndexToOrderTransactionCreateAndUpdate();
        static::assertSame(1752750234, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1752750234AddIndexToOrderTransactionCreateAndUpdate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $existingIndexes = $this->connection->createSchemaManager()->listTableIndexes('order_transaction');

        static::assertArrayHasKey('idx.order_transaction_created_at', $existingIndexes);
        static::assertArrayHasKey('idx.order_transaction_updated_at', $existingIndexes);
    }

    private function rollback(): void
    {
        $existingIndexes = $this->connection->createSchemaManager()->listTableIndexes('order_transaction');

        if (isset($existingIndexes['idx.order_transaction_created_at'])) {
            $this->connection->executeStatement('DROP INDEX `idx.order_transaction_created_at` ON `order_transaction`');
        }

        if (isset($existingIndexes['idx.order_transaction_updated_at'])) {
            $this->connection->executeStatement('DROP INDEX `idx.order_transaction_updated_at` ON `order_transaction`');
        }
    }
}
