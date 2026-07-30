# ArcheryOps - Arbitrage

Application web PHP + JS dédiée à la gestion de la formation des arbitres de la FFTA (Fédération Française de Tir à l'Arc) : suivi des candidats, questionnaires de validation et administration des comptes/rôles.

## Fonctionnalités principales

- **Connexion unifiée** (`index.php`) : première visite = création du compte administrateur, ensuite écran de connexion classique.
- **Dashboard** (`dashboard.php`) : espaces disponibles selon le(s) rôle(s) du compte (Espace candidat, Espace formateur, Administration, Mon compte) + tuiles de raccourcis personnalisables (Mode config).
- **Espace candidat** (`candidate.php`) : profil, statistiques, questionnaires à passer. Si le compte porte le rôle Formateur, cette page affiche à la place une recherche de ses candidats assignés (fiche en lecture seule + statistiques).
- **Module questionnaires** (`quiz.php`) : QCM à réponse unique/multiple ou question ouverte, questionnaires d'entraînement ou d'examen (fenêtre d'ouverture, minuteur, tentatives max, score affiché ou masqué), import en masse CSV/XLSX.
- **Administration** (`admin/index.php`) : banque de questions, questionnaires, résultats, comptes utilisateurs, candidats/formateurs, rôles et permissions, mise à jour système (zip ou GitHub, sauvegardes, migrations de base).
- **Rôles cumulables** : un compte peut porter plusieurs rôles à la fois (ex. Candidat + Formateur). Les rôles et leurs permissions par section sont gérables depuis l'onglet Rôles (rôles personnalisés possibles, Super-Admin toujours à accès total).

Voir `CLAUDE.md` pour le détail de l'architecture, des conventions de code et des points d'attention avant de reprendre le développement.

## Installation

Prérequis : PHP 8+ (extensions `pdo_mysql`, `zip`) et un serveur MariaDB/MySQL accessible.

1. Déposer les fichiers sur un serveur PHP. `includes/` doit être accessible en écriture (pour `db-config.php`), ainsi que `uploads/questions/` (images jointes, servi publiquement).
2. Ouvrir **`install.php`** : renseigner les identifiants de connexion à la base (création automatique possible si le compte a le droit `CREATE`) — les tables sont créées automatiquement, aucun script SQL à lancer à la main.
   - Alternative sans interface : variables d'environnement `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, ou copier `includes/db-config.sample.php` en `includes/db-config.php`.
3. Ouvrir `index.php` : créer le compte administrateur.
4. Une fois connecté, ajouter des questions, créer un questionnaire, gérer les comptes/rôles depuis l'espace Administration.

Après une mise à jour de code, si la base de données doit évoluer (nouvelle colonne/table), un bandeau apparaît dans **Administration > Mise à jour système** : la mise à jour de schéma n'est jamais appliquée automatiquement, elle se lance volontairement d'un clic.
