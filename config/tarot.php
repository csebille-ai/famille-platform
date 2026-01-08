<?php

return [
    // Minimal deck for MVP: Major Arcana only.
    // If you want the full 78-card deck later, we can extend this.
    'cards' => [
        ['name' => 'Le Mat', 'keywords' => 'nouveau départ, spontanéité, liberté'],
        ['name' => 'Le Magicien', 'keywords' => 'initiative, habileté, action'],
        ['name' => 'La Papesse', 'keywords' => 'intuition, recul, secrets'],
        ['name' => 'L’Impératrice', 'keywords' => 'créativité, expression, confiance'],
        ['name' => 'L’Empereur', 'keywords' => 'structure, limites, responsabilité'],
        ['name' => 'Le Pape', 'keywords' => 'conseil, valeurs, transmission'],
        ['name' => 'L’Amoureux', 'keywords' => 'choix, lien, cohérence'],
        ['name' => 'Le Chariot', 'keywords' => 'élan, victoire, direction'],
        ['name' => 'La Justice', 'keywords' => 'équilibre, décision, clarté'],
        ['name' => 'L’Hermite', 'keywords' => 'patience, introspection, sagesse'],
        ['name' => 'La Roue de Fortune', 'keywords' => 'cycle, changement, opportunité'],
        ['name' => 'La Force', 'keywords' => 'courage, maîtrise, douceur'],
        ['name' => 'Le Pendu', 'keywords' => 'pause, nouveau regard, lâcher-prise'],
        ['name' => 'L’Arcane sans Nom', 'keywords' => 'fin, transformation, nettoyage'],
        ['name' => 'Tempérance', 'keywords' => 'harmonie, dosage, patience'],
        ['name' => 'Le Diable', 'keywords' => 'attachements, tentation, lucidité'],
        ['name' => 'La Maison Dieu', 'keywords' => 'rupture, révélation, reconstruction'],
        ['name' => 'L’Étoile', 'keywords' => 'espoir, apaisement, confiance'],
        ['name' => 'La Lune', 'keywords' => 'émotions, flou, imagination'],
        ['name' => 'Le Soleil', 'keywords' => 'joie, succès, simplicité'],
        ['name' => 'Le Jugement', 'keywords' => 'appel, renouveau, décision'],
        ['name' => 'Le Monde', 'keywords' => 'accomplissement, clôture, expansion'],
    ],

    // Output guardrails
    'max_chars' => (int) env('TAROT_MAX_CHARS', 1200),
];
