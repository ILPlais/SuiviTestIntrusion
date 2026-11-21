#!/bin/sh
#
# Point d'entrée de l'image (socle-2), commun aux services app, queue et scheduler.
#
# 1. Vérifie la configuration minimale (APP_KEY).
# 2. Prépare le volume des données (arborescence de storage/, base SQLite).
# 3. Met en cache la configuration, les routes et les vues.
# 4. Pour le service app seulement (commande apache2-foreground) : applique les
#    migrations, avant que queue et scheduler ne démarrent (depends_on: service_healthy).
# 5. Lance la commande du service.

set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
	echo "APP_KEY est vide : générez une clé (voir 42.01) et ajoutez-la à docker/.env." >&2
	exit 1
fi

# Le volume peut être neuf ou provenir d'une version antérieure.
mkdir -p storage/app/private storage/app/public storage/logs storage/database \
	storage/framework/cache/data storage/framework/sessions storage/framework/views

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
	base="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
	[ -e "$base" ] || touch "$base"
fi

php artisan optimize --no-interaction >/dev/null

if [ "${1:-}" = "apache2-foreground" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
	php artisan migrate --force --no-interaction
fi

exec "$@"
