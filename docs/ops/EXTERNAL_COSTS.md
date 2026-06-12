# Surveillance des coûts — contributions / services externes

Objectif : centraliser **tout ce qui peut coûter** (ou générer une facture surprise) et fournir les **liens directs** pour surveiller l’usage, configurer des **plafonds** et des **alertes**.

> Règle d’or : aucun secret dans ce fichier. On note uniquement des identifiants non sensibles (ex: account id), et des URLs de consoles.

## Checklist (à faire une fois)

- Mettre un **budget mensuel** + **alertes** sur chaque provider payant.
- Quand c’est possible : créer une **clé API “prod” dédiée** avec un plafond et une **clé “dev”** séparée.
- Vérifier les “périodes de facturation” (souvent mensuel) et **s’assurer que les alertes sont en e-mail** (et idéalement sur Slack).
- Faire une revue rapide 1x/semaine au début, puis 1x/mois.

## Services utilisés par ce repo (janv. 2026)

| Service | Utilisation dans ce projet | Variables / points d’ancrage | Liens de monitoring | Alertes / budgets recommandés |
|---|---|---|---|---|
| Cloudflare (R2) | Stockage fichiers (cloud), avatars générés, vidéos/photos, uploads multipart (S3 compatible) | `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_BASE_URL`, `UPLOAD_QUOTA_BYTES` | Dashboard: https://dash.cloudflare.com/ • R2: https://dash.cloudflare.com/?to=/:account/r2/overview • Billing: https://dash.cloudflare.com/?to=/:account/billing | Définir un budget mensuel Cloudflare si dispo + alertes. Surveiller **storage (GB-mois)**, **Class A/B operations**, egress éventuel, et pics de requêtes. |
| Cloudflare (Workers AI / API) | Génération d’images astro via Workers AI (selon config) | `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_AI_IMAGE_MODEL` | Workers AI: https://dash.cloudflare.com/?to=/:account/workers-ai • Billing: https://dash.cloudflare.com/?to=/:account/billing | Budget mensuel + alertes. Surveiller volume de générations / erreurs / latence (un bug peut spammer). |
| OpenAI (API) | Tarot (texte), TTS audio, génération image (selon config) | `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_BASE_URL`, `OPENAI_IMAGE_MODEL`, `OPENAI_TTS_MODEL`, `OPENAI_TTS_VOICE` | Usage: https://platform.openai.com/usage • Billing: https://platform.openai.com/settings/organization/billing/overview • Limits: https://platform.openai.com/settings/organization/limits | Mettre des **hard limits** (monthly budget / usage limits) au niveau projet/org. Surveiller pics (TTS/Images = dépenses rapides). |
| Spotify (Developer) | Recherche Spotify + ajout de tracks dans playlists (API) + embeds | `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET` | Dashboard: https://developer.spotify.com/dashboard | Pas de coût direct en général, mais surveiller quotas/ratelimits. |
| Mail (Postmark) | Option d’envoi e-mail (si utilisé) | `POSTMARK_API_KEY` | Dashboard: https://account.postmarkapp.com/ • Billing: https://account.postmarkapp.com/billing | Mettre alertes d’envoi / quotas / plafond. |
| Mail (Resend) | Option d’envoi e-mail (si utilisé) | `RESEND_API_KEY` | Dashboard: https://resend.com/ • Billing: https://resend.com/billing | Mettre alertes d’envoi / plafond. |
| Mail (AWS SES) | Option d’envoi e-mail via AWS (si utilisé) | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` | SES: https://console.aws.amazon.com/ses/ • Billing: https://console.aws.amazon.com/billing/home | Utiliser **AWS Budgets** + alertes. Surveiller volume emails/sortant. |
| Logs → Slack (optionnel) | Envoi des logs critiques vers Slack via webhook | `LOG_SLACK_WEBHOOK_URL`, `LOG_SLACK_USERNAME` | Slack admin: https://api.slack.com/apps (selon app) | Pas un coût direct, mais utile pour recevoir les alertes des providers. |
| Web Push (VAPID) | Notifications push (upload photo) | `WEBPUSH_SUBJECT`, `WEBPUSH_PUBLIC_KEY`, `WEBPUSH_PRIVATE_KEY` | Pas de dashboard payant (APNs/FCM gérés par OS, généralement gratuit) | Pas de coût direct, mais surveiller les erreurs (push qui échouent) dans les logs. |
| Geo (OpenStreetMap Nominatim) | Résolution lieu → coordonnées (gratuit) | `NOMINATIM_URL`, `NOMINATIM_USER_AGENT` | Status/policy: https://nominatim.org/ | Pas de coût direct, mais risque de blocage si trop d’appels. Ajouter cache et limiter. |
| Reverb (WebSocket, self-host) | Temps réel chat en dev (Docker). En prod o2switch souvent désactivé | `REVERB_*` / `BROADCAST_CONNECTION` | N/A (self-host) | Pas de coût direct, mais en prod si externalisé (Pusher/Ably) → voir lignes ci-dessous. |
| Pusher (optionnel) | Alternative SaaS temps réel (si activé) | `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER` | Dashboard: https://dashboard.pusher.com/ | Mettre alertes/quotas car ça peut monter vite avec du chat. |
| Ably (optionnel) | Alternative SaaS temps réel (si activé) | `ABLY_KEY` | Dashboard: https://ably.com/dashboard | Mettre alertes/quotas. |
| Hébergement o2switch | Hébergement prod mutualisé + DB MySQL | (hors env vars applicatives) | Espace client: https://o2switch.fr/client/ (ou cPanel) | Surveiller renouvellement, quotas disque/inodes, backups. |

## Notes d’exploitation (anti “facture surprise”)

- Cloudflare R2 : surveiller les **uploads multipart** (un bug front peut relancer des chunks en boucle). Garder un œil sur les logs/app et sur la taille totale du bucket.
- OpenAI : le **TTS** et surtout les **images** peuvent coûter vite. Mettre des limites au niveau projet et éviter les boucles de retry silencieuses.
- Temps réel : si tu actives Pusher/Ably, le chat peut générer beaucoup d’événements. Prévoir un budget/plafond.

## À compléter (infos internes)

- Propriétaire des comptes (email) : …
- Budget mensuel global cible : …
- Seuils d’alerte : 50% / 80% / 100% : …
- Canal Slack pour alertes : …
