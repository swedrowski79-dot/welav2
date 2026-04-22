# Task

Fehlgeschlagene Migration `019_add_attribute_values_to_raw_extra_attribute_translations.sql` untersuchen und den gesamten Migrationslauf pruefen.

# Files read

- `src/Web/Repository/MigrationRepository.php`
- `database.sql`
- `migrations/019_add_attribute_values_to_raw_extra_attribute_translations.sql`
- `public/index.php`
- `src/Web/bootstrap.php`
- `src/Web/Repository/StageConnection.php`

# Changed files

- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-21-migration-tracker-version-width.md`

# Summary

- Die eigentliche Migration `019` war nicht defekt.
- Ursache war der Migrations-Tracker:
  - `schema_migrations.version` war als `VARCHAR(50)` definiert.
  - Mehrere Versionsnamen sind laenger, z. B.:
    - `019_add_attribute_values_to_raw_extra_attribute_translations`
    - `021_add_intro_text_to_raw_extra_article_translations`
- `MigrationRepository` legt neue Installationen jetzt direkt mit `VARCHAR(191)` an.
- Bestehende Installationen werden beim naechsten Migrationslauf automatisch per `ALTER TABLE` auf `VARCHAR(191)` angehoben.
- Danach liefen die ausstehenden Migrationen wieder sauber durch:
  - `019_add_attribute_values_to_raw_extra_attribute_translations.sql`
  - `020_create_documents_file_table.sql`
  - `021_add_intro_text_to_raw_extra_article_translations.sql`

# Open points

- Kein offener technischer Rest im Migrations-Tracker.
- Fachliche Pruefungen der neu angelegten Tabellen/Inhalte bleiben wie gehabt Teil der normalen Prozessvalidierung.

# Validation steps

- `docker compose exec -T php php -l src/Web/Repository/MigrationRepository.php`
- `docker compose exec -T mysql mysql -ustage -pstage stage_sync -e "SHOW COLUMNS FROM schema_migrations LIKE 'version';"`
- `docker compose exec -T php php -r 'require "src/Web/bootstrap.php"; $repo = new App\\Web\\Repository\\MigrationRepository(App\\Web\\Repository\\StageConnection::make(), getcwd() . "/migrations"); $executed = $repo->runPending(); echo json_encode($executed, JSON_UNESCAPED_SLASHES), PHP_EOL;'`
- erneuter Datenbank-Check auf `schema_migrations.version = varchar(191)` sowie die eingetragenen Versionen `019` und `021`

# Recommended next step

Die Weboberflaeche unter `Status` einmal oeffnen und bestaetigen, dass dort jetzt keine ausstehenden Migrationen mehr gemeldet werden.
