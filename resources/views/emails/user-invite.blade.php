@php
    $appName = config('app.name', 'Portail');
@endphp

<p>Bonjour,</p>

<p>
    Tu as été invité(e) à accéder à <strong>{{ $appName }}</strong>.
</p>

<p>
    Pour choisir ton nom d'utilisateur et ton mot de passe, ouvre ce lien :
</p>

<p>
    <a href="{{ $acceptUrl }}">Activer mon compte</a>
</p>

<p>
    Ensuite, pour installer l'app (PWA) sur ton téléphone :
</p>

<ul>
    <li><strong>Android (Chrome)</strong> : menu ⋮ → “Installer l'application”.</li>
    <li><strong>iPhone (Safari)</strong> : bouton Partager → “Sur l’écran d’accueil”.</li>
</ul>

<p>À bientôt.</p>
