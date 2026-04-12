.PHONY: migrate migrate-up migrate-down up down

migrate:
	@true

up: migrate-up

down: migrate-down

migrate-up:
	@if [ -z "$(FILE)" ]; then \
		echo "Usage: make migrate-up FILE=migrations/yyyymmdd-name.sql"; \
		exit 1; \
	fi
	docker compose exec -T db sh -lc 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"' < $(FILE)

migrate-down:
	@if [ -z "$(FILE)" ]; then \
		echo "Usage: make migrate-down FILE=migrations/yyyymmdd-name.sql"; \
		exit 1; \
	fi
	docker compose exec -T db sh -lc 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"' < $(FILE)
