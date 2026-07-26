<?php

declare(strict_types=1);

namespace WelaApi;

use PDO;
use RuntimeException;
use Throwable;

final class CategorySyncService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function syncCategoryRequest(array $request): array
    {
        $category = \wela_required_array($request['category'] ?? null, 'Kategorie-Sync benoetigt einen Kategorieblock.');
        $categoryIdentity = \wela_allowed_field_map(
            \wela_required_array($category['identity'] ?? null, 'Kategorie-Sync benoetigt eine Kategorie-Identitaet.'),
            ['external_id']
        );
        $categoryColumns = \wela_allowed_field_map(
            \wela_required_array($category['columns'] ?? null, 'Kategorie-Sync benoetigt Kategoriespalten.'),
            \wela_allowed_tables()['xt_categories']['write_fields']
        );
        $translations = \wela_optional_array_list($request['translations'] ?? null, 'Kategorie-Sync-Uebersetzungen muessen eine Liste sein.');
        $seoUrls = \wela_optional_array_list($request['seo_urls'] ?? null, 'Kategorie-Sync-SEO-URLs muessen eine Liste sein.');

        $this->pdo->beginTransaction();

        try {
            $categoryResult = \wela_upsert_row($this->pdo, 'xt_categories', 'categories_id', $categoryIdentity, $categoryColumns);
            $categoryId = (int) ($categoryResult['primary_key_value'] ?? 0);

            if ($categoryId <= 0) {
                throw new RuntimeException('Kategorie-Sync konnte keine gueltige XT-Kategorie-ID ermitteln.');
            }

            foreach ($translations as $translation) {
                $languageCode = \wela_allowed_language($translation['language_code'] ?? null);
                $translationColumns = \wela_allowed_field_map(
                    \wela_required_array($translation['columns'] ?? null, 'Kategorie-Sync-Uebersetzung benoetigt Spalten.'),
                    \wela_allowed_tables()['xt_categories_description']['write_fields']
                );
                unset($translationColumns['categories_id']);

                \wela_upsert_row(
                    $this->pdo,
                    'xt_categories_description',
                    ['categories_id', 'language_code'],
                    [
                        'categories_id' => $categoryId,
                        'language_code' => $languageCode,
                    ],
                    $translationColumns
                );
            }

            $seoWrites = 0;

            foreach ($seoUrls as $seoUrl) {
                $languageCode = \wela_allowed_language($seoUrl['language_code'] ?? null);
                $seoColumns = \wela_allowed_field_map(
                    \wela_required_array($seoUrl['columns'] ?? null, 'Kategorie-Sync-SEO benoetigt Spalten.'),
                    \wela_allowed_tables()['xt_seo_url']['write_fields']
                );

                $linkType = isset($seoColumns['link_type']) ? (int) $seoColumns['link_type'] : 2;
                $storeId = isset($seoColumns['store_id']) ? (int) $seoColumns['store_id'] : 1;
                $seoIdentity = [
                    'link_type' => $linkType,
                    'link_id' => $categoryId,
                    'language_code' => $languageCode,
                    'store_id' => $storeId,
                ];

                unset($seoColumns['link_id'], $seoColumns['link_type'], $seoColumns['language_code'], $seoColumns['store_id']);

                if (($seoUrl['auto_generate'] ?? false) === true) {
                    $seoColumns = \wela_auto_generate_seo_columns(
                        $this->pdo,
                        is_string($seoUrl['auto_generate_class'] ?? null) ? (string) $seoUrl['auto_generate_class'] : 'category',
                        $linkType,
                        $categoryId,
                        $languageCode,
                        $storeId,
                        $seoColumns,
                        is_string($seoUrl['auto_generate_text'] ?? null) ? (string) $seoUrl['auto_generate_text'] : null
                    );
                    $seoColumns = \wela_apply_auto_generated_seo_update($this->pdo, $seoIdentity, $seoColumns);
                } else {
                    $seoColumns = \wela_preserve_existing_seo_url_columns($this->pdo, $seoIdentity, $seoColumns);
                }

                \wela_upsert_row(
                    $this->pdo,
                    'xt_seo_url',
                    ['link_type', 'link_id', 'language_code', 'store_id'],
                    $seoIdentity,
                    $seoColumns
                );
                $seoWrites++;
            }

            \wela_flush_seo_redirect_buffer($this->pdo);
            $this->pdo->commit();

            return [
                'category_id' => $categoryId,
                'category_action' => $categoryResult['action'] ?? null,
                'translations' => count($translations),
                'seo_urls' => $seoWrites,
            ];
        } catch (Throwable $exception) {
            \wela_clear_seo_redirect_buffer($this->pdo);
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function syncCategoriesBatchRequest(array $request): array
    {
        $items = \wela_optional_array_list($request['items'] ?? null, 'Kategorie-Batch benoetigt eine Liste von Items.');
        if ($items === []) {
            \wela_respond(400, [
                'ok' => false,
                'error' => 'Kategorie-Batch benoetigt mindestens ein Item.',
            ]);
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($items as $item) {
            $queueId = (int) ($item['queue_id'] ?? 0);
            $entityId = trim((string) ($item['entity_id'] ?? ''));
            $syncPayload = \wela_required_array($item['sync_payload'] ?? null, 'Kategorie-Batch-Item benoetigt sync_payload.');

            try {
                $data = $this->syncCategoryRequest($syncPayload);
                $results[] = [
                    'queue_id' => $queueId,
                    'entity_id' => $entityId,
                    'category_identity' => $entityId,
                    'ok' => true,
                    'data' => $data,
                ];
                $successCount++;
            } catch (Throwable $exception) {
                $results[] = [
                    'queue_id' => $queueId,
                    'entity_id' => $entityId,
                    'category_identity' => $entityId,
                    'ok' => false,
                    'error' => $exception->getMessage(),
                ];
                $errorCount++;
            }
        }

        return [
            'results' => $results,
            'success_count' => $successCount,
            'error_count' => $errorCount,
        ];
    }
}
