#!/bin/sh
set -e
cd /var/www/html

# 1. dépendances (utile quand le code est monté en volume sans vendor/)
[ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist

# 2. attendre MySQL
echo "Attente de la base de données…"
i=0
until php -r 'new PDO(getenv("DB_DSN") ?: "mysql:host=db;dbname=resto", getenv("DB_USER") ?: "resto", getenv("DB_PASSWORD") ?: "resto");' 2>/dev/null; do
  i=$((i+1)); [ "$i" -gt 60 ] && { echo "MySQL injoignable"; exit 1; }
  sleep 2
done

# 3. schéma + données de démonstration au premier lancement
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
if [ "$(php bin/console dbal:run-sql 'SELECT COUNT(*) FROM app_user' --force-fetch 2>/dev/null | grep -Eo '[0-9]+' | tail -1)" = "0" ]; then
  echo "Base vide : chargement du jeu de démonstration"
  php bin/console doctrine:fixtures:load --no-interaction
fi

chown -R www-data:www-data var
exec docker-php-entrypoint "$@"
