# MKS — Marseille Kulture Spot

Plateforme de consultation et réservation d’événements culturels à Marseille.

## Stack
- Symfony + Twig
- MySQL
- Doctrine
- Mailpit (emails en local)

## Fonctionnalités principales
- Consultation des événements (recherche, filtres, tri)
- Détail événement + réservation
- Demande de réservation groupe (+6 places)
- Compte utilisateur (profil, photo, réservations)
- Admin: CRUD événements, catégories, utilisateurs, demandes
- Import AMP + carte des événements

## Parcours utilisateur (rapide)
1. Je cherche un événement via les filtres.
2. Je consulte la fiche et je réserve (ou je fais une demande groupe).
3. Je retrouve mes réservations et l’état des demandes dans mon profil.

## Prérequis
- PHP 8.2+
- Composer
- MySQL (local)
- Symfony CLI (optionnel)
- Mailpit (optionnel, pour voir les emails)

## Configuration (.env.local)
Exemple minimal:
```
APP_ENV=dev
APP_SECRET=change_me
DATABASE_URL="mysql://admin:motdepasse@127.0.0.1:3306/mks?serverVersion=8.0"
MAILER_DSN="smtp://127.0.0.1:1025"
PEXELS_API_KEY="votre_cle"
```

## Démarrage rapide
```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load --no-interaction
symfony serve -d
```

## Comptes & accès
- Admin (exemple):
```bash
php bin/console app:create-admin --email=admin@mail.com --password=987654321
```

## Import AMP (événements culturels)
```bash
php bin/console app:import-amp --email=admin@mail.com --limit=50
```

## Images d’événements (Pexels)
Remplit les événements sans image avec une photo.
```bash
php bin/console app:fill-event-images
```

## Mailpit (local)
Lancer Mailpit puis ouvrir l’interface web pour lire les emails envoyés.
DSN utilisé: `smtp://127.0.0.1:1025`

## Sécurité
Voir `docs/securite.md`.
