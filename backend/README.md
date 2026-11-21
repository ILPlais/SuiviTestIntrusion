# SuiviTestIntrusion — API

API REST Laravel de SuiviTestIntrusion, versionnée sous `/api/v1/` ([11.03 Serveur et API REST](../docs/10-19%20Conception/11%20Cahier%20des%20charges/11.03%20Serveur%20et%20API%20REST.md)).

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve        # http://localhost:8000/api/v1/health
```

La base est choisie par `DB_CONNECTION` dans `.env` : `sqlite` (par défaut), `mysql`, `mariadb` ou `pgsql` ; `.env.example` donne un exemple pour chacune.

## Tests

```bash
php artisan test
```
