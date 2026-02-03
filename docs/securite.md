# Sécurité (checklist junior)

Objectif : couvrir les failles web classiques sans complexité.

## 1) XSS (injection de scripts)
- ✅ Twig échappe les variables par défaut.
- ✅ Pas de `|raw` sur des contenus utilisateurs.
- ✅ Popups de la carte : escape HTML côté JS.

## 2) Injections SQL
- ✅ Doctrine/QueryBuilder (paramètres).
- ✅ Pas de concaténation SQL brute.

## 3) CSRF (formulaires)
- ✅ CSRF activé sur les formulaires Symfony.
- ✅ Tokens vérifiés sur actions sensibles (suppression, réservation, etc.).

## 4) Brute force login
- ✅ `login_throttling` actif (max 5 tentatives / minute).

## 5) Uploads
- ✅ Types autorisés : JPG/PNG/WEBP.
- ✅ Taille max : 2 Mo.
- ✅ Renommage des fichiers.

## 6) Headers HTTP de base
- ✅ `X-Content-Type-Options: nosniff`
- ✅ `X-Frame-Options: SAMEORIGIN`
- ✅ `Referrer-Policy: strict-origin-when-cross-origin`
- ✅ `Permissions-Policy: geolocation=(), camera=(), microphone=()`
- ✅ CSP simple (scripts/styles externes autorisés selon besoin)

## 7) Sessions
- ✅ Cookies HttpOnly par défaut (Symfony).
- 🔧 En prod : activer `cookie_secure: true` + `same_site: lax/strict`.

## 8) Accès & rôles
- ✅ Routes admin protégées (ROLE_ADMIN).
- ✅ Annonceur limité aux événements.

## Vérifs rapides (avant soutenance)
- Login : test bruteforce (5 essais).
- Création event : validation date/capacité.
- Upload image : mauvais fichier refusé.
- Formulaires sensibles : action sans token => refusée.
