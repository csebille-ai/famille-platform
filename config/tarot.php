<?php

return [
    // Where card images are served from.
    // For this project we serve Major Arcana from public/tarot as PNG files.
    // NOTE: The UI uses asset('tarot/...') directly; this remains for legacy/compat use.
    'assets_base_url' => rtrim((string) env('TAROT_ASSETS_BASE_URL', '/tarot'), '/'),

    // Minimal deck for MVP: Major Arcana only.
    // If you want the full 78-card deck later, we can extend this.
    'cards' => [
        ['n' => 0, 'slug' => 'le-mat', 'file' => '00-le-mat.png', 'name' => 'Le Mat', 'keywords' => 'nouveau départ, spontanéité, liberté'],
        ['n' => 1, 'slug' => 'le-bateleur', 'file' => '01-le-bateleur.png', 'name' => 'Le Bateleur', 'keywords' => 'initiative, habileté, action'],
        ['n' => 2, 'slug' => 'la-papesse', 'file' => '02-la-papesse.png', 'name' => 'La Papesse', 'keywords' => 'intuition, recul, secrets'],
        ['n' => 3, 'slug' => 'l-imperatrice', 'file' => '03-limperatrice.png', 'name' => 'L’Impératrice', 'keywords' => 'créativité, expression, confiance'],
        ['n' => 4, 'slug' => 'l-empereur', 'file' => '04-lempereur.png', 'name' => 'L’Empereur', 'keywords' => 'structure, limites, responsabilité'],
        ['n' => 5, 'slug' => 'le-pape', 'file' => '05-le-pape.png', 'name' => 'Le Pape', 'keywords' => 'conseil, valeurs, transmission'],
        ['n' => 6, 'slug' => 'l-amoureux', 'file' => '06-lamoureux.png', 'name' => 'L’Amoureux', 'keywords' => 'choix, lien, cohérence'],
        ['n' => 7, 'slug' => 'le-chariot', 'file' => '07-le-chariot.png', 'name' => 'Le Chariot', 'keywords' => 'élan, victoire, direction'],
        ['n' => 8, 'slug' => 'la-justice', 'file' => '08-la-justice.png', 'name' => 'La Justice', 'keywords' => 'équilibre, décision, clarté'],
        ['n' => 9, 'slug' => 'l-hermite', 'file' => '09-lhermite.png', 'name' => 'L’Hermite', 'keywords' => 'patience, introspection, sagesse'],
        ['n' => 10, 'slug' => 'la-roue-de-fortune', 'file' => '10-la-roue-de-fortune.png', 'name' => 'La Roue de Fortune', 'keywords' => 'cycle, changement, opportunité'],
        ['n' => 11, 'slug' => 'la-force', 'file' => '11-la-force.png', 'name' => 'La Force', 'keywords' => 'courage, maîtrise, douceur'],
        ['n' => 12, 'slug' => 'le-pendu', 'file' => '12-le-pendu.png', 'name' => 'Le Pendu', 'keywords' => 'pause, nouveau regard, lâcher-prise'],
        ['n' => 13, 'slug' => 'l-arcane-sans-nom', 'file' => '13-larcane-sans-nom.png', 'name' => 'L’Arcane sans Nom', 'keywords' => 'fin, transformation, nettoyage'],
        ['n' => 14, 'slug' => 'temperance', 'file' => '14-temperance.png', 'name' => 'Tempérance', 'keywords' => 'harmonie, dosage, patience'],
        ['n' => 15, 'slug' => 'le-diable', 'file' => '15-le-diable.png', 'name' => 'Le Diable', 'keywords' => 'attachements, tentation, lucidité'],
        ['n' => 16, 'slug' => 'la-maison-dieu', 'file' => '16-la-maison-dieu.png', 'name' => 'La Maison Dieu', 'keywords' => 'rupture, révélation, reconstruction'],
        ['n' => 17, 'slug' => 'l-etoile', 'file' => '17-letoile.png', 'name' => 'L’Étoile', 'keywords' => 'espoir, apaisement, confiance'],
        ['n' => 18, 'slug' => 'la-lune', 'file' => '18-la-lune.png', 'name' => 'La Lune', 'keywords' => 'émotions, flou, imagination'],
        ['n' => 19, 'slug' => 'le-soleil', 'file' => '19-le-soleil.png', 'name' => 'Le Soleil', 'keywords' => 'joie, succès, simplicité'],
        ['n' => 20, 'slug' => 'le-jugement', 'file' => '20-le-jugement.png', 'name' => 'Le Jugement', 'keywords' => 'appel, renouveau, décision'],
        ['n' => 21, 'slug' => 'le-monde', 'file' => '21-le-monde.png', 'name' => 'Le Monde', 'keywords' => 'accomplissement, clôture, expansion'],
    ],

    // Output guardrails
    'max_chars' => (int) env('TAROT_MAX_CHARS', 1200),
];
