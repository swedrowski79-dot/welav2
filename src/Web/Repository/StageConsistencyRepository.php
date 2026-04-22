<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class StageConsistencyRepository
{
    public function __construct(private array $deltaConfig = [])
    {
    }

    /**
     * @return array{summary: array{issues:int, checks:int, affected_rows:int}, checks:list<array{name:string,count:int,severity:string,description:string,examples:list<string>}>}
     */
    public function report(\PDO $stageDb): array
    {
        $checks = [];

        foreach ($this->definitions() as $definition) {
            if (!$this->tablesExist($stageDb, $definition['tables'])) {
                continue;
            }

            $count = $this->countRows($stageDb, $definition['count_sql']);
            if ($count <= 0) {
                continue;
            }

            $examples = $this->exampleValues($stageDb, $definition['example_sql']);
            $checks[] = [
                'name' => $definition['name'],
                'count' => $count,
                'severity' => $definition['severity'],
                'description' => $definition['description'],
                'examples' => $examples,
            ];
        }

        return [
            'summary' => [
                'issues' => count($checks),
                'checks' => count($this->definitions()),
                'affected_rows' => array_sum(array_map(static fn (array $check): int => $check['count'], $checks)),
            ],
            'checks' => $checks,
        ];
    }

    /**
     * @return list<array{article_id:string,article_number:?string,article_name:?string}>
     */
    public function missingProductsWithoutTranslations(\PDO $stageDb): array
    {
        if (!$this->tablesExist($stageDb, ['stage_products', 'stage_product_translations'])) {
            return [];
        }

        $stmt = $stageDb->query(
            'SELECT
                CAST(p.afs_artikel_id AS CHAR) AS article_id,
                p.sku AS article_number,
                p.name_default AS article_name
             FROM stage_products p
             LEFT JOIN stage_product_translations t ON t.afs_artikel_id = p.afs_artikel_id
             WHERE p.afs_artikel_id IS NOT NULL
               AND t.afs_artikel_id IS NULL
             ORDER BY p.afs_artikel_id ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{category_id:string,category_name:?string}>
     */
    public function missingCategoriesWithoutTranslations(\PDO $stageDb): array
    {
        if (!$this->tablesExist($stageDb, ['stage_categories', 'stage_category_translations'])) {
            return [];
        }

        $stmt = $stageDb->query(
            'SELECT
                CAST(c.afs_wg_id AS CHAR) AS category_id,
                c.name_default AS category_name
             FROM stage_categories c
             LEFT JOIN stage_category_translations t ON t.afs_wg_id = c.afs_wg_id
             WHERE c.afs_wg_id IS NOT NULL
               AND t.afs_wg_id IS NULL
             ORDER BY c.afs_wg_id ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{name:string,severity:string,description:string,tables:list<string>,count_sql:string,example_sql:string}>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'Produkte ohne Uebersetzungen',
                'severity' => 'warning',
                'description' => 'Stage-Produkte ohne passende Eintraege in `stage_product_translations`.',
                'tables' => ['stage_products', 'stage_product_translations'],
                'count_sql' => 'SELECT COUNT(*) FROM stage_products p LEFT JOIN stage_product_translations t ON t.afs_artikel_id = p.afs_artikel_id WHERE p.afs_artikel_id IS NOT NULL AND t.afs_artikel_id IS NULL',
                'example_sql' => 'SELECT p.afs_artikel_id FROM stage_products p LEFT JOIN stage_product_translations t ON t.afs_artikel_id = p.afs_artikel_id WHERE p.afs_artikel_id IS NOT NULL AND t.afs_artikel_id IS NULL ORDER BY p.afs_artikel_id ASC LIMIT 5',
            ],
            [
                'name' => 'Produkt-Uebersetzungen ohne Produkt',
                'severity' => 'danger',
                'description' => 'Uebersetzungen referenzieren Produkte, die in `stage_products` nicht vorhanden sind.',
                'tables' => ['stage_products', 'stage_product_translations'],
                'count_sql' => 'SELECT COUNT(*) FROM stage_product_translations t LEFT JOIN stage_products p ON p.afs_artikel_id = t.afs_artikel_id WHERE t.afs_artikel_id IS NOT NULL AND p.afs_artikel_id IS NULL',
                'example_sql' => 'SELECT t.afs_artikel_id FROM stage_product_translations t LEFT JOIN stage_products p ON p.afs_artikel_id = t.afs_artikel_id WHERE t.afs_artikel_id IS NOT NULL AND p.afs_artikel_id IS NULL ORDER BY t.afs_artikel_id ASC LIMIT 5',
            ],
            [
                'name' => 'Kategorien ohne Uebersetzungen',
                'severity' => 'warning',
                'description' => 'Stage-Kategorien ohne passende Eintraege in `stage_category_translations`.',
                'tables' => ['stage_categories', 'stage_category_translations'],
                'count_sql' => 'SELECT COUNT(*) FROM stage_categories c LEFT JOIN stage_category_translations t ON t.afs_wg_id = c.afs_wg_id WHERE c.afs_wg_id IS NOT NULL AND t.afs_wg_id IS NULL',
                'example_sql' => 'SELECT c.afs_wg_id FROM stage_categories c LEFT JOIN stage_category_translations t ON t.afs_wg_id = c.afs_wg_id WHERE c.afs_wg_id IS NOT NULL AND t.afs_wg_id IS NULL ORDER BY c.afs_wg_id ASC LIMIT 5',
            ],
            [
                'name' => 'Kategorie-Uebersetzungen ohne Kategorie',
                'severity' => 'warning',
                'description' => 'Stage-Kategorie-Uebersetzungen referenzieren Kategorien, die in `stage_categories` nicht vorhanden sind.',
                'tables' => ['stage_categories', 'stage_category_translations'],
                'count_sql' => 'SELECT COUNT(*) FROM stage_category_translations t LEFT JOIN stage_categories c ON c.afs_wg_id = t.afs_wg_id WHERE t.afs_wg_id IS NOT NULL AND c.afs_wg_id IS NULL',
                'example_sql' => 'SELECT t.afs_wg_id FROM stage_category_translations t LEFT JOIN stage_categories c ON c.afs_wg_id = t.afs_wg_id WHERE t.afs_wg_id IS NOT NULL AND c.afs_wg_id IS NULL ORDER BY t.afs_wg_id ASC LIMIT 5',
            ],
            [
                'name' => 'Attributzeilen ohne Produkt-Uebersetzung',
                'severity' => 'danger',
                'description' => 'Attributzeilen haben kein passendes Produkt-Uebersetzungsziel fuer Produkt und Sprache.',
                'tables' => ['stage_attribute_translations', 'stage_product_translations'],
                'count_sql' => 'SELECT COUNT(*) FROM stage_attribute_translations a LEFT JOIN stage_product_translations t ON t.afs_artikel_id = a.afs_artikel_id AND t.language_code = a.language_code WHERE a.afs_artikel_id IS NOT NULL AND t.afs_artikel_id IS NULL',
                'example_sql' => 'SELECT CONCAT(a.afs_artikel_id, \':\', a.language_code) FROM stage_attribute_translations a LEFT JOIN stage_product_translations t ON t.afs_artikel_id = a.afs_artikel_id AND t.language_code = a.language_code WHERE a.afs_artikel_id IS NOT NULL AND t.afs_artikel_id IS NULL ORDER BY a.afs_artikel_id ASC, a.language_code ASC LIMIT 5',
            ],
            [
                'name' => 'Attributzeilen ohne Produkt',
                'severity' => 'warning',
                'description' => 'Stage-Attributzeilen referenzieren Produkte, die in `stage_products` nicht vorhanden sind.',
                'tables' => ['stage_attribute_translations', 'stage_products'],
                'count_sql' => 'SELECT COUNT(*) FROM stage_attribute_translations a LEFT JOIN stage_products p ON p.afs_artikel_id = a.afs_artikel_id WHERE a.afs_artikel_id IS NOT NULL AND p.afs_artikel_id IS NULL',
                'example_sql' => 'SELECT a.afs_artikel_id FROM stage_attribute_translations a LEFT JOIN stage_products p ON p.afs_artikel_id = a.afs_artikel_id WHERE a.afs_artikel_id IS NOT NULL AND p.afs_artikel_id IS NULL ORDER BY a.afs_artikel_id ASC LIMIT 5',
            ],
            [
                'name' => 'Export-State ohne aktuelles Produkt',
                'severity' => 'warning',
                'description' => 'Persistenter Export-State verweist auf Produkte, die aktuell nicht mehr in `stage_products` vorhanden sind.',
                'tables' => ['product_export_state', 'stage_products'],
                'count_sql' => 'SELECT COUNT(*) FROM product_export_state s LEFT JOIN stage_products p ON p.afs_artikel_id = s.product_id WHERE p.afs_artikel_id IS NULL',
                'example_sql' => 'SELECT s.product_id FROM product_export_state s LEFT JOIN stage_products p ON p.afs_artikel_id = s.product_id WHERE p.afs_artikel_id IS NULL ORDER BY s.product_id ASC LIMIT 5',
            ],
            [
                'name' => 'Kategorie-Export-State ohne aktuelle Kategorie',
                'severity' => 'warning',
                'description' => 'Persistenter Kategorie-Export-State verweist auf Kategorien, die aktuell nicht mehr in `stage_categories` vorhanden sind.',
                'tables' => ['category_export_state', 'stage_categories'],
                'count_sql' => 'SELECT COUNT(*) FROM category_export_state s LEFT JOIN stage_categories c ON c.afs_wg_id = s.category_id WHERE c.afs_wg_id IS NULL',
                'example_sql' => 'SELECT s.category_id FROM category_export_state s LEFT JOIN stage_categories c ON c.afs_wg_id = s.category_id WHERE c.afs_wg_id IS NULL ORDER BY s.category_id ASC LIMIT 5',
            ],
            [
                'name' => 'Media-Export-State ohne aktuelles Medium',
                'severity' => 'warning',
                'description' => 'Persistenter Media-Export-State verweist auf Medien, die aktuell nicht mehr in `stage_product_media` vorhanden sind.',
                'tables' => ['product_media_export_state', 'stage_product_media'],
                'count_sql' => 'SELECT COUNT(*) FROM product_media_export_state s LEFT JOIN stage_product_media m ON BINARY m.media_external_id = BINARY s.entity_id WHERE m.media_external_id IS NULL',
                'example_sql' => 'SELECT s.entity_id FROM product_media_export_state s LEFT JOIN stage_product_media m ON BINARY m.media_external_id = BINARY s.entity_id WHERE m.media_external_id IS NULL ORDER BY s.entity_id ASC LIMIT 5',
            ],
            [
                'name' => 'Dokument-Export-State ohne aktuelles Dokument',
                'severity' => 'warning',
                'description' => 'Persistenter Dokument-Export-State verweist auf Dokumente, die aktuell nicht mehr in `stage_product_documents` vorhanden sind.',
                'tables' => ['product_document_export_state', 'stage_product_documents'],
                'count_sql' => 'SELECT COUNT(*) FROM product_document_export_state s LEFT JOIN stage_product_documents d ON BINARY CAST(d.afs_document_id AS CHAR) = BINARY s.entity_id WHERE d.afs_document_id IS NULL',
                'example_sql' => 'SELECT s.entity_id FROM product_document_export_state s LEFT JOIN stage_product_documents d ON BINARY CAST(d.afs_document_id AS CHAR) = BINARY s.entity_id WHERE d.afs_document_id IS NULL ORDER BY s.entity_id ASC LIMIT 5',
            ],
        ];
    }

    /**
     * @param list<string> $tables
     */
    private function tablesExist(\PDO $stageDb, array $tables): bool
    {
        foreach ($tables as $table) {
            $stmt = $stageDb->prepare('SHOW TABLES LIKE :table');
            $stmt->execute([':table' => $table]);

            if (!$stmt->fetchColumn()) {
                return false;
            }
        }

        return true;
    }

    private function countRows(\PDO $stageDb, string $sql): int
    {
        return (int) $stageDb->query($sql)->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function exampleValues(\PDO $stageDb, string $sql): array
    {
        return array_map(
            static fn (mixed $value): string => (string) $value,
            $stageDb->query($sql)->fetchAll(\PDO::FETCH_COLUMN)
        );
    }
}
