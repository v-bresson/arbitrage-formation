# CLAUDE.md — Notes pour reprendre ce dépôt

Repo `v-bresson/quizz-arbitre` — application PHP + JS de QCM/formation pour arbitres assistants en tir à l'arc. Ce fichier rassemble ce qu'il faut savoir avant de reprendre le développement ; `README.md` reste la présentation utilisateur/installation.

## Stack et conventions

- **Backend** : PHP 8+ procédural (pas de framework), PDO/MariaDB. Chaque `api/*.php` est un point d'entrée unique gardé par `require_user()` et/ou `require_permission($section, $level)`.
- **Frontend** : JS vanilla avec un mini-framework réactif maison, `assets/mvvm.js` (~90 lignes) : `qaReactive()` (proxy avec suivi de dépendances) + `qaWatchEffect()` (ré-exécution auto d'une fonction de rendu). Chaque page a un `vm` unique ; les champs de formulaire dans les modales restent lus nativement via `getElementById` à la soumission (pas de binding bidirectionnel, pour ne pas perdre le focus/curseur).
- **CSS** : `assets/style.css`, thème sombre unique, classes réutilisables (`.card`, `.grid`, `.panel`, `.field`, `.field-row`, `.pill`, `.modal-overlay`/`.modal`, `.switch-toggle`). Toujours ajouter les nouveaux types d'input à la règle commune (`input[type="..."], select, textarea { ... }`) sinon ils héritent du style navigateur par défaut — piège déjà rencontré avec `input[type="date"]`.
- **En-tête fixe** : `.site-header` en `position:fixed` + `.breadcrumb` fixe juste en dessous sur les pages hors admin. `assets/header-fix.js` mesure la hauteur réelle au chargement/redimensionnement pour dimensionner `.header-spacer` (la hauteur varie avec la largeur d'écran / longueur du nom d'utilisateur). Le fil d'ariane hérite `text-align` de son parent : `.breadcrumb-row` force `text-align:left` explicitement pour ne pas se retrouver centré si un jour il est imbriqué dans un conteneur `.page` (qui est centré par défaut) — piège déjà rencontré.

## Schéma de base et migrations (`includes/db.php`)

Deux régimes bien distincts, à ne jamais mélanger :

1. **Tables/colonnes purement additives** (nouvelle table qui n'existe nulle part encore, ex. `roles`, `role_permissions`, `user_permissions`, `user_roles`, `candidat_niveaux_valides`, `tiles`) : créées **sans condition** à chaque appel de `get_db()` (`CREATE TABLE IF NOT EXISTS`), avec éventuellement un backfill idempotent (`INSERT ... WHERE NOT EXISTS`). Sans danger sur une base existante.
2. **Altération d'une table existante** (nouvelle colonne sur `users`, `tiles`, etc.) : passe *obligatoirement* par le registre `qa_schema_migrations()` — jamais d'`ALTER TABLE` direct hors de ce mécanisme. Une migration n'est appliquée qu'à la demande explicite de l'admin (Administration > Mise à jour système), sauf sur une installation neuve où le schéma cible est déjà dans le `CREATE TABLE` (la migration est alors auto-marquée appliquée par `qa_sync_schema_migrations()`).

**Piège classique** : si du code (API) référence une colonne qui vient d'être ajoutée au `CREATE TABLE` mais dont la migration n'a pas encore été lancée sur une base existante, une requête SQL brute peut planter (`PDOException` non catchée) et casser le JSON de réponse — le front-end interprète alors ça comme une erreur réseau générique ("Erreur de connexion au serveur"), ce qui n'aide pas au diagnostic. Toujours :
- catcher les `PDOException` sur les endpoints qui touchent une colonne récente et renvoyer un message explicite du type *"la base de données n'est pas à jour, lancez la mise à jour depuis Administration > Mise à jour système"* (voir `api/users.php`, `api/tiles.php` pour l'exemple) ;
- pour les lectures (`list`/`list_admin`), prévoir un repli sans la colonne (`qa_column_exists()`) plutôt qu'une requête qui plante, pour ne pas casser tout l'affichage en attendant que la migration soit lancée.

## Rôles et permissions (`includes/permissions.php`)

- Un compte peut cumuler **plusieurs rôles** (table `user_roles`, many-to-many). Les rôles eux-mêmes (4 historiques + rôles personnalisés) et leurs groupes de droits par défaut vivent dans `roles`/`role_permissions` (éditables depuis l'onglet Rôles, sauf `super_admin` qui a toujours accès total et n'est jamais stocké).
- `qa_user_role_keys($pdo, $userId, $role)` renvoie tous les rôles assignés ; `qa_effective_permissions()` fusionne, section par section, le **niveau le plus élevé** parmi tous ces rôles, puis applique les éventuelles surcharges individuelles (`user_permissions` — capacité conservée en base mais retirée de l'UI d'édition utilisateur, à réintroduire plus tard si besoin).
- `users.role` (colonne unique) est conservé comme "rôle principal" = le rôle le plus haut rang parmi ceux cochés (ordre : candidat < formateur < membre_cra < super_admin, rang par défaut 1 pour un rôle personnalisé). Sert uniquement à l'affichage/compat ; ne jamais l'utiliser pour une décision de permission, toujours passer par `qa_effective_permissions()`/`qa_has_permission()`.
- Invariant important à préserver si on retouche `api/users.php` : la promotion automatique de `super_admin` en rôle principal dès qu'il est coché est ce qui permet à `qa_super_admin_count()` (garde-fou "au moins un Super-Admin actif") de rester fiable même si on lit `users.role` quelque part par erreur.
- `qa_has_formateur_role()` (booléen simple "ce compte a-t-il le rôle Formateur") sert spécifiquement à `candidate.php` pour la vue exclusive — ne pas confondre avec `qa_has_formateur_access()` (basé sur les permissions effectives, pour la tuile "Espace formateur" du dashboard).

## Suivi de formation des candidats

- Un candidat évolue dans le temps sur plusieurs niveaux (Assistant Arbitre, Arbitre Fédéral, Arbitre Duel) : chaque niveau validé est enregistré séparément avec sa date (table `candidat_niveaux_valides`, une ligne par niveau coché ; `date_validation` NULL = niveau "en cours", pas encore formellement daté). `qa_user_niveaux_valides($pdo, $userId)` (dans `includes/db.php`) renvoie l'historique complet, utilisé à la fois par la fiche admin (`api/users.php`) et la fiche formateur en lecture seule (`api/mes_candidats.php`).
- L'ancienne colonne unique `users.niveau_formation` n'est plus lue ni écrite (remplacée par la table ci-dessus au moment du passage au multi-niveaux) — elle reste en base sans usage actif, ne pas s'y fier si elle traîne encore sur une vieille installation.
- Édition réservée à Administration > Comptes utilisateurs (fiche complète, case à cocher + date par niveau) : un formateur ne peut que consulter (lecture seule) le suivi de formation de ses candidats assignés, jamais le modifier.

## Espace candidat vs Espace formateur

- `candidate.php` : si le compte a le rôle **Formateur**, la page bascule entièrement sur une recherche de ses candidats assignés (`formateur_referent_id`) avec fiche en lecture seule + stats (`api/mes_candidats.php`) — **choix exclusif**, même si le compte est aussi Candidat (décision produit validée, ne pas re-proposer les deux en même temps sans redemander).
- `formateur.php` (Espace formateur, distinct) donne accès aux sections admin (questions/questionnaires/résultats) selon permissions — n'a rien à voir avec la recherche de candidats assignés.
- Les tuiles du Dashboard (`tiles` table, colonne `scope` = `accueil` vs `candidat`) sont un système séparé des "espaces" fixes (Candidat/Formateur/Administration/Mon compte) : `scope=accueil` = raccourcis configurables sur `dashboard.php`, `scope=candidat` = tuiles historiques de `candidate.php` (ne jamais fusionner les deux sans y penser, un oubli du filtre `scope` par défaut a déjà cassé l'affichage des tuiles candidat).

## Workflow de test recommandé avant de pousser

Ce dépôt n'a pas de suite de tests automatisés : la vérification se fait manuellement à chaque changement fonctionnel, avec ce cycle (déjà utilisé tout au long du développement) :

```bash
service mariadb start
mysql -u root -e "CREATE DATABASE IF NOT EXISTS quizz_test; CREATE USER IF NOT EXISTS 'qa'@'localhost' IDENTIFIED BY 'qa'; GRANT ALL ON quizz_test.* TO 'qa'@'localhost'; FLUSH PRIVILEGES;"
cat > includes/db-config.php << 'EOF'
<?php
return ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'quizz_test', 'username' => 'qa', 'password' => 'qa'];
EOF
php -r "require 'includes/db.php'; get_db(); echo 'OK';"
nohup php -S 127.0.0.1:8099 > /tmp/php-server.log 2>&1 & disown
```

Puis Playwright (Chromium pré-installé à `/opt/pw-browsers/chromium`, `npm install playwright` dans un dossier scratch si le module manque) pour vérifier visuellement, et/ou `curl` direct sur les endpoints `api/*.php` pour vérifier les réponses JSON.

**Toujours nettoyer avant de committer** : supprimer `includes/db-config.php` (jamais versionné), `DROP DATABASE`/`DROP USER` de test, arrêter `php -S` et `service mariadb stop`. Vérifier chaque étape de nettoyage individuellement (`ps aux`, `ls`) plutôt que de faire confiance à une chaîne de commandes — dans cet environnement, un `pkill` suivi d'autres commandes dans le même appel Bash tronque parfois silencieusement la suite (exit code 144) sans exécuter ce qui suit.

## Git

Branche de travail : `claude/qcm-arbitre-app-2zqsgc`, PR #1. Toujours committer avec un message qui explique le *pourquoi*, jamais de push sans lint (`php -l`, `node --check`) et, pour tout changement fonctionnel, une vérification locale réelle (pas seulement une lecture de code).
