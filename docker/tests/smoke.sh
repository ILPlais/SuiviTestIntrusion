#!/usr/bin/env bash
#
# Test de fumée de la pile Docker Compose (socle-2), lancé par la CI (job « docker »)
# ou à la main depuis la racine du dépôt : docker/tests/smoke.sh
#
# Vérifie, sur une pile construite depuis les sources :
# - les services app, queue et scheduler, leurs commandes et le volume des données ;
# - la SPA et l'API servies sur la même origine, en HTTP sur 127.0.0.1 seulement ;
# - l'exemple de proxy inverse (nginx) : TLS 1.2 et 1.3, corps de 105 Mio accepté
#   de bout en bout (proxy, Apache, PHP) ;
# - la base PostgreSQL optionnelle (profil « database »).
#
# Prérequis : Docker avec Compose v2, curl, jq, openssl. Le script refuse de tourner
# si docker/.env existe déjà, pour ne jamais écraser la configuration d'une instance.

set -o errexit -o nounset -o pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
dossier_docker="$racine/docker"
fichier_env="$dossier_docker/.env"
projet="suivi-smoke"
temporaire="$(mktemp --directory)"
compose=(docker compose --project-name "$projet" --project-directory "$dossier_docker" --file "$dossier_docker/compose.yaml")

if [[ -e "$fichier_env" ]]; then
	echo "docker/.env existe déjà : test de fumée annulé pour ne pas le modifier." >&2
	exit 2
fi

# Nettoyage systématique, y compris après un échec.
nettoyer() {
	"${compose[@]}" --profile database down --volumes --remove-orphans >/dev/null 2>&1 || true
	docker rm --force suivi-smoke-proxy >/dev/null 2>&1 || true
	rm --force "$fichier_env"
	rm --recursive --force "$temporaire"
}
trap nettoyer EXIT

# Affiche une étape, puis un échec explicite si la condition est fausse.
etape() { echo "--- $*"; }
echouer() {
	echo "ÉCHEC : $*" >&2
	"${compose[@]}" logs --no-color --tail 50 >&2 || true
	exit 1
}

# Écrit docker/.env depuis l'exemple, avec une clé neuve et des réglages éventuels.
ecrire_env() {
	sed "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" "$dossier_docker/.env.example" >"$fichier_env"
	for reglage in "$@"; do
		local nom="${reglage%%=*}"
		sed --in-place "/^#\? \?${nom}=/d" "$fichier_env"
		echo "$reglage" >>"$fichier_env"
	done
}

# Vrai si la page contient le conteneur de la SPA. La sortie est lue en entier avant
# le test : un « curl | grep --quiet » peut échouer sous pipefail (tube fermé tôt).
contient_spa() { local page; page="$("$@")" && [[ "$page" == *'id="app"'* ]]; }

# Attend que la commande réussisse (au plus 60 essais d'une seconde).
attendre() {
	for _ in $(seq 60); do
		if "$@" >/dev/null 2>&1; then return 0; fi
		sleep 1
	done
	return 1
}

base="http://127.0.0.1:8080"

# ---------------------------------------------------------------------------
etape "Configuration du Compose"
ecrire_env
"${compose[@]}" config --quiet || echouer "compose.yaml invalide"
config_json="$("${compose[@]}" config --format json)"

[[ "$(jq --raw-output '.services.queue.command | join(" ")' <<<"$config_json")" == "php artisan queue:work --tries=3 --backoff=10,60,300" ]] \
	|| echouer "commande du service queue"
[[ "$(jq --raw-output '.services.scheduler.command | join(" ")' <<<"$config_json")" == "php artisan schedule:work" ]] \
	|| echouer "commande du service scheduler"
[[ "$(jq --raw-output '.services.app.ports[0].host_ip' <<<"$config_json")" == "127.0.0.1" ]] \
	|| echouer "le port HTTP de app doit être limité à 127.0.0.1"
# Le profil « database » est inactif ici : on lit la configuration complète pour le vérifier.
[[ "$("${compose[@]}" --profile database config --format json | jq --raw-output '.services.database.profiles[0]')" == "database" ]] \
	|| echouer "la base doit être optionnelle (profil database)"
for service in app queue scheduler; do
	[[ "$(jq --raw-output ".services.$service.volumes[] | select(.target == \"/var/www/html/storage\") | .source" <<<"$config_json")" == "donnees" ]] \
		|| echouer "volume des données absent du service $service"
done

# ---------------------------------------------------------------------------
etape "Démarrage de la pile (SQLite)"
"${compose[@]}" up --build --detach --wait --wait-timeout 180 || echouer "démarrage de la pile"

for service in app queue scheduler; do
	[[ $'\n'"$("${compose[@]}" ps --status running --services)"$'\n' == *$'\n'"$service"$'\n'* ]] \
		|| echouer "service $service arrêté"
done

etape "SPA et API sur la même origine"
curl --silent --fail "$base/up" >/dev/null || echouer "/up"
contient_spa curl --silent --fail "$base/" || echouer "la racine doit servir la SPA"
contient_spa curl --silent --fail "$base/engagements/12" || echouer "lien profond de la SPA"
[[ "$(curl --silent --fail "$base/api/v1/health" | jq --raw-output '.status, .database' | paste --serial --delimiters=,)" == "ok,ok" ]] \
	|| echouer "santé de l'API"
[[ "$(curl --silent "$base/api/v1/inexistant" | jq --raw-output '.code')" == "not_found" ]] \
	|| echouer "enveloppe d'erreur JSON de l'API"
entetes="$(curl --silent --head "$base/" | tr --delete '\r' | tr '[:upper:]' '[:lower:]')"
[[ $'\n'"$entetes"$'\n' == *$'\n'"server: apache"$'\n'* ]] || echouer "version d'Apache exposée"
if [[ "$entetes" == *"x-powered-by"* ]]; then
	echouer "version de PHP exposée"
fi

etape "Réglages de PHP et du conteneur"
[[ "$("${compose[@]}" exec --no-TTY app php --run 'echo ini_get("upload_max_filesize"), "/", ini_get("post_max_size");')" == "110M/110M" ]] \
	|| echouer "limites de taille de PHP"
[[ "$("${compose[@]}" exec --no-TTY app id --user --name)" == "www-data" ]] || echouer "app doit tourner sans les droits root"
"${compose[@]}" exec --no-TTY app test -w storage/app/private || echouer "stockage des preuves non inscriptible"
"${compose[@]}" exec --no-TTY queue php artisan migrate:status --no-interaction >/dev/null || echouer "base inaccessible depuis queue"

# ---------------------------------------------------------------------------
etape "Exemple de proxy inverse (nginx)"
mkdir --parents "$temporaire/certs"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=pentest.example.org" \
	-keyout "$temporaire/certs/privkey.pem" -out "$temporaire/certs/fullchain.pem" >/dev/null 2>&1
chmod a+r "$temporaire/certs/privkey.pem"
docker run --detach --name suivi-smoke-proxy --network host \
	--volume "$dossier_docker/proxy/nginx.conf.example:/etc/nginx/conf.d/default.conf:ro" \
	--volume "$temporaire/certs:/etc/ssl/suivi:ro" \
	nginx:stable >/dev/null
https=(curl --silent --insecure --resolve pentest.example.org:443:127.0.0.1)
attendre "${https[@]}" --fail https://pentest.example.org/up || { docker logs suivi-smoke-proxy >&2; echouer "proxy nginx"; }

"${https[@]}" --fail --tlsv1.3 https://pentest.example.org/up >/dev/null || echouer "TLS 1.3"
"${https[@]}" --fail --tlsv1.2 --tls-max 1.2 https://pentest.example.org/up >/dev/null || echouer "TLS 1.2"
if "${https[@]}" --tlsv1.1 --tls-max 1.1 https://pentest.example.org/up >/dev/null 2>&1; then
	echouer "TLS 1.1 doit être refusé"
fi
[[ "$(curl --silent --output /dev/null --write-out '%{http_code} %{redirect_url}' --resolve pentest.example.org:80:127.0.0.1 http://pentest.example.org/up)" == "301 https://pentest.example.org/up" ]] \
	|| echouer "redirection de HTTP vers HTTPS"
contient_spa "${https[@]}" --fail https://pentest.example.org/ || echouer "SPA derrière le proxy"

# Un corps de 105 Mio traverse le proxy, Apache et PHP jusqu'au routeur : la route
# n'accepte pas POST, et l'enveloppe d'erreurs de l'API (socle-4) rend ce 405 en 404
# « not_found ». Une limite trop basse donnerait 413 (proxy, Apache) ou 422 (PHP, Laravel).
head --bytes $((105 * 1024 * 1024)) /dev/zero >"$temporaire/corps.bin"
reponse="$("${https[@]}" --write-out ' %{http_code}' --header 'Accept: application/json' \
	--header 'Content-Type: application/octet-stream' --data-binary "@$temporaire/corps.bin" \
	https://pentest.example.org/api/v1/health)"
[[ "$reponse" == *'"code":"not_found"'*' 404' ]] || echouer "corps de 105 Mio refusé en chemin : $reponse"

# ---------------------------------------------------------------------------
etape "Base PostgreSQL optionnelle (profil database)"
"${compose[@]}" down --volumes >/dev/null
ecrire_env DB_CONNECTION=pgsql DB_HOST=database DB_PORT=5432 DB_DATABASE=suivi DB_USERNAME=suivi "DB_PASSWORD=$(openssl rand -hex 16)"
"${compose[@]}" --profile database up --build --detach --wait --wait-timeout 180 || echouer "démarrage avec PostgreSQL"
[[ "$(curl --silent --fail "$base/api/v1/health" | jq --raw-output '.database')" == "ok" ]] || echouer "santé de l'API avec PostgreSQL"
# Les migrations appliquées par app doivent se trouver dans PostgreSQL, pas dans SQLite.
migrations="$("${compose[@]}" --profile database exec --no-TTY database \
	psql --username=suivi --dbname=suivi --tuples-only --no-align --command='SELECT count(*) FROM migrations')" \
	|| echouer "table des migrations absente de PostgreSQL"
[[ "$migrations" -gt 0 ]] || echouer "l'application doit utiliser PostgreSQL"

echo "Test de fumée réussi."
