# ArcheryOps Judging

Application web PHP + JS pour créer des QCM et faire passer des questionnaires de validation de la formation d'arbitre assistant. Pensée comme un module destiné à s'intégrer, à terme, dans une architecture ArcheryOps plus large (Results, Judging, inscriptions...) partageant un dashboard et une base d'utilisateurs communs.

## Architecture frontend (MVVM maison)

Le JS de chaque page (`index.php`, `dashboard.php`, `quiz.php`, `admin/app.js`) suit un vrai pattern **MVVM**, sans dépendance externe (choix cohérent avec les autres projets de l'écosystème) : un mini-noyau réactif fait main (`assets/mvvm.js`, ~90 lignes) fournit `qaReactive()` (proxy avec suivi de dépendances, comme la réactivité de Vue) et `qaWatchEffect()` (ré-exécution automatique d'une fonction de rendu dès qu'une propriété réactive qu'elle lit est modifiée).

Chaque page définit un état réactif unique (le **ViewModel**) contenant les collections chargées depuis l'API et les messages d'état ; les fonctions `loadXxx()` se contentent d'assigner ce state (le **Model**, côté fetch), et la **vue** (fonctions de rendu passées à `qaWatchEffect`) se redessine seule — plus aucun appel manuel à un rendu après une mutation d'état. Volontairement laissés en dehors de cette réactivité, pour ne pas perdre le focus/curseur en cours de frappe : les champs des formulaires dans les modales (lus nativement via `getElementById` à la soumission), la liste de questions en cours de passage, et le minuteur (mis à jour chaque seconde de façon imperative).

## Fonctionnalités

- **Connexion** (`index.php`) : point d'entrée unique de l'application. Première visite = création du compte administrateur ; ensuite, écran de connexion classique (identifiant/mot de passe).
- **Dashboard** (`dashboard.php`) : après connexion, grille de tuiles configurables depuis l'admin (voir plus bas) ; une tuile **Administration** y est ajoutée automatiquement pour les comptes de rôle admin (elle ne se configure pas dans l'onglet Tuiles).
- **Module Candidats arbitres** (`quiz.php`, atteint via la tuile "Candidats arbitres") : liste des questionnaires actifs (entraînement ou examen), passage du questionnaire (questions piochées aléatoirement) en tant qu'utilisateur connecté, minuteur visible si chronométré, note finale (ou message générique si le score est masqué) et statut réussi/non validé.
- **Espace admin** (`admin/index.php`), réservé au rôle admin, organisé en menu latéral avec sous-sections dépliables :
  - **Dashboard** : vue d'ensemble (nombre de questions, questionnaires, tentatives, comptes, version installée).
  - **Candidats arbitres**
    - *Banque de questions* : QCM à **réponse unique**, QCM à **réponses multiples**, ou **question ouverte** (réponse libre, non notée automatiquement — à relire manuellement dans Résultats). Chaque question peut avoir une **image jointe**, une catégorie, un nombre de points, et être **réservée à l'examen**. Import en masse via **CSV** ou **XLSX** (colonnes : `categorie, type, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points, examen_uniquement` — voir le modèle `data/modele_import_questions.csv`).
    - *Questionnaires* de deux types :
      - **Entraînement** : pioche uniquement parmi les questions non réservées à l'examen (aucune question d'examen ne peut y apparaître).
      - **Examen** : peut piocher dans toute la banque de questions, avec réglages spécifiques : **fenêtre d'ouverture** (date/heure de début et de fin), **durée chronométrée** (minuteur, auto-soumission à expiration), **nombre de tentatives maximum** par candidat, et **affichage ou masquage du score** au candidat à la fin.
      - Chaque questionnaire peut piocher soit un nombre global de questions dans une catégorie unique (ou toutes), soit une **répartition par thématique** (ex. 3 questions "Sécurité" + 2 "Scoring" + 5 "Règlement").
    - *Résultats* : archive de **toutes** les tentatives (entraînement et examen), avec statut (en cours / terminée / expirée), conservée même si le questionnaire ou les questions sont ensuite modifiés ou supprimés.
  - **Comptes utilisateurs** : création/modification des comptes (identifiant, mot de passe, rôle admin/utilisateur, actif/inactif), avec fiche profil optionnelle (prénom, nom, email, téléphone, n° de licence, club). Il doit toujours rester au moins un administrateur actif (garde-fou sur la suppression/rétrogradation).
  - **Administration**
    - *Tuiles* : gestion du contenu du dashboard (nom, description, icône, ordre d'affichage, réservée ou non aux admins). Une tuile de type "Candidats arbitres" pointe vers le module intégré ; une tuile de type "Lien" pointe vers une URL (utile pour brancher plus tard d'autres modules ArcheryOps).
    - *Mise à jour système* : mise à jour de l'application par upload d'un fichier `.zip`, ou directement depuis la dernière release d'un dépôt GitHub configuré. Une sauvegarde complète du code est créée automatiquement avant toute mise à jour ou restauration ; historique consultable et restaurable, avec un journal détaillé de chaque opération (voir plus bas).

Le nom d'utilisateur connecté sert directement d'identifiant candidat pour les questionnaires (plus besoin de ressaisir son nom) : il identifie aussi la reprise de tentative et le comptage des tentatives sur les examens.

> Architecture multi-modules : ce module vit pour l'instant avec sa propre base d'utilisateurs (table `users`) et sa propre session. Le passage à un référentiel d'utilisateurs et une méthode d'authentification centralisés (ArcheryOps Dashboard) est identifié comme un chantier à part entière, à traiter plus tard sans que cela ne bloque le fonctionnement autonome actuel.

### Mise à jour, sauvegardes et journal (Administration > Mise à jour système)

- **Upload manuel** : déposer un fichier `.zip` de la nouvelle version. Avant toute application, une sauvegarde complète du code actuel est créée dans `backups/` (bloquante : si la sauvegarde échoue ou est incomplète, la mise à jour est annulée). L'archive fournie doit contenir `index.php` et `admin/index.php` (à la racine, ou dans un unique dossier englobant — cas des exports GitHub) : c'est la vérification qui atteste qu'il s'agit bien d'une version de cette application avant toute copie de fichiers.
- **Mise à jour depuis GitHub** : optionnelle, à activer en ajoutant une clé `github` (`token`, `owner`, `repo`) dans `includes/db-config.php` (voir `includes/db-config.sample.php`). L'onglet Mise à jour système propose alors de vérifier la dernière release publiée et de l'appliquer en un clic (même mécanisme de sauvegarde automatique).
- **Chemins jamais écrasés**, quel que soit le contenu de l'archive : `includes/db-config.php`, `data/`, `uploads/questions/` (images des questions) et `backups/` lui-même. La copie est **additive** : un fichier absent de la nouvelle archive mais présent sur le disque n'est jamais supprimé.
- **Sauvegardes** : les 10 plus récentes sont conservées (rotation automatique), consultables et restaurables individuellement depuis l'onglet ; restaurer applique le même mécanisme de copie sélective qu'une mise à jour.
- **Journal de maintenance** (`backups/maintenance.log`) : historique de chaque sauvegarde, mise à jour, restauration ou échec, avec date, auteur et détail ; purgé automatiquement au-delà de 90 jours, ou vidable manuellement.

### Fonctionnement du minuteur et des tentatives d'examen

- Au démarrage d'un questionnaire chronométré, l'heure de début et le tirage des questions sont figés côté serveur dans la tentative (table `tentatives`). Un utilisateur qui recharge la page **reprend** sa tentative en cours (mêmes questions, temps restant recalculé) plutôt que d'en démarrer une nouvelle.
- Si le temps imparti est écoulé, la tentative est automatiquement soumise avec les réponses déjà données et marquée **expirée**.
- Le nombre de tentatives maximum (examen) compte les tentatives **terminées ou expirées** ; une tentative en cours ne consomme pas de crédit tant qu'elle n'est pas finalisée.
- Les questions ouvertes ne comptent jamais dans le total de points noté automatiquement : le score porte uniquement sur les QCM.

## Installation

Prérequis : PHP 8+ avec les extensions `pdo_mysql` et `zip` (pour l'import `.xlsx`), et un serveur **MariaDB** (ou MySQL) accessible.

1. Déposer les fichiers sur un serveur PHP (Apache/Nginx + PHP-FPM, ou `php -S localhost:8000`). Le dossier `includes/` doit être accessible en écriture par PHP (pour que l'assistant d'installation puisse y créer `db-config.php`), et `uploads/questions/` accessible en écriture et servi publiquement (images jointes aux questions).
2. Ouvrir **`install.php`** : l'assistant d'installation demande l'hôte, le port, le nom de la base, l'utilisateur et le mot de passe MariaDB/MySQL, propose de créer la base automatiquement (si le compte a le droit `CREATE`), teste la connexion, écrit `includes/db-config.php` puis crée les tables (`users`, `tiles`, `questions`, `quizzes`, `tentatives`) — aucun script SQL à lancer à la main. Une tuile "Candidats arbitres" par défaut est créée automatiquement.
   Si l'application est déjà installée, `install.php` redirige directement vers `index.php` (pas de réinstallation accidentelle) ; `install.php?force=1` permet de repasser par l'assistant pour dépanner une configuration existante.
   Alternative sans interface (conteneur/CI) : définir les variables d'environnement `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, ou copier `includes/db-config.sample.php` en `includes/db-config.php` à la main.
3. Ouvrir `index.php` : le premier écran propose de créer le compte administrateur (si `install.php` n'a pas encore été passé et que la base n'est pas configurée, `index.php` y redirige automatiquement).
4. Une fois connecté en admin, ajouter des questions (manuellement ou via import), créer un premier questionnaire, et éventuellement d'autres tuiles/utilisateurs depuis l'espace admin.

> Les versions précédentes de l'application utilisaient une base SQLite locale (`data/quizz.sqlite`) et un compte admin unique stocké dans `data/credentials.json`. Ces deux mécanismes sont abandonnés au profit d'une vraie base MariaDB avec une table `users` multi-comptes ; les anciennes données n'ont pas été migrées automatiquement.

## Structure

```
install.php                    Assistant d'installation (identifiants DB, création de la base, écriture de db-config.php)
index.php                     Page de connexion (+ création du premier compte admin)
dashboard.php                  Dashboard : grille de tuiles + accès admin (selon rôle)
quiz.php                       Module de passage de questionnaires (candidat connecté)
admin/index.php                Espace admin (SPA, réservé au rôle admin)
admin/app.js                   Logique JS de l'admin
api/auth.php                    Authentification unifiée (connexion, session, rôle)
api/users.php                   CRUD utilisateurs (admin)
api/tiles.php                   Liste des tuiles (utilisateur connecté) + CRUD (admin)
api/questions.php               CRUD + import questions (admin)
api/quizzes.php                  CRUD questionnaires + archive des tentatives (admin)
api/attempt.php                  Passage de questionnaire (utilisateur connecté) : liste, démarrage/reprise, soumission
api/maintenance.php               Mise à jour (zip/GitHub), sauvegardes, journal (admin)
includes/db.php                   Connexion MariaDB/MySQL (PDO) + création du schéma
includes/db-config.sample.php      Modèle de fichier de config DB (à copier en db-config.php), avec clé GitHub optionnelle
includes/maintenance.php           Sauvegarde/restauration de code, extraction de zip, intégration GitHub, journal
includes/require_user.php          Garde d'accès : utilisateur connecté
includes/require_admin.php         Garde d'accès : rôle admin
includes/xlsx_reader.php           Lecteur .xlsx minimaliste sans dépendance
includes/uploads.php               Gestion des images jointes aux questions
assets/style.css                   Charte graphique (reprise de serveur-home)
assets/mvvm.js                      Mini-noyau MVVM maison (état réactif + rendu automatique)
uploads/questions/                 Images jointes aux questions (non versionnées)
VERSION.txt                        Version du code déployé, affichée dans l'onglet Mise à jour système
backups/                           Sauvegardes de code + journal de maintenance (non versionnés)
```
