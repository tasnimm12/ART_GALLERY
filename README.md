# Abstract Art Gallery

Plateforme web de galerie d'art construite avec Symfony 6.4. Elle permet de découvrir des œuvres abstraites, gérer les favoris, passer des commandes, participer à des enchères en temps réel et bénéficier d'une assistance artistique alimentée par Gemini.

## Fonctionnalités

### Catalogue et découverte

- Parcours du catalogue d'œuvres avec filtres par recherche, artiste, catégorie, prix et couleurs.
- Pagination et mise à jour des résultats par requêtes AJAX.
- Page détaillée pour chaque œuvre avec images, description, prix, disponibilité et analyses de couleurs.
- Sélection de l'œuvre du jour.
- Consultation des artistes et de leurs créations.
- Favoris accessibles depuis le compte utilisateur.

### Comptes et sécurité

- Inscription et connexion par formulaire.
- Connexion OAuth via Google.
- Gestion des comptes avec les rôles `ROLE_USER`, `ROLE_CLIENT` et `ROLE_ADMIN`.
- Protection des pages compte, des enchères réservées aux clients et de l'administration.
- Option « se souvenir de moi ».

### Panier et commandes

- Ajout, suppression et vidage du panier.
- Récapitulatif de commande et choix du mode de paiement.
- Paiement par carte avec Stripe Checkout.
- Confirmation, historique des commandes et génération de reçus par e-mail.
- Notifications liées aux changements de statut des commandes.

### Enchères

- Enchères planifiées, actives et terminées.
- Dépôt d'offres avec vérification du montant minimum et des règles de participation.
- Mise à jour immédiate du prix, des offres et du gagnant.
- Actualisation temps réel via Mercure/SSE, avec repli automatique sur le polling.
- Anti-sureenchère lorsqu'une offre arrive près de la fin de l'enchère.
- Clôture automatique, désignation du gagnant et création de la commande associée.
- Notifications aux enchérisseurs surenchéris ou ayant remporté une vente.

### Intelligence artificielle

- Conseiller artistique proposant jusqu'à six œuvres correspondant à une demande libre.
- Analyse des préférences exprimées dans le texte : style, ambiance, couleurs, budget et pièces.
- Assistant conversationnel basé sur Gemini et sur les données réelles du catalogue.
- Recommandations contextuelles par rapport à une œuvre consultée.
- Analyse des couleurs des images par calcul des principaux clusters RGB.
- Exploration et filtrage du catalogue par couleurs dominantes.

### Administration

- Tableau de bord EasyAdmin.
- Gestion des œuvres, images, artistes, catégories et couleurs.
- Gestion des enchères, commandes, paiements, utilisateurs et administrateurs.
- Consultation des notifications et journaux d'audit.
- Analyse automatique ou manuelle des couleurs d'une œuvre.

### Technique

- Symfony 6.4, Doctrine ORM et Twig.
- Base MySQL/MariaDB par défaut ou PostgreSQL via Docker.
- Symfony Messenger pour les tâches asynchrones.
- Mercure pour les événements temps réel.
- Symfony AssetMapper, Stimulus, Turbo et Leaflet.
- Tests fonctionnels avec PHPUnit.

## Prérequis

- PHP 8.1 ou supérieur
- Extensions PHP `ctype`, `gd` et `iconv`
- Composer
- Symfony CLI, recommandé pour le serveur de développement
- MySQL/MariaDB ou PostgreSQL
- Node.js, uniquement si des commandes npm supplémentaires sont utilisées

Sous Windows, vérifiez les commandes disponibles :

```powershell
php -v
composer --version
symfony check:req
```

## Installation

1. Installer les dépendances :

```powershell
cd C:\Users\amen\aag-platform
composer install
```

2. Créer un fichier local d'environnement :

```powershell
Copy-Item .env .env.local
```

3. Configurer la connexion à la base de données dans `.env.local`.

Exemple MariaDB/MySQL correspondant à la configuration actuelle :

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/tas?serverVersion=mariadb-10.4.32&charset=utf8mb4"
```

4. Créer la base et appliquer les migrations :

```powershell
php bin\console doctrine:database:create
php bin\console doctrine:migrations:migrate
```

5. Installer les éventuelles données de démonstration :

```powershell
php bin\console doctrine:fixtures:load
```

Cette dernière commande remplace les données existantes dans le schéma configuré. Ne l'exécutez pas sur une base de production.

6. Vider le cache et démarrer l'application :

```powershell
php bin\console cache:clear
symfony server:start
```

Ouvrir ensuite l'adresse affichée par Symfony CLI, généralement `http://127.0.0.1:8000`.

## Configuration des services externes

Ajoutez les valeurs suivantes dans `.env.local`, sans les ajouter à Git :

```dotenv
# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Google Gemini
GEMINI_API_KEY=your_gemini_api_key

# E-mail
MAILER_DSN="smtp://USER:PASSWORD@smtp.example.com:587"
MAILER_FROM=noreply@example.com

# Mercure
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET="change-this-secret"
MESSENGER_TRANSPORT_DSN=doctrine://default
```

Les principales intégrations sont optionnelles selon les fonctionnalités testées :

- Sans clé Gemini, les fonctionnalités IA retournent un message de configuration indisponible.
- Sans clé Stripe, le paiement ne peut pas démarrer.
- Sans `MAILER_DSN`, les e-mails peuvent être désactivés ou interceptés selon l'environnement.
- L'analyse des couleurs nécessite l'extension PHP GD.
- Mercure doit être démarré séparément pour bénéficier des mises à jour SSE.

Configurez les URI de retour Google sur `/connect/google/check` dans la console Google. Configurez aussi le webhook Stripe pour pointer vers `/payment/webhook`.

## Docker

Le fichier `compose.yaml` fournit PostgreSQL et Mercure. Les ports et les mots de passe doivent être adaptés à l'environnement local :

```powershell
docker compose up -d
```

Le projet utilise MariaDB/MySQL par défaut dans `.env`. Pour utiliser PostgreSQL avec Docker, remplacez `DATABASE_URL` dans `.env.local` par l'URL correspondant au service créé.

## Commandes utiles

```powershell
# Afficher les routes
php bin\console debug:router

# Vider le cache
php bin\console cache:clear

# Analyser les couleurs de toutes les œuvres
php bin\console app:analyze-artwork-colors

# Simuler la clôture des enchères expirées
php bin\console app:close-expired-auctions --dry-run

# Clôturer réellement les enchères expirées
php bin\console app:close-expired-auctions

# Vérifier et rattraper les notifications de surenchère
php bin\console app:notify-outbid-users

# Tester la configuration e-mail
php bin\console app:test-mailer

# Consommer les messages Messenger
php bin\console messenger:consume async
```

Pour envoyer des reçus ou tester l'e-mail, consultez les options disponibles :

```powershell
php bin\console app:send-order-receipt --help
php bin\console app:test-mailer --help
```

## Tests

Exécuter toute la suite PHPUnit :

```powershell
php bin\phpunit
```

Exécuter un sous-ensemble :

```powershell
php bin\phpunit tests\Catalog
php bin\phpunit tests\Auction
php bin\phpunit tests\AI
```

## Structure principale

```text
assets/          JavaScript et feuilles de style
config/          Configuration Symfony, sécurité et services
migrations/      Migrations Doctrine
public/          Point d'entrée web et fichiers téléversés
src/
  Command/       Commandes Symfony CLI
  Controller/    Contrôleurs web et API
  Entity/        Entités Doctrine
  Form/          Formulaires
  Repository/    Requêtes Doctrine
  Service/       Panier, commandes, enchères, IA et paiements
templates/       Vues Twig
tests/           Tests PHPUnit
```

## Sécurité

- Ne commitez jamais les fichiers `.env.local`, les clés API, les mots de passe ou les secrets Stripe.
- Utilisez des clés Stripe de test pendant le développement.
- Remplacez les secrets Mercure et `APP_SECRET` en production.
- Limitez les données de démonstration et les comptes administrateurs lors des déploiements.
