# Déploiement sur o2switch (SSH) — famille-platform

Ce guide suppose un hébergement mutualisé o2switch avec accès SSH et un sous-domaine du type `famille.opanoma.fr`.

## Important (Chat temps réel)

- Sur o2switch (mutualisé), faire tourner **Reverb** (WebSocket) en 24/7 est généralement compliqué (pas de process manager + pas de port dédié).
- En prod o2switch, tu as 3 options :
  1) **désactiver** le temps réel (chat fonctionne mais sans live),
  2) utiliser un service externe (Pusher/Ably),
  3) héberger le WebSocket sur un VPS.

Le reste du site (playlists Spotify embed, images/vidéos) est OK.

## Pré-requis

- Un accès SSH o2switch
- PHP 8.2+ disponible côté serveur
- Une base MySQL créée (et identifiants)

### Note importante (Composer sur mutualisé / cPanel)

Sur certains hébergements, la commande `composer` fournie par cPanel peut être cassée (ex: erreur fatale `React\Promise\ExtendedPromiseInterface::otherwise(...) must be compatible with ...`).

Dans ce cas, n’utilise pas `composer` global. Installe un Composer local dans le dossier du projet :

```bash
cd ~/apps/famille-platform

# Télécharge Composer (phar) propre
php -r "copy('https://getcomposer.org/composer-stable.phar', 'composer.phar');"

# Vérifie la version
php composer.phar --version

# Installe les deps
php composer.phar install --no-dev --optimize-autoloader
```

Si `php` n’est pas en 8.2+ sur ton serveur, utilise le binaire PHP correct (ex: `php82`, `php83` selon l’hébergeur) :

```bash
php82 -v
php82 composer.phar install --no-dev --optimize-autoloader
```

Si tu installes en `--no-dev` et/ou `--no-scripts`, pense aussi à vider les caches Laravel *avant* de relancer des commandes `artisan` (sinon un ancien cache peut référencer des packages dev comme `laravel/pail`) :

```bash
rm -f bootstrap/cache/*.php
```

## 1) Arborescence recommandée

Sur le serveur, clone le projet hors du webroot, puis fais pointer le sous-domaine vers `public/`.

Exemple :

- Code : `~/apps/famille-platform`
- Docroot du sous-domaine : `~/apps/famille-platform/public`

## 2) Premier déploiement (une seule fois)

### 2.1 Cloner la branche main

```bash
mkdir -p ~/apps
cd ~/apps

git clone https://github.com/csebille-ai/famille-platform.git famille-platform
cd famille-platform

git checkout main
```

### 2.2 Configurer l’environnement

Créer le fichier `.env` sur le serveur (ne pas le committer) :

Tu peux partir du template : `.env.o2switch.example`.

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://famille.opanoma.fr`
- `APP_KEY=...` (généré via artisan)
- DB (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)

Puis :

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
```

### 2.3 Storage / permissions

Sur mutualisé, il faut que `storage/` et `bootstrap/cache/` soient inscriptibles :

```bash
chmod -R ug+rwx storage bootstrap/cache
```

(Si ça ne suffit pas, ajuste via le manager o2switch / droits de fichiers.)

### 2.4 Assets (Vite)

Deux stratégies :

- **Stratégie A (recommandée mutualisé)** : build en local/CI et pousser `public/build` dans la branche `main`.
- Stratégie B : build sur le serveur (nécessite Node/NPM sur le serveur).

Si tu utilises la stratégie A, tu n’as rien à faire sur le serveur : `public/build` est déjà dans le repo.

### 2.5 Cache (prod)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3) Déploiements suivants (update)

À chaque release :

```bash
cd ~/apps/famille-platform

git checkout main

git pull

composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan optimize:clear
rm -f storage/framework/views/*.php

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 4) Réglages `.env` prod conseillés

- `APP_ENV=production`
- `APP_DEBUG=false`
- `LOG_LEVEL=warning`
- `SESSION_DRIVER=file` (OK en mutualisé)
- `CACHE_STORE=file` (OK en mutualisé)

### Broadcast / Chat

Si tu ne peux pas faire tourner Reverb en prod o2switch, mets par exemple :

- `BROADCAST_CONNECTION=log`

(Le chat reste utilisable mais sans temps réel.)

## 5) Astro-engine (profil astro)

Le calcul astro (signe lunaire, décans kémétiques, etc.) dépend du micro-service `astro-engine`.

### Variables `.env`

Dans `.env` (Laravel), configure :

- `ASTRO_ENGINE_URL=https://astro-engine.ciyu0696.odns.fr`
- `ASTRO_ENGINE_VERIFY_SSL=false` (nécessaire si le certificat du sous-domaine est auto-signé)

Puis recache la config :

```bash
php artisan optimize:clear
php artisan config:cache
```

### Vérifier que l'engine répond

Sur le serveur, teste :

```bash
curl -k "$ASTRO_ENGINE_URL/health"

curl -k -X POST "$ASTRO_ENGINE_URL/sun" \
  -H "Content-Type: application/json" \
  -d '{"date":"1990-05-10","time":"13:30","timezone":"Europe/Paris"}'

Pour diagnostiquer rapidement côté Laravel (URL finale, TLS, endpoint chart), utilise aussi :

```bash
php artisan astro:engine-check

# override ponctuel
php artisan astro:engine-check --url="https://astro-engine.exemple.tld" --verify-ssl=false
```
```

Note : `-k` est uniquement pour diagnostiquer côté shell. Côté Laravel, c'est `ASTRO_ENGINE_VERIFY_SSL=false` qui évite l'échec TLS.

### Backfill / Recompute (pour enlever “À calculer”)

Après déploiement + migration, relance le calcul du profil astro :

```bash
# 1 utilisateur
php artisan astro:recompute --email="toi@exemple.fr" --sync --yes

# ou tous (à faire hors heures de pointe)
php artisan astro:recompute --all --queue --yes
```

### Redémarrer l'app Node (cPanel / Passenger)

Après un `git pull` dans `astro-engine/` (ou si tu modifies `passenger.json` / `app.cjs` / deps), il faut redémarrer l'app Node.

- **cPanel → Setup Node.js App** : sélectionner l'app `astro-engine` puis cliquer **Restart**.
- Si tu n'as pas le bouton : dans le **File Manager**, modifier/toucher le fichier `tmp/restart.txt` dans le dossier de l'app (Passenger) pour forcer un reload.

Ensuite, re-teste :

```bash
curl -k "$ASTRO_ENGINE_URL/health"

curl -k -X POST "$ASTRO_ENGINE_URL/chart" \
  -H "Content-Type: application/json" \
  -d '{"utc":"2026-01-16T12:00:00Z","lat":48.8566,"lng":2.3522}'
```

### Recommandation (sécurité)

Idéalement, installe un vrai certificat (Let’s Encrypt) sur `astro-engine...` et remets `ASTRO_ENGINE_VERIFY_SSL=true`.

## 6) Cron (scheduler)

Pour alimenter l'actu locale via RSS, configure un cron (o2switch) qui exécute le scheduler Laravel toutes les minutes :

```bash
cd ~/apps/famille-platform
php artisan schedule:run >> /dev/null 2>&1
```

Et dans ton `.env`, configure tes flux :

- Option simple (CSV) : `NEWS_FEEDS=https://exemple.tld/rss.xml,https://autre.tld/atom.xml`
- Option plus propre (JSON) : `NEWS_FEEDS_JSON=["https://exemple.tld/rss.xml","https://autre.tld/atom.xml"]`
- Option “encore mieux” (sources + tags) :
  - `NEWS_SOURCES_JSON=[{"name":"Préfecture","url":"https://.../rss.xml","tag":"securite","enabled":true},{"name":"Sud Ouest","url":"https://.../rss.xml","tag":"commune","enabled":true}]`

## 7) Vérifications post-déploiement

- `https://famille.opanoma.fr` charge correctement
- Login OK
- Playlists : création + ajout d’un track `open.spotify.com/.../track/...` OK
- Chat : page OK (temps réel selon infra)

Si tu as une erreur de class not found après un refactor (ex: suppression d'une classe), ajoute aussi :

```bash
composer dump-autoload -o
```
