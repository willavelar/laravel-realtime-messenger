.PHONY: up down build shell migrate migrate-fresh test fresh logs artisan

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

shell:
	docker compose exec app sh

migrate:
	docker compose exec app php artisan migrate

migrate-fresh:
	docker compose exec app php artisan migrate:fresh --seed

test:
	docker compose exec app php artisan test

logs:
	docker compose logs -f

artisan:
	docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))
