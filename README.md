# Resto Manager — PFE Challenge EMC, Sujet 2

Gestion d'un restaurant de 60 couverts : **carte, recettes, prise de commande, cuisine, stocks, achats, marges**.
Symfony 7.4 · PHP 8.3 · MySQL 8 · Doctrine ORM/Migrations · Twig.

**Sujet choisi :** n° 2 — *Gestion d'un restaurant : carte, cuisine et stocks*.

## Niveaux atteints

| Niveau | État | Où le voir |
|---|---|---|
| 1 — Modéliser | ✅ | `docs/MODELE.md`, `migrations/`, `src/DataFixtures/` (23 plats, 61 ingrédients, 6 fournisseurs, 5 préparations dont une imbriquée, 2 menus, 14 jours d'historique), back-office `/admin/*` |
| 2 — Règles | ✅ | `src/Service/` ; 68 tests dans `tests/` |
| 3 — Produit | ✅ | rôles serveur/cuisine/gérant, salle, cuisine, tableau de bord |
| 4 — API externe | ✅ (testé sur réponses simulées) | Open Food Facts, allergènes sur la carte publique `/carte` |
| 5 — Livrer | ✅ écrit, ⚠️ Docker non exécuté ici | `Dockerfile`, `docker-compose.yml`, `INSTALL.md` |

## Lancer

```bash
docker compose up --build      # puis http://localhost:8080
```
Comptes (mot de passe `password`) : `gerant@resto.test`, `serveur@resto.test`, `cuisine@resto.test`. Détails, installation locale et dépannage : **[INSTALL.md](INSTALL.md)**.

Tests : `composer test:prepare && vendor/bin/phpunit` (base MySQL `resto_test` requise).

## Ce qui fonctionne

- **Carte** : plats, catégories, prix TTC/TVA, menus composés (temps + plats au choix), retrait de la carte.
- **Recettes** : ingrédients en g/kg/ml/cl/l/pièce, **préparations de base** réutilisables et imbriquables (sauce → plat), coût matière et marge par plat.
- **Salle** : plan des tables, une commande ouverte par table, ajout de plats/menus avec note, disponibilité en direct (ingrédient manquant indiqué), annulation, encaissement.
- **Cuisine** : file des plats (à lancer / en préparation / prêts), rafraîchissement automatique, déclaration des pertes.
- **Stocks** : décrément automatique, plat indisponible dès qu'un ingrédient manque, stock jamais négatif, journal de tous les mouvements, achats fournisseurs avec réception et **coût moyen pondéré**, **inventaire** avec écarts valorisés, pertes motivées.
- **Gérant** : tableau de bord (CA, marge brute, pertes, valeur du stock, meilleures ventes, marge par plat, suggestions de réapprovisionnement groupées par fournisseur → bon de commande pré-rempli).
- **Allergènes** : via Open Food Facts (code-barres) ou déclaration manuelle, agrégés par plat à travers les préparations, avec avertissement quand l'information est absente ou incomplète.
- **Sécurité** : authentification par formulaire, rôles hiérarchiques, CSRF sur tous les POST.

## Ce qui reste à faire

Lots/DLC et FIFO, production de préparations en lot, saisonnalité automatique de la carte, réception partielle des achats, addition partagée, impression des tickets, rafraîchissement temps réel (Turbo/Mercure), gestion avancée des utilisateurs (modification, changement de mot de passe), tests de charge/concurrence multi-processus. Voir `docs/DECISIONS.md`.

## Documentation

- [`docs/MODELE.md`](docs/MODELE.md) — modèle de données (Mermaid) et choix.
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — journal des décisions, réponses aux 5 questions.
- [`INSTALL.md`](INSTALL.md) — installation, comptes, dépannage.

