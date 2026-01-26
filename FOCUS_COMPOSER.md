# Focus Composer - Chat UX Improvements

## Vue d'ensemble

Le **Focus Composer** simplifie l'interface de saisie du chat avec un dock à 3 actions premium, centré sur l'expérience utilisateur.

## Fonctionnalités

### 1. Dock à 3 actions (Photo / Vidéo / Micro)

**Ancien système** : Bouton "+" ouvrant un bottom sheet générique avec quota proéminent et actions plates.

**Nouveau système** :
- Bouton "+" (toggle) avec `aria-expanded` pour accessibilité
- Dock élégant avec 3 grandes tuiles visuelles :
  - **Photo** : Gradient bleu → cyan, icône remplie
  - **Vidéo** : Gradient violet → rose, icône caméra
  - **Micro** : Gradient teal → émeraude, icône microphone
- Chaque tuile : icône dans cercle coloré + libellé clair + états hover/pressed
- Animations fluides (220ms ease-out) : opacity + translateY (mobile) / scale (desktop)

### 2. Dictée vocale (Web Speech API)

**Nouvelle fonctionnalité** : Bouton "Micro" pour dicter du texte directement dans le champ.

**Implémentation** :
- Utilise `SpeechRecognition` / `webkitSpeechRecognition`
- Langue : `fr-FR`
- Mode `interimResults` pour feedback visuel en temps réel
- Le texte dicté s'ajoute intelligemment au texte existant
- État actif visible : tuile passe en mode "Stop" avec animation pulse
- Gestion d'erreurs : toast informatif si non supporté / erreur micro

**Fallback** : Toast "Dictée non supportée sur cet appareil" si API absente.

### 3. Séparation Photo / Vidéo

**Ancien** : Un seul input `accept="image/*,video/*"`

**Nouveau** :
- Input séparé pour photos : `chatPhotoInput` / `chatPhotoInputDesktop`
- Input séparé pour vidéos : `chatVideoInput` / `chatVideoInputDesktop`
- Permet à l'OS d'ouvrir le picker approprié (galerie photos vs vidéos)

### 4. Accessibilité

- `aria-expanded` sur bouton "+" (true quand dock ouvert)
- `aria-controls` pointant vers `chatFocusDock` / `chatFocusDockDesktop`
- Navigation clavier : Tab entre actions, ESC ferme dock
- Focus automatique sur première action (Photo) à l'ouverture
- `aria-hidden` synchronisé avec état dock

### 5. UX Desktop vs Mobile

**Mobile** :
- Bottom sheet avec backdrop semi-transparent
- Slide up animation (translateY)
- Backdrop clickable pour fermer
- Dock au-dessus de la bottom-nav

**Desktop** :
- Dock en position `absolute bottom-full` (au-dessus du composer)
- Scale up animation (scale 95% → 100%)
- Pas de backdrop (click outside ferme)
- Présentation plus compacte

## Architecture technique

### Fichiers modifiés

**Views (Blade)** :
- `resources/views/chat/partials/index-bottom-dock.blade.php` : Composer mobile + dock
- `resources/views/chat/partials/index-composer-desktop.blade.php` : Composer desktop + dock

**JavaScript** :
- `resources/js/chat-page.js` : 
  - Ajout de `focusDock` object (mobile/desktop elements)
  - Fonctions `setFocusDockOpen()`, `startDictation()`, `stopDictation()`
  - Helper `showToast()` pour notifications
  - Refonte `bindAttachFor()` pour gérer dock + inputs séparés
  - Event listener global ESC

**Nouveau fichier** :
- `resources/js/chat-focus-dock.js` : Version standalone (référence, pas importé)

### État JavaScript

```javascript
let focusDockOpen = false;
let dictationRecognition = null;
let isDictating = false;
let focusDockCloseTimer = null;
```

### Composer object (mis à jour)

```javascript
const composer = {
    mobile: {
        photoInput: document.getElementById('chatPhotoInput'),
        videoInput: document.getElementById('chatVideoInput'),
        // ...autres éléments
    },
    desktop: {
        photoInput: document.getElementById('chatPhotoInputDesktop'),
        videoInput: document.getElementById('chatVideoInputDesktop'),
        // ...
    }
};
```

## Design système

### Tuiles (actions)

**Structure** :
```html
<button class="flex flex-col items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-{color}-50 to-{color2}-50 hover:from-{color}-100 ...">
    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-{color}-500 to-{color2}-500 flex items-center justify-center shadow-sm">
        <i class="ph-fill ph-{icon} text-[22px] text-white"></i>
    </div>
    <span class="text-sm font-semibold text-slate-900">{Label}</span>
</button>
```

**Couleurs** :
- Photo : Bleu (50→100, 500) → Cyan (50→100, 500)
- Vidéo : Violet (50→100, 500) → Rose (50→100, 500)
- Micro : Teal (50→100, 500) → Émeraude (50→100, 500)

### Animations

**Mobile dock** :
```css
opacity: 0 → 1
transform: translateY(6) → translateY(0)
transition: 220ms ease-out
```

**Desktop dock** :
```css
opacity: 0 → 1
transform: scale(0.95) → scale(1)
transition: 200ms ease-out
```

**Backdrop** :
```css
opacity: 0 → 1
transition: 200ms
```

**Dictation pulse** :
```css
animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite
```

## Tests manuels recommandés

### ✅ Checklist fonctionnelle

- [ ] Clic sur "+" ouvre le dock
- [ ] Clic backdrop/ESC ferme le dock
- [ ] Clic "Fermer" ferme le dock
- [ ] Re-clic "+" toggle le dock
- [ ] Photo : ouvre galerie, upload fonctionne
- [ ] Vidéo : ouvre vidéos, upload fonctionne
- [ ] Micro : démarre dictée (Chrome/Edge/Safari)
- [ ] Dictée : texte apparaît en temps réel
- [ ] Dictée : clic "Stop" arrête
- [ ] Toast affiché si dictée non supportée (Firefox)
- [ ] Tab navigation fonctionne
- [ ] Focus visible (ring bleu)
- [ ] Pas de régression : envoi message OK
- [ ] Pas de régression : scroll messages OK
- [ ] Desktop : dock au-dessus composer
- [ ] Mobile : dock au-dessus bottom-nav

### ⚠️ Edge cases

- [ ] Dictée interrompue si dock fermé pendant enregistrement
- [ ] Pas de double dictée si multi-clic rapide
- [ ] Textarea auto-resize pendant dictée
- [ ] Upload placeholder ne casse pas layout
- [ ] prefers-reduced-motion respecté (pas d'animation)

## Améliorations futures

### Court terme
- [ ] Swipe down sur dock mobile pour fermer (geste natif)
- [ ] Haptic feedback sur actions (si supporté)
- [ ] Meilleure intégration toast (système centralisé)

### Moyen terme
- [ ] Enregistrement audio natif (fallback si pas Web Speech API)
- [ ] Historique dictée (corrections)
- [ ] Support multi-langues (détection auto langue)

### Long terme
- [ ] Transcription serveur (Whisper API) pour meilleure précision
- [ ] Prévisualisation photo/vidéo avant envoi
- [ ] Édition rapide (crop, rotate) avant upload

## Notes techniques

### Compatibilité navigateurs

**Web Speech API (Dictée)** :
- ✅ Chrome/Edge (desktop + mobile)
- ✅ Safari iOS 14.5+
- ❌ Firefox (pas de support)
- ⚠️ Nécessite HTTPS (ou localhost)

**Animations** :
- Utilise Tailwind transitions
- Respecte `prefers-reduced-motion`
- Fallback immédiat si media query échoue

### Performance

- Pas de reflow pendant animation (GPU compositing via `transform`)
- Event listeners dé-dupliqués (un par instance mobile/desktop)
- `requestAnimationFrame` pour synchronisation visuelle
- Cleanup proper de `SpeechRecognition` instance

### Sécurité

- Upload validations inchangées (taille, type, quota)
- Dictée ne bypasse pas validation message vide
- Pas de stockage texte dicté (privacy-first)

## Migration depuis ancien système

### Ancien bottom sheet (`chatAttachSheet`)

**Statut** : Conservé temporairement pour compatibilité.

**Éléments** :
- `chatAttachSheet` (overlay)
- `chatAttachPickMedia` (bouton media)
- `chatAttachPickVoice` (bouton voix)
- `chatAttachQuota` (affichage quota)

**Action requise** : Peut être supprimé une fois Focus Dock validé en production.

### Migration graduelle

1. **Phase 1 (actuelle)** : Focus Dock actif, ancien système en parallèle
2. **Phase 2** : Supprimer event listeners ancien système
3. **Phase 3** : Supprimer HTML ancien bottom sheet
4. **Phase 4** : Nettoyer CSS/JS lié à `chatAttachSheet`

---

**Commit** : `7599059 - Focus Composer: 3-action dock (Photo/Video/Micro) with dictation support`
**Date** : Janvier 2026
