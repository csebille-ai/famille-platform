# Déploiement sur o2switch (SSH) — famille-platform

Ce guide suppose un hébergement mutualisé o2switch avec accès SSH et un sous-domaine du type `famille.opanoma.fr`.

## Important (Chat temps réel)

- Sur o2switch (mutualisé), faire tourner **Reverb** (WebSocket) en 24/7 est généralement compliqué (pas de process manager + pas de port dédié).
- En prod o2switch, tu as 3 options :
  1) **désactiver** le temps réel (chat fonctionne mais sans live),
  2) utiliser un service externe (Pusher/Ably),
  3) héberger le WebSocket sur un VPS.

Le reste du site (ressources, playlists Spotify embed, images/vidéos) est OK.

## Pré-requis

- Un accès SSH o2switch
- PHP 8.2+ disponible côté serveur
- Une base MySQL créée (et identifiants)

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

## 5) Vérifications post-déploiement

- `https://famille.opanoma.fr` charge correctement
- Login OK
- Ressources : upload/download OK
- Playlists : création + ajout d’un track `open.spotify.com/.../track/...` OK
- Chat : page OK (temps réel selon infra)
