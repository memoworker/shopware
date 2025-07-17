<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752750086AddIndexToOrderLineItemCreateAndUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752750086;
    }

    public function update(Connection $connection): void
    {
        $existingIndexes = $connection->createSchemaManager()->listTableIndexes('order_line_item');

        if (!isset($existingIndexes['idx.order_line_item_created_at'])) {
            $connection->executeStatement('CREATE INDEX `idx.order_line_item_created_at` ON `order_line_item` (`created_at`)');
        }

        if (!isset($existingIndexes['idx.order_line_item_updated_at'])) {
            $connection->executeStatement('CREATE INDEX `idx.order_line_item_updated_at` ON `order_line_item` (`updated_at`)');
        }
    }
}
