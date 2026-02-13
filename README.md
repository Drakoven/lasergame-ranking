# LaserGame — Classement (WAMP / PHP / MySQL)

Application web pour soumettre un score (avec photo) et valider/refuser via une interface admin.
- Soumission joueur : `/lasergame/form.html`
- Admin : `/lasergame/admin/login.php`

## Prérequis
- WAMP (Apache + PHP + MySQL)
- phpMyAdmin

## Installation (local WAMP)
1. Placer le dossier `lasergame/` dans `C:\wamp64\www\`
2. Créer une base MySQL `lasergame`
3. Importer/Créer les tables :
   - `admins`
   - `scores`
   - `party_codes`
4. Copier `api/config.example.php` vers `api/config.php`
   - Renseigner les identifiants MySQL
5. Vérifier les permissions du dossier :
   - `uploads/` doit être inscriptible par Apache (sur WAMP ça passe généralement)

## Fonctionnement
### Formulaire joueur
- Remplit email, date, pseudos, score, code de partie, photo
- Envoie vers `api/submit_score.php`
- Le score arrive en `status = 0` (en attente)

### Admin
- Génère des codes temporaires (30 minutes)
- Consulte la liste des scores
- Change le statut :
  - 0 = En attente
  - 1 = Accepté
  - 2 = Rejeté

## Sécurité
- Upload limité à JPG/PNG, max 5 Mo, check `getimagesize()`
- Protection `.htaccess` dans `/uploads`
- Actions admin protégées par CSRF

## Notes GitHub
- `api/config.php` est ignoré (secrets)
- Les fichiers uploadés ne sont pas versionnés
