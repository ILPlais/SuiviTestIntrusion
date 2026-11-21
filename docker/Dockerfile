# syntax=docker/dockerfile:1
#
# Image de SuiviTestIntrusion (socle-2) : API Laravel et SPA Vue compilée, servies
# en HTTP par Apache et PHP sur le même port (même origine, 13.06). La même image
# fait tourner les services app, queue et scheduler du Compose ; seule la commande change.
#
# Contexte de construction : la racine du dépôt (voir compose.yaml).

ARG PHP_VERSION=8.3
ARG NODE_VERSION=22

# --- 1. SPA : compilation de frontend/ -----------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS spa
WORKDIR /src/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-fund --no-audit
COPY frontend/ ./
RUN npm run build

# --- 2. Base PHP : Apache, mod_php et extensions des trois SGBD ----------------
FROM php:${PHP_VERSION}-apache-bookworm AS base
RUN set -eux; \
	apt-get update; \
	apt-get install --yes --no-install-recommends libicu-dev libzip-dev libpq-dev unzip; \
	docker-php-ext-install --jobs "$(nproc)" intl bcmath zip opcache pdo_mysql pdo_pgsql; \
	rm --recursive --force /var/lib/apt/lists/*

# --- 3. Dépendances PHP de production ------------------------------------------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /src
# Les dépendances d'abord, pour profiter du cache tant que composer.lock ne change pas.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
	&& php artisan package:discover --no-interaction

# --- 4. Image finale -----------------------------------------------------------
FROM base AS app
LABEL org.opencontainers.image.title="SuiviTestIntrusion" \
	org.opencontainers.image.source="https://github.com/ILPlais/SuiviTestIntrusion" \
	org.opencontainers.image.licenses="MIT"

# PHP en production, avec les limites de taille alignées sur la GED (13.06).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/suivi.ini "$PHP_INI_DIR/conf.d/zz-suivi.ini"

# Apache sans les droits root : écoute sur 8080, site unique, version masquée.
COPY docker/apache/suivi.conf /etc/apache2/sites-available/000-default.conf
RUN set -eux; \
	a2enmod rewrite headers; \
	sed --in-place 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
	chown --recursive www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /src ./
# La SPA compilée rejoint public/ : index.html et les ressources à empreinte (assets/).
COPY --from=spa --chown=www-data:www-data /src/frontend/dist/ ./public/
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/suivi-entrypoint

USER www-data
EXPOSE 8080
# Volume des données : preuves chiffrées, base SQLite, journaux, sessions et cache.
VOLUME ["/var/www/html/storage"]
# Contrôle de santé : défini dans compose.yaml pour le seul service app (/up), car
# queue et scheduler utilisent la même image sans serveur web.

ENTRYPOINT ["suivi-entrypoint"]
CMD ["apache2-foreground"]
