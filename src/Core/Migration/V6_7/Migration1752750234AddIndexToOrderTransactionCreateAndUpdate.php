<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752750234AddIndexToOrderTransactionCreateAndUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752750234;
    }

    public function update(Connection $connection): void
    {
        $existingIndexes = $connection->createSchemaManager()->listTableIndexes('order_transaction');

        if (!isset($existingIndexes['idx.order_transaction_created_at'])) {
            $connection->executeStatement('CREATE INDEX `idx.order_transaction_created_at` ON `order_transaction` (`created_at`)');
        }

        if (!isset($existingIndexes['idx.order_transaction_updated_at'])) {
            $connection->executeStatement('CREATE INDEX `idx.order_transaction_updated_at` ON `order_transaction` (`updated_at`)');
        }
    }
}
