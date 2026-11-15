# Journal des décisions et hypothèses

Les entrées sont datées du jour où la décision a été prise. Les cinq premières répondent aux « Questions à trancher » de l'énoncé.

## Réponses aux questions de l'énoncé

### Q1 — Le coût d'un plat déjà servi change-t-il si le prix d'achat d'un ingrédient évolue ? *(2026-11-14)*

**Non.** Au moment de la commande, la ligne (`order_line`) fige le prix de vente, la TVA et le **coût matière** du plat (calculé au coût moyen pondéré — CUMP — des ingrédients à cet instant). Une vente est un fait historique : si l'agneau double de prix demain, les marges des repas d'hier ne doivent pas être réécrites, sinon le tableau de bord mentirait sur le passé.

À l'inverse, le **coût courant** d'un plat (fiche plat, marge théorique, plats « à surveiller ») est recalculé en continu avec le CUMP actuel : il dit ce que coûterait un plat commandé *maintenant*. Le tableau de bord montre les deux : marge théorique et marge réalisée sur la période. Le CUMP est recalculé à chaque réception d'achat : `(stock × coût actuel + qté reçue × prix d'achat) / nouveau stock`. Test : `StockRulesTest::testSoldDishKeepsItsCostWhenIngredientPriceChanges`.

*Alternative écartée :* FIFO par lot. Plus fidèle mais demande de suivre chaque lot ; disproportionné pour ce périmètre (voir « avec une semaine de plus »).

### Q2 — Comment enregistrer les pertes (casse, péremption, erreur en cuisine) ? *(2026-11-14)*

Une perte est un **mouvement de stock** de type `loss` : quantité négative, motif (`casse`, `péremption`, `erreur en cuisine`, `autre`), commentaire libre, auteur, date et **coût unitaire du moment** — donc valorisée en MAD. Elle passe par `StockService::recordLoss`, avec les mêmes garanties que toute sortie (transaction, verrou, stock jamais négatif, unité compatible). Écran : *Pertes* (cuisine et gérant).

Cas particulier : un plat **annulé après son lancement en cuisine** (`PREPARING`/`READY`) est marqué `wasted` : ses ingrédients ne reviennent pas en stock et sont comptés comme perte (coût figé de la ligne). Annulé avant lancement (`PENDING`), le stock est restitué par un mouvement `sale_cancel`.

Le tableau de bord additionne pertes déclarées et plats gâchés sur la période.

### Q3 — Un menu « entrée + plat + dessert au choix » est-il un plat comme les autres ? *(2026-11-14)*

**Non.** Un plat a une recette et consomme des stocks ; un menu est une **offre commerciale** : un prix fixe et, par « temps », une liste de plats entre lesquels le client choisit. Il n'a pas de recette propre.

Conséquences de conception : `Menu → MenuCourse → plats` ; commander un menu crée un `OrderMenu` (prix de la formule figé) et une `OrderLine` par plat choisi, de sorte que **le décompte des stocks, la file cuisine et les statistiques par plat fonctionnent sans cas particulier**. La commande est **tout ou rien** : si un seul plat est indisponible, rien n'est consommé. Le prix de la formule est réparti entre les plats au prorata de leurs prix à la carte (le dernier absorbe l'arrondi : la somme est exacte), ce qui garde des marges par plat cohérentes. Un menu est disponible si chaque temps a au moins un plat disponible.

Test : `testMenuIsAllOrNothingAndSplitsItsPriceAcrossDishes`.

### Q4 — Le stock d'un ingrédient peut-il devenir négatif ? *(2026-11-14)*

**Non.** Trois barrières : (1) l'application refuse toute sortie supérieure au stock (`InsufficientStockException`, message clair pour le serveur) ; (2) les sorties se font sous verrou `SELECT … FOR UPDATE`, ingrédients verrouillés dans l'ordre des id, donc deux serveurs ne peuvent pas vendre « en même temps » le dernier kilo ; (3) une contrainte `CHECK (stock_quantity >= 0)` en base, en dernier rempart.

Un stock négatif ne décrit pas la réalité (on ne peut pas avoir −2 kg de farine) : il signale un stock *mal enregistré*, qu'il faut corriger par **inventaire**, pas masquer. Contrepartie assumée : si la cuisine a physiquement de la marchandise non saisie en stock, le logiciel bloquera la vente tant qu'une réception ou un inventaire n'a pas rétabli la réalité. Dans cet outil, c'est préférable à un stock négatif qui fausserait les coûts et le réapprovisionnement.

### Q5 — Que faire quand l'information allergène est absente ou incohérente ? *(2026-11-14)*

Principe : **on ne dit jamais « sans allergène » sans preuve.** Chaque ingrédient a un statut :

| Statut | Cas | Affichage sur la carte |
|---|---|---|
| `known` | Open Food Facts renseigne les allergènes (avec composition), ou déclaration manuelle du gérant | liste d'allergènes, ou « aucun allergène déclaré » |
| `incomplete` | fiche OFF marquée « ingrédients à compléter », ou liste d'allergènes vide *sans* liste d'ingrédients pour la corroborer | allergènes connus **+ avertissement** |
| `unknown` | pas de code-barres, produit introuvable, champ allergènes absent, source injoignable | **avertissement** |

Un plat est *complet* seulement si **tous** ses ingrédients bruts (préparations dépliées) sont `known`. Sinon la carte affiche « ⚠ Information allergènes incomplète — demandez au personnel » et liste les ingrédients à vérifier. Les allergènes partiellement connus sont conservés par prudence (union, jamais de retrait).

Autres règles : une **déclaration manuelle** du gérant (produits frais, fiche OFF défaillante) est fiable et **n'est jamais écrasée** par une resynchronisation ; une erreur réseau/HTTP/format n'efface pas les données précédentes et l'erreur est visible dans le back-office ; le client a un délai maximal de 5 s, une réponse inattendue ne casse pas la page ; les « traces » sont affichées à part, sans répéter un allergène déjà présent. Tests : `tests/Unit/OpenFoodFactsTest.php` (réponses simulées).

## Autres décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-14 | **Quand le stock sort-il ?** À l'envoi du plat en cuisine (création de la ligne), pas au moment de servir. | Le stock affiché est ainsi toujours le stock *réellement disponible* : impossible de vendre deux fois le dernier ingrédient pendant qu'un plat attend. Annuler avant lancement restitue ; après lancement, c'est une perte. « Plat servi » de l'énoncé est lu comme « plat vendu et sorti de la file ». |
| 2026-11-14 | Unité de base par dimension (g / ml / pièce), conversions via enum `Unit`, calculs en bcmath. | Pas d'erreur de float, pas de mélange d'unités, stock comparable. |
| 2026-11-14 | Préparations **dépliées à la vente** (pas de stock de préparation produit en lot). | Plus simple et correct pour ce périmètre ; le coût et le décompte restent exacts. Limite : on ne modélise pas « 5 l de fond déjà fait » — voir « avec une semaine de plus ». |
| 2026-11-14 | Cycles entre préparations interdits à la saisie (`wouldCreateCycle`) et détectés au dépliage. | Évite une boucle infinie. |
| 2026-11-14 | Prix de vente **TTC**, TVA 10 % par défaut ; marge calculée sur le prix **HT**. | La TVA n'est pas un revenu du restaurant ; calculer la marge TTC la gonflerait de 10 %. |
| 2026-11-14 | Devise : **MAD**. Données de démonstration : cuisine marocaine d'un restaurant d'Agadir. | Contexte local. |
| 2026-11-14 | Inventaire : l'écart est calculé à la validation **par rapport au stock courant** (relu sous verrou), pas à la photo du démarrage. | Des ventes peuvent avoir lieu pendant le comptage ; sinon elles seraient comptées deux fois. Un ingrédient non compté n'est pas modifié. |
| 2026-11-14 | Le stock ne se modifie **que** par achat reçu, vente, perte ou inventaire — pas d'édition libre du champ. | Traçabilité : chaque variation a un mouvement, un auteur, un motif. |
| 2026-11-14 | Une seule commande ouverte par table (verrou sur la table). | Évite deux additions parallèles pour la même table. |
| 2026-11-14 | Rôles : `ROLE_SERVER`, `ROLE_KITCHEN`, `ROLE_MANAGER` ; le gérant hérite des deux autres. Serveur : salle ; cuisine : file + pertes ; gérant : tout. Carte publique sans connexion. | Moindre privilège ; le client peut consulter les allergènes. |
| 2026-11-14 | CSRF sur tous les POST ; les règles métier violées deviennent des messages, pas des erreurs 500. | Robustesse. |
| 2026-11-14 | Entités à propriétés publiques typées (pas de getters/setters). | Moins de code répétitif ; les invariants vivent dans les services et en base, pas dans des setters. |
| 2026-11-14 | Pas de Flex / Symfony Runtime : kernel, `public/index.php` et `bin/console` classiques. | Projet lisible et autonome, sans dépendre de recettes. |
| 2026-11-14 | Données de démo : 14 jours de ventes passées insérées directement (sans mouvements de stock) + 2 commandes ouvertes passées par le vrai service. | Tableau de bord parlant dès le premier lancement ; l'historique est une « photo » (le stock de démo est posé à la main). |
| 2026-11-14 | Réapprovisionnement suggéré si stock ≤ seuil **ou** couverture < 2 jours (consommation estimée sur 14 jours d'après les plats servis) ; quantité = de quoi atteindre la cible ou 7 jours de consommation. | Simple, explicable, utile au gérant. |
| 2026-11-15 | **J'indique que j'ai utilisé une IA.** J'ai utilisé Claude pour comprendre le projet (énoncé, domaine, découpage) et l'IDE Antigravity pour le codage : le code a été écrit surtout par l'agent IA de l'IDE, que j'ai piloté. Je suis développeur Java/Spring Boot et je découvre Symfony : j'ai cadré le besoin, relu, lancé les tests et vérifié leurs résultats, demandé les corrections et fait organiser la documentation. | L'énoncé autorise l'IA. Je préfère le dire clairement. |
| 2026-11-15 | **Historique Git : j'ai réécrit `main` une fois.** J'ai fait un push forcé le 15/11 pour retirer des références dans la documentation. Je ne l'ai pas refait ensuite : les corrections suivantes sont de simples nouveaux commits. | L'énoncé demande un historique intact. Je préfère l'écrire moi-même plutôt que de le cacher. |
| 2026-11-15 | **`.env` reste dans le dépôt (convention Symfony), avec des valeurs de développement seulement.** Il contient un `APP_SECRET` factice, les identifiants MySQL par défaut (`resto`/`resto`) et l'URL publique d'Open Food Facts, comme `.env.example`. Aucun vrai secret n'a été versionné. Je l'avais retiré du suivi par erreur : sans `.env`, `Dotenv::bootEnv()` plante sur un clone neuf, donc j'ai annulé ça. | Pas besoin de réécrire l'historique pour un fichier sans secret. |
| 2026-11-15 | Je ne versionne **pas** `composer.lock` pour l'instant. Celui de mon environnement fixait Symfony 8.1 (PHP ≥ 8.4.1), alors que `composer.json` demande Symfony 7.4 et que le Dockerfile utilise PHP 8.3. | Un lock incohérent ferait échouer `composer install`. À régénérer avec PHP 8.3 puis à versionner. |

## Ce qui n'est pas fait / limites connues

- Pas de lots ni de DLC (le FIFO et la péremption automatique demandent un suivi par lot).
- Pas de saisonnalité automatique de la carte : un plat se retire/ajoute manuellement (`à la carte`).
- Pas de réception partielle d'un achat, ni de facturation fournisseur.
- Pas d'addition partagée / de paiement multiple.
- La concurrence est protégée par verrous SQL mais non testée avec des processus réellement parallèles (les tests tournent dans un seul processus).
- L'API Open Food Facts n'a pu être appelée en conditions réelles depuis l'environnement de développement (réseau restreint) : le client est testé sur des réponses simulées au format documenté.
