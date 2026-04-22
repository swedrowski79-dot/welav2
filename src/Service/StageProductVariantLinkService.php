<?php

declare(strict_types=1);

final class StageProductVariantLinkService
{
    public function __construct(private PDO $stageDb)
    {
    }

    public function sync(): array
    {
        $this->stageDb->exec(
            'UPDATE `stage_products` AS child
             LEFT JOIN `stage_products` AS master
               ON master.`sku` = child.`master_sku`
             SET child.`master_afs_artikel_id` = CASE
                 WHEN child.`is_master` = 1 THEN child.`afs_artikel_id`
                 WHEN child.`master_sku` IS NULL OR child.`master_sku` = "" THEN NULL
                 ELSE master.`afs_artikel_id`
             END'
        );

        return [
            'masters' => $this->countByCondition('`is_master` = 1'),
            'slaves' => $this->countByCondition('`is_slave` = 1'),
            'resolved_master_links' => $this->countByCondition('`is_slave` = 1 AND `master_afs_artikel_id` IS NOT NULL'),
            'unresolved_master_links' => $this->countByCondition('`is_slave` = 1 AND `master_sku` IS NOT NULL AND `master_sku` <> "" AND `master_afs_artikel_id` IS NULL'),
        ];
    }

    private function countByCondition(string $condition): int
    {
        $stmt = $this->stageDb->query("SELECT COUNT(*) FROM `stage_products` WHERE {$condition}");

        return (int) $stmt->fetchColumn();
    }
}
