COMPOSE = docker compose

.PHONY: up down shell schema-import import-all merge expand

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

shell:
	$(COMPOSE) exec php bash

schema-import:
	$(COMPOSE) exec -T mysql mysql -uroot -proot stage_sync < database.sql

import-all:
	$(COMPOSE) exec php php run_import_all.php

merge:
	$(COMPOSE) exec php php run_merge.php

expand:
	$(COMPOSE) exec php php run_expand.php
