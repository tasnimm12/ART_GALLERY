Abstract Art Gallery — Architecture et Conception du MVP
📌 Présentation du projet
Abstract Art Gallery est une plateforme web dédiée à la présentation et à la commercialisation d'œuvres d'art.

L'objectif du projet est de concevoir une plateforme haut de gamme, moderne, minimaliste, performante, sécurisée et évolutive, capable de fonctionner à l'international.

La plateforme intégrera deux modes de transaction complémentaires :

🛒 Vente directe

🔨 Vente aux enchères

Les deux systèmes doivent fonctionner de manière indépendante tout en partageant un même socle technique :

utilisateurs ;

catalogue ;

commandes ;

paiements ;

notifications ;

sécurité ;

administration ;

historique et traçabilité.

L'objectif du MVP n'est pas uniquement de créer un site vitrine, mais de construire une véritable base technique capable d'évoluer dans le temps.

1. Objectifs du projet
La plateforme doit permettre de :

présenter les œuvres de manière professionnelle ;

présenter les artistes ;

vendre des œuvres directement ;

organiser des ventes aux enchères ;

permettre aux utilisateurs de participer aux enchères ;

gérer les commandes ;

gérer les paiements ;

envoyer des notifications ;

administrer la plateforme depuis un Back Office ;

garantir la sécurité des données et des transactions ;

assurer la traçabilité des opérations importantes ;

optimiser les performances ;

mettre en place une stratégie SEO solide ;

préparer l'évolution internationale de la plateforme.

2. Analyse fonctionnelle
2.1 Utilisateurs de la plateforme
La plateforme distingue principalement plusieurs types d'utilisateurs.

Visiteur
Le visiteur peut :

consulter la page d'accueil ;

consulter les œuvres ;

rechercher des œuvres ;

consulter les artistes ;

consulter les enchères ;

consulter les informations de la galerie ;

consulter les conditions de vente et d'enchères.

Il doit créer un compte pour effectuer des opérations sensibles.

Client
Le client peut :

créer un compte ;

se connecter ;

modifier son profil ;

acheter une œuvre ;

ajouter une œuvre au panier ;

effectuer un paiement ;

participer aux enchères ;

consulter ses offres ;

consulter ses commandes ;

recevoir des notifications.

Administrateur
L'administrateur peut :

gérer les utilisateurs ;

gérer les artistes ;

gérer les œuvres ;

gérer les ventes directes ;

créer et administrer les enchères ;

consulter les offres ;

gérer les commandes ;

consulter les paiements ;

gérer les notifications ;

consulter les logs d'audit ;

gérer les rôles et permissions.

3. Services principaux
La plateforme repose sur plusieurs services fonctionnels.

3.1 Catalogue
Le catalogue permet de :

présenter les œuvres ;

afficher les images ;

afficher les informations artistiques ;

rechercher des œuvres ;

filtrer les œuvres ;

trier les résultats ;

consulter les détails d'une œuvre.

Les informations principales d'une œuvre sont notamment :

titre ;

artiste ;

description ;

technique ;

dimensions ;

année ;

images ;

prix ;

disponibilité.

3.2 Vente directe
Le système de vente directe permet au client de :

consulter une œuvre ;

consulter son prix ;

ajouter l'œuvre au panier ;

accéder au checkout ;

s'authentifier ;

confirmer les informations ;

effectuer le paiement ;

recevoir la confirmation.

Workflow :

Consultation de l'œuvre
        ↓
Ajout au panier
        ↓
Panier
        ↓
Authentification
        ↓
Checkout
        ↓
Paiement
        ↓
Confirmation
        ↓
Commande confirmée
4. Système d'enchères
Le système d'enchères constitue une fonctionnalité centrale du projet.

Chaque enchère doit pouvoir contenir :

un lot ;

une œuvre associée ;

une date de début ;

une date de clôture ;

un prix de départ ;

un prix de réserve ;

un incrément minimum ;

les offres ;

l'identification des enchérisseurs ;

l'historique des offres ;

le gagnant ;

le statut de l'enchère.

4.1 Workflow d'une enchère
Création du lot
      ↓
Configuration de l'enchère
      ↓
Publication
      ↓
Ouverture
      ↓
Réception des offres
      ↓
Vérification des offres
      ↓
Mise à jour du prix actuel
      ↓
Clôture automatique
      ↓
Vérification du prix de réserve
      ↓
Détermination du gagnant
      ↓
Création de la commande
      ↓
Paiement
      ↓
Confirmation
5. Architecture technique du MVP
L'architecture est conçue autour d'une séparation claire entre :

Front Office ;

Back Office ;

API / couche applicative ;

logique métier ;

base de données ;

services externes.

Architecture générale :

                         UTILISATEURS
                              │
                              ▼
                    ┌───────────────────┐
                    │    FRONT OFFICE   │
                    │                   │
                    │ Catalogue         │
                    │ Vente directe     │
                    │ Enchères          │
                    │ Compte client     │
                    └─────────┬─────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │       API         │
                    │  Couche applicative│
                    └─────────┬─────────┘
                              │
                 ┌────────────┼────────────┐
                 │            │            │
                 ▼            ▼            ▼
          ┌────────────┐ ┌──────────┐ ┌────────────┐
          │ Catalogue  │ │ Enchères │ │ Commandes  │
          └────────────┘ └──────────┘ └────────────┘
                 │            │            │
                 └────────────┼────────────┘
                              ▼
                    ┌───────────────────┐
                    │      MySQL        │
                    │    Database       │
                    └───────────────────┘

          ┌─────────────────────────────────────┐
          │           BACK OFFICE               │
          │                                     │
          │ Utilisateurs / Catalogue / Enchères │
          │ Commandes / Paiements / Audit       │
          └─────────────────────────────────────┘

                    Services externes
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
           Paiement     Email       Cloudflare
6. Séparation Front Office / Back Office
Front Office
Le Front Office est destiné aux visiteurs et aux clients.

Il contient :

présentation de la galerie ;

catalogue ;

œuvres ;

artistes ;

ventes directes ;

enchères ;

panier ;

checkout ;

compte client ;

notifications ;

pages institutionnelles.

Back Office
Le Back Office est réservé aux utilisateurs administrateurs.

Il permet de gérer :

utilisateurs ;

artistes ;

catalogue ;

œuvres ;

ventes ;

enchères ;

offres ;

commandes ;

paiements ;

notifications ;

audit ;

rôles et permissions.

7. Modèle de données
Le modèle de données est construit autour des principales entités du système.

USER
 │
 ├───────────────┐
 │               │
 ▼               ▼
ORDER           BID
 │               │
 ▼               ▼
PAYMENT        AUCTION
 │               │
 └───────┐       │
         │       ▼
         │     LOT
         │       │
         │       ▼
         └─── PRODUCT / ARTWORK
                    │
                    ▼
                 ARTIST
Entités principales
USER
Représente les utilisateurs de la plateforme.

Principales informations :

id ;

nom ;

email ;

mot de passe hashé ;

rôle ;

statut ;

dates de création et modification.

ARTIST
Représente les artistes.

Informations principales :

id ;

nom ;

biographie ;

image ;

informations artistiques ;

statut.

ARTWORK / PRODUCT
Représente une œuvre.

Informations :

id ;

titre ;

description ;

artiste ;

images ;

prix ;

dimensions ;

technique ;

année ;

disponibilité.

AUCTION
Représente une enchère.

Informations :

id ;

lot ;

prix de départ ;

prix actuel ;

prix de réserve ;

incrément ;

date de début ;

date de fin ;

statut ;

gagnant.

BID
Représente une offre.

Informations :

id ;

enchère ;

utilisateur ;

montant ;

date ;

statut.

ORDER
Représente une commande.

Informations :

id ;

utilisateur ;

montant ;

statut ;

date ;

type de vente.

Le type de vente peut être :

DIRECT
AUCTION
PAYMENT
Représente une transaction de paiement.

Informations :

id ;

commande ;

montant ;

devise ;

référence externe ;

statut ;

date.

NOTIFICATION
Permet de gérer les notifications.

Informations :

id ;

utilisateur ;

type ;

message ;

statut de lecture ;

date.

AUDIT_LOG
Permet de conserver l'historique des opérations sensibles.

Informations :

id ;

utilisateur ;

action ;

entité ;

identifiant de l'entité ;

date ;

informations complémentaires.

8. Sécurité et gestion des cas particuliers
La sécurité est considérée comme une composante fondamentale de l'architecture.

Les objectifs sont :

confidentialité ;

intégrité ;

disponibilité ;

authentification ;

autorisation ;

traçabilité.

8.1 Offre simultanée
Deux utilisateurs peuvent envoyer une offre presque simultanément.

Exemple :

Prix actuel : 2 000 €

Utilisateur A → 2 100 €
Utilisateur B → 2 200 €
La plateforme doit éviter les incohérences.

Solution
Le serveur doit :

démarrer une transaction ;

verrouiller les données nécessaires ;

relire le prix actuel ;

vérifier l'offre ;

enregistrer l'offre ;

mettre à jour le prix ;

valider la transaction.

Le navigateur ne doit jamais être considéré comme la source de vérité.

8.2 Enchère à la dernière seconde
Une règle claire doit être définie pour les offres arrivant juste avant la clôture.

Le serveur doit déterminer la validité d'une offre selon l'heure serveur.

Date de fin
     ↓
Clôture serveur
     ↓
Nouvelles offres refusées
Une solution anti-sniping pourra être étudiée ultérieurement.

8.3 Prix de réserve non atteint
Exemple :

Prix de réserve : 5 000 €
Meilleure offre : 4 500 €
Résultat :

Prix de réserve non atteint
        ↓
Pas de vente
        ↓
Pas de gagnant
        ↓
Pas de paiement
Le statut peut être :

RESERVE_NOT_MET
L'historique des offres reste conservé.

8.4 Paiement refusé
Un paiement refusé doit être clairement distingué d'un paiement réussi.

Commande
   ↓
PENDING_PAYMENT
   ↓
Prestataire de paiement
   ↓
   ├── Succès → PAID
   │
   └── Échec  → PAYMENT_FAILED
La commande et la tentative de paiement restent enregistrées.

8.5 Utilisateur non authentifié
Certaines actions sont publiques :

consulter les œuvres ;

consulter les artistes ;

consulter les enchères.

D'autres nécessitent une authentification :

acheter ;

placer une offre ;

consulter les commandes ;

consulter l'historique personnel.

Exemple :

Visiteur
   ↓
Placer une offre
   ↓
Authentification obligatoire
8.6 Annulation
Les commandes et enchères ne doivent pas être supprimées physiquement lorsqu'elles ont déjà été utilisées.

On privilégie la gestion par statut.

Exemple :

AUCTION
LIVE
  ↓
CANCELLED
ou :

ORDER
CONFIRMED
  ↓
CANCELLED
Cela permet de conserver l'historique.

8.7 Traçabilité
Les opérations sensibles sont enregistrées dans AUDIT_LOG.

Exemples :

création d'une enchère ;

modification d'une enchère ;

annulation ;

dépôt d'une offre ;

clôture ;

désignation du gagnant ;

création d'une commande ;

paiement ;

changement de statut ;

modification administrative.

L'objectif est de pouvoir reconstruire l'historique complet d'une transaction.

9. Mesures de sécurité techniques
Le MVP devra également prévoir :

HTTPS / TLS ;

authentification sécurisée ;

gestion des rôles et permissions ;

hash des mots de passe ;

protection CSRF ;

protection XSS ;

protection contre les injections SQL ;

validation des données côté serveur ;

rate limiting ;

gestion sécurisée des sessions ;

gestion sécurisée des secrets ;

logs serveur ;

audit des opérations sensibles ;

contrôle d'accès au Back Office.

Les clés API et secrets ne doivent jamais être stockés directement dans le code source ou dans GitHub.

10. Principe de sécurité des enchères
Une règle fondamentale est retenue :

Le serveur constitue l'unique source de vérité pour l'état d'une enchère.

Le navigateur peut afficher :

Prix actuel : 3 500 €
Temps restant : 00:15
mais il ne décide jamais :

si l'enchère est ouverte ;

si l'offre est valide ;

quel est le prix réel ;

qui est le gagnant ;

si le paiement est confirmé.

Ces décisions sont exclusivement prises côté serveur.

11. Arborescence du MVP
11.1 Front Office
/
├── Accueil
│
├── Œuvres
│   ├── Catalogue
│   ├── Recherche
│   ├── Filtres
│   └── [Œuvre]
│
├── Artistes
│   ├── Liste
│   └── [Artiste]
│
├── Enchères
│   ├── À venir
│   ├── En cours
│   ├── Terminées
│   └── [Enchère]
│
├── Panier
│
├── Checkout
│
├── Auth
│   ├── Connexion
│   ├── Inscription
│   ├── Mot de passe oublié
│   └── Réinitialisation
│
├── Mon compte
│   ├── Dashboard
│   ├── Profil
│   ├── Commandes
│   ├── Mes offres
│   ├── Mes enchères
│   └── Notifications
│
├── Paiement
│   ├── Succès
│   └── Échec
│
└── Informations
    ├── À propos
    ├── Contact
    ├── FAQ
    ├── CGV
    ├── Confidentialité
    └── Conditions des enchères
12. Back Office
/admin
│
├── Dashboard
│
├── Utilisateurs
│   ├── Liste
│   └── Détail
│
├── Artistes
│   ├── Liste
│   ├── Ajouter
│   └── Modifier
│
├── Catalogue
│   ├── Œuvres
│   ├── Ajouter
│   ├── Modifier
│   ├── Images
│   └── Catégories
│
├── Vente directe
│   ├── Liste des ventes
│   └── Disponibilité
│
├── Enchères
│   ├── Liste
│   ├── Créer un lot
│   ├── Modifier
│   ├── En cours
│   ├── Terminées
│   ├── Offres
│   └── Résultats
│
├── Commandes
│   ├── Toutes
│   ├── En attente
│   ├── Confirmées
│   ├── Échouées
│   └── Annulées
│
├── Paiements
│   ├── Transactions
│   ├── Réussis
│   ├── Échoués
│   └── Remboursements
│
├── Notifications
│
└── Administration
    ├── Rôles & permissions
    ├── Audit Logs
    └── Paramètres
13. Priorisation du MVP
🔴 Priorité 1 — Fonctionnalités indispensables
Front Office
Accueil ;

catalogue ;

détail œuvre ;

artistes ;

panier ;

checkout ;

authentification ;

enchères ;

détail enchère ;

compte client ;

commandes.

Back Office
Dashboard ;

utilisateurs ;

artistes ;

œuvres ;

enchères ;

offres ;

commandes ;

paiements ;

audit.

🟠 Priorité 2 — Fonctionnalités importantes
recherche avancée ;

filtres avancés ;

notifications ;

FAQ ;

catégories ;

gestion des emails.

🟢 Phase future
Les fonctionnalités suivantes pourront être développées après validation du MVP :

intelligence artificielle ;

recommandations personnalisées ;

application mobile ;

CRM avancé ;

analytics avancés ;

marketplace ;

système de fidélité ;

fonctionnalités sociales ;

multilingue avancé.

14. Performance
La performance doit être prise en compte dès la conception.

Les principaux axes sont :

optimisation des images ;

compression ;

lazy loading ;

cache ;

optimisation des requêtes SQL ;

indexation de la base de données ;

limitation des requêtes inutiles ;

CDN ;

mise en cache des ressources statiques ;

monitoring des performances.

Les images d'art doivent notamment être optimisées sans dégrader leur qualité visuelle.

15. SEO
Le référencement naturel est intégré dès le MVP.

Les principales mesures prévues sont :

URLs propres ;

titres HTML optimisés ;

meta descriptions ;

structure H1/H2/H3 cohérente ;

données structurées lorsque pertinentes ;

sitemap XML ;

robots.txt ;

balises canonical ;

optimisation des images ;

attributs ALT ;

pages artistes indexables ;

pages œuvres indexables ;

contenu textuel pertinent ;

performances web ;

compatibilité mobile.

Exemples d'URLs :

/oeuvres
/oeuvres/nom-de-loeuvre
/artistes/nom-artiste
/encheres
/encheres/nom-du-lot
16. Évolutivité
L'architecture doit permettre de faire évoluer la plateforme sans réécrire l'ensemble du système.

Les futures évolutions peuvent inclure :

MVP
 │
 ├── Vente directe
 ├── Enchères
 ├── Catalogue
 ├── Paiement
 └── Administration
          │
          ▼
     Évolutions futures
          │
          ├── IA
          ├── Recommandations
          ├── CRM
          ├── Analytics
          ├── Marketplace
          ├── Mobile
          └── Internationalisation avancée
Le système doit donc privilégier une architecture modulaire avec une séparation claire des responsabilités.

17. Git / GitHub
Le projet doit être versionné dès le début avec Git.

Une organisation possible :

main
 │
 ├── develop
 │
 ├── feature/catalog
 ├── feature/direct-sales
 ├── feature/auctions
 ├── feature/payment
 └── feature/authentication
Les décisions techniques importantes doivent également être documentées.

Exemple :

docs/
├── architecture/
├── database/
├── security/
├── workflows/
└── decisions/
Cela permettra à une autre personne de comprendre et maintenir le projet plus facilement.

18. Décisions techniques à valider
Avant de commencer le développement complet, certaines décisions doivent être validées avec l'encadrant.

Notamment :

Architecture
monolithique modulaire ou architecture distribuée ;

structure Front Office / Back Office / API ;

stratégie de cache.

Enchères
clôture stricte ou système anti-sniping ;

comportement en cas de non-paiement du gagnant ;

visibilité de l'historique des offres ;

règles d'annulation.

Paiement
prestataire de paiement ;

devises supportées ;

gestion des remboursements ;

gestion des paiements échoués.

International
langues supportées au lancement ;

devises ;

règles fiscales ;

formats de date et de prix.

Aucune de ces décisions ne doit être prise arbitrairement lorsqu'elles ont un impact important sur l'architecture ou les règles métier.

19. État actuel de la conception
Les éléments suivants ont été étudiés dans cette phase :

 Analyse fonctionnelle

 Identification des utilisateurs

 Identification des services

 Définition de la vente directe

 Définition du système d'enchères

 Architecture technique du MVP

 Séparation Front Office / Back Office

 Modèle de données

 Entités principales et relations

 Workflow de vente directe

 Workflow d'une enchère

 Sécurité

 Gestion des cas particuliers

 Traçabilité

 Arborescence Front Office

 Arborescence Back Office

 Priorisation du MVP

 Premières orientations SEO

 Premières orientations performance

 Évolutivité

 Organisation Git / GitHub

20. Prochaine étape
La prochaine étape consiste à valider la conception fonctionnelle et technique avant de commencer le développement.

Après validation, le travail pourra progresser progressivement :

Conception
    ↓
Validation
    ↓
Initialisation Git / GitHub
    ↓
Configuration environnement
    ↓
Base de données
    ↓
Authentification
    ↓
Catalogue
    ↓
Vente directe
    ↓
Enchères
    ↓
Commandes
    ↓
Paiements
    ↓
Notifications
    ↓
Back Office
    ↓
Sécurité
    ↓
SEO
    ↓
Tests
    ↓
Déploiement
Conclusion
Le MVP d'Abstract Art Gallery est conçu comme une base professionnelle destinée à évoluer dans le temps.

La plateforme repose sur deux systèmes commerciaux complémentaires — vente directe et enchères — partageant un socle commun composé des utilisateurs, du catalogue, des commandes, des paiements, des notifications, de la sécurité et de la traçabilité.

L'architecture proposée cherche à maintenir un équilibre entre simplicité du MVP, sécurité, performance, SEO et évolutivité.

L'objectif de cette phase est de disposer d'une conception suffisamment précise pour permettre une validation technique avant le démarrage du développement.

Principe directeur : construire dès le MVP une base solide, modulaire et maintenable, sans développer prématurément les fonctionnalités avancées qui appartiennent aux phases futures.
