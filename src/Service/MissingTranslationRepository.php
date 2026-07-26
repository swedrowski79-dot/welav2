<?php

declare(strict_types=1);

final class MissingTranslationRepository
{
    private PDO $connection;
    private string $driver;
    private ?PDOStatement $upsertMissingArticleStatement = null;
    private ?PDOStatement $upsertMissingCategoryStatement = null;
    private ?PDOStatement $markArticleDoneStatement = null;
    private ?PDOStatement $markCategoryDoneStatement = null;

    public function __construct(private array $connectionConfig)
    {
        $this->connection = ConnectionFactory::create($connectionConfig);
        $this->driver = (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($this->driver === 'sqlite') {
            $this->connection->exec('PRAGMA foreign_keys = ON');
            $this->connection->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public function ensureSchema(): void
    {
        if ($this->driver === 'mysql') {
            $this->connection->exec(
                "CREATE TABLE IF NOT EXISTS missing_article_translations (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    article_id VARCHAR(255) NOT NULL,
                    article_number VARCHAR(255) NULL,
                    article_name TEXT NULL,
                    language VARCHAR(10) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'missing',
                    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_missing_article_translation (article_id, language)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $this->connection->exec(
                "CREATE TABLE IF NOT EXISTS missing_category_translations (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    category_id VARCHAR(255) NOT NULL,
                    category_name TEXT NULL,
                    language VARCHAR(10) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'missing',
                    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_missing_category_translation (category_id, language)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            return;
        }

        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS missing_article_translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id TEXT NOT NULL,
                article_number TEXT,
                article_name TEXT,
                language TEXT NOT NULL,
                status TEXT DEFAULT 'missing',
                detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(article_id, language)
            )"
        );

        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS missing_category_translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id TEXT NOT NULL,
                category_name TEXT,
                language TEXT NOT NULL,
                status TEXT DEFAULT 'missing',
                detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(category_id, language)
            )"
        );
    }

    public function upsertMissingArticle(string $articleId, ?string $articleNumber, ?string $articleName, string $language): void
    {
        $stmt = $this->upsertMissingArticleStatement ??= $this->connection->prepare(
            $this->driver === 'mysql'
                ? "INSERT INTO missing_article_translations (
                    article_id,
                    article_number,
                    article_name,
                    language,
                    status,
                    detected_at,
                    last_checked_at
                ) VALUES (
                    :article_id,
                    :article_number,
                    :article_name,
                    :language,
                    'missing',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    article_number = VALUES(article_number),
                    article_name = VALUES(article_name),
                    status = 'missing',
                    last_checked_at = CURRENT_TIMESTAMP"
                : "INSERT INTO missing_article_translations (
                    article_id,
                    article_number,
                    article_name,
                    language,
                    status,
                    detected_at,
                    last_checked_at
                ) VALUES (
                    :article_id,
                    :article_number,
                    :article_name,
                    :language,
                    'missing',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON CONFLICT(article_id, language) DO UPDATE SET
                    article_number = excluded.article_number,
                    article_name = excluded.article_name,
                    status = 'missing',
                    last_checked_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            ':article_id' => $articleId,
            ':article_number' => $articleNumber,
            ':article_name' => $articleName,
            ':language' => $language,
        ]);
    }

    public function upsertMissingCategory(string $categoryId, ?string $categoryName, string $language): void
    {
        $stmt = $this->upsertMissingCategoryStatement ??= $this->connection->prepare(
            $this->driver === 'mysql'
                ? "INSERT INTO missing_category_translations (
                    category_id,
                    category_name,
                    language,
                    status,
                    detected_at,
                    last_checked_at
                ) VALUES (
                    :category_id,
                    :category_name,
                    :language,
                    'missing',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    category_name = VALUES(category_name),
                    status = 'missing',
                    last_checked_at = CURRENT_TIMESTAMP"
                : "INSERT INTO missing_category_translations (
                    category_id,
                    category_name,
                    language,
                    status,
                    detected_at,
                    last_checked_at
                ) VALUES (
                    :category_id,
                    :category_name,
                    :language,
                    'missing',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON CONFLICT(category_id, language) DO UPDATE SET
                    category_name = excluded.category_name,
                    status = 'missing',
                    last_checked_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            ':category_id' => $categoryId,
            ':category_name' => $categoryName,
            ':language' => $language,
        ]);
    }

    public function markArticleTranslationDone(string $articleId, string $language): void
    {
        $stmt = $this->markArticleDoneStatement ??= $this->connection->prepare(
            "UPDATE missing_article_translations
             SET status = 'done',
                 last_checked_at = CURRENT_TIMESTAMP
             WHERE article_id = :article_id
               AND language = :language"
        );

        $stmt->execute([
            ':article_id' => $articleId,
            ':language' => $language,
        ]);
    }

    public function markCategoryTranslationDone(string $categoryId, string $language): void
    {
        $stmt = $this->markCategoryDoneStatement ??= $this->connection->prepare(
            "UPDATE missing_category_translations
             SET status = 'done',
                 last_checked_at = CURRENT_TIMESTAMP
             WHERE category_id = :category_id
               AND language = :language"
        );

        $stmt->execute([
            ':category_id' => $categoryId,
            ':language' => $language,
        ]);
    }
}
