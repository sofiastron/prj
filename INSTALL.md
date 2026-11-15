# Installation

Deux façons de lancer l'application : **Docker (recommandé, une commande)** ou **en local** (PHP + MySQL installés).

> ⚠️ Honnêteté sur les tests : le projet a été développé et testé (68 tests) avec PHP 8.3 et MySQL 8.0 directement installés. Les fichiers Docker (`Dockerfile`, `docker-compose.yml`, `docker/entrypoint.sh`) ont été écrits avec soin mais **n'ont pas pu être exécutés** dans l'environnement de développement (pas de Docker disponible). Si une étape échoue, voyez « Dépannage » ci-dessous.

## Option A — Docker

### Prérequis
- Docker Engine 24+ avec le plugin **Compose v2** (`docker compose version`) — Docker Desktop sous Windows/macOS convient.
- Port **8080** libre (modifiable dans `docker-compose.yml`).
- Accès Internet au premier lancement (téléchargement des images et des dépendances Composer).

### Lancement
```bash
git clone <URL-du-dépôt> resto-manager
cd resto-manager
docker compose up --build
```
Au premier démarrage, le conteneur `app` : installe les dépendances, attend MySQL, applique les migrations, puis **charge les données de démonstration** (base vide uniquement). Quand le journal affiche `apache2 -D FOREGROUND`, ouvrez **http://localhost:8080**.

Aucun fichier `.env.local` n'est nécessaire : `docker-compose.yml` fournit les variables. Pour changer les mots de passe, créez un fichier `.env` à côté de `docker-compose.yml` avec `DB_PASSWORD=…`, `DB_ROOT_PASSWORD=…`, `APP_SECRET=…` (voir `.env.example`).

### Comptes de démonstration
Mot de passe de tous les comptes : `password` (**données de démonstration uniquement — à changer hors démo**).

| Rôle | E-mail | Arrive sur |
|---|---|---|
| Gérant | `gerant@resto.test` | tableau de bord, tout le back-office |
| Serveur | `serveur@resto.test` (ou `serveur2@`) | plan de salle et prise de commande |
| Cuisine | `cuisine@resto.test` | écran cuisine, déclaration des pertes |

La carte publique (avec allergènes) est visible sans connexion : http://localhost:8080/carte

### Parcours de démonstration conseillé (5 minutes)
1. Connectez-vous en **serveur** : deux tables (T3, T5) ont déjà une commande ouverte. Ouvrez-en une, ajoutez un plat ou un menu. Un plat sans stock suffisant s'affiche « Indisponible » avec l'ingrédient manquant.
2. Dans un autre navigateur, connectez-vous en **cuisine** : lancez un plat, marquez-le prêt (l'écran se rafraîchit seul).
3. Revenez en serveur : « Servir » puis « Encaisser ».
4. En **gérant** : *Tableau de bord* (marges, meilleures ventes, réapprovisionnement → « Créer le bon de commande »), *Achats* (réceptionner), *Inventaires*, *Ingrédients* (↻ OFF pour interroger Open Food Facts).

### Lancer les tests dans Docker
```bash
docker compose exec app composer test:prepare   # crée et migre la base resto_test (une fois)
docker compose exec app vendor/bin/phpunit
```
Résultat attendu : `OK, but incomplete, skipped, or risky tests!` avec 1 test ignoré (le test de fumée sur les données de démo, volontairement conditionnel).

### Remise à zéro
```bash
docker compose down -v          # supprime la base : tout est rechargé au prochain « up »
docker compose exec app composer db:reset   # ou : recharger seulement les données de démo
```

## Option B — Installation locale

### Prérequis
PHP **8.2+** avec les extensions `pdo_mysql`, `intl`, `bcmath`, `ctype`, `iconv`, `mbstring`, `xml`, `zip` ; Composer 2 ; MySQL **8.0** (≥ 8.0.16 pour les contraintes CHECK).

### Étapes
```bash
composer install
# Base et utilisateur MySQL (adaptez le mot de passe) :
mysql -uroot -p -e "CREATE DATABASE resto CHARACTER SET utf8mb4; \
  CREATE USER 'resto'@'localhost' IDENTIFIED BY 'resto'; \
  GRANT ALL ON \`resto%\`.* TO 'resto'@'localhost';"
cp .env.example .env.local      # puis ajustez DATABASE_URL si besoin
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n     # données de démonstration
php -S 127.0.0.1:8000 -t public               # http://127.0.0.1:8000
```
Tests : `composer test:prepare && vendor/bin/phpunit`.

## Open Food Facts
L'application interroge `https://world.openfoodfacts.org` (API gratuite, sans clé) depuis le bouton **↻ OFF** de la liste des ingrédients, ou en masse : `php bin/console app:allergens:refresh`. Trois ingrédients de démonstration ont un code-barres (Coca-Cola, Spaghetti Barilla, Nutella) et des allergènes pré-remplis ; deux autres (« Harissa du commerce », « Ras el hanout du souk ») ont volontairement une information **inconnue** pour montrer l'avertissement sur la carte. Sans Internet, l'application fonctionne normalement : l'erreur est affichée sur la ligne de l'ingrédient.

## Dépannage

| Symptôme | Cause probable / solution |
|---|---|
| `port is already allocated` | Le port 8080 est pris : changez `"8080:80"` dans `docker-compose.yml`. |
| `entrypoint.sh: not found` / `\r: command not found` (Windows) | Fins de ligne CRLF. `git config core.autocrlf false`, re-clonez ; le fichier `.gitattributes` force LF. |
| `MySQL injoignable` au démarrage | Premier démarrage lent : relancez `docker compose up`. Vérifiez `docker compose logs db`. |
| Page blanche / erreur 500 | `docker compose logs app` et `var/log/dev.log`. Après un changement de code : `docker compose exec app php bin/console cache:clear`. |
| `Permission denied` sur `var/` | `docker compose exec app chown -R www-data:www-data var`. |
| `Access denied … resto_test` en lançant les tests | Le volume MySQL existait avant l'ajout des droits : `docker compose down -v` puis `up --build`. |
| Open Food Facts : « injoignable » | Pas d'accès Internet depuis le conteneur, ou service momentanément indisponible. Réessayez ; les allergènes déjà enregistrés sont conservés. |
| `composer install` très lent | Normal au premier build (téléchargement). Les builds suivants utilisent le cache Docker. |
