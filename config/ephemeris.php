<?php

return [
    // Default location used for sunrise/sunset.
    // You can override via env if needed.
    'timezone' => env('EPHEMERIS_TZ', 'Europe/Paris'),
    'location' => [
        'lat' => (float) env('EPHEMERIS_LAT', 48.8566),
        'lng' => (float) env('EPHEMERIS_LNG', 2.3522),
    ],

    // Minimal internal saint calendar (MM-DD => label).
    // V1: intentionally limited; can be enriched over time.
    'saints' => [
        '01-01' => 'Saint Basile',
        '01-06' => 'Saint Melchior',
        '01-17' => 'Sainte Roseline',
        '01-21' => 'Sainte Agnès',
        '01-25' => 'Saint Paul',
        '02-02' => 'Sainte Candice',
        '02-06' => 'Saint Gaston',
        '02-14' => 'Saint Valentin',
        '02-22' => 'Sainte Isabelle',
        '03-01' => 'Saint Aubin',
        '03-17' => 'Saint Patrick',
        '03-19' => 'Saint Joseph',
        '03-25' => 'Saint Humbert',
        '04-01' => 'Saint Hugues',
        '04-23' => 'Saint Georges',
        '04-25' => 'Saint Marc',
        '05-01' => 'Saint Jérémie',
        '05-11' => 'Sainte Estelle',
        '05-18' => 'Saint Éric',
        '06-13' => 'Saint Antoine',
        '06-21' => 'Saint Rodolphe',
        '06-24' => 'Saint Jean',
        '07-04' => 'Saint Florent',
        '07-22' => 'Sainte Madeleine',
        '08-10' => 'Saint Laurent',
        '08-15' => 'Sainte Marie',
        '09-14' => 'Saint Cyprien',
        '09-29' => 'Saint Michel',
        '10-04' => 'Saint François',
        '10-18' => 'Saint Luc',
        '11-11' => 'Saint Martin',
        '11-30' => 'Saint André',
        '12-04' => 'Sainte Barbe',
        '12-06' => 'Saint Nicolas',
        '12-13' => 'Sainte Lucie',
        '12-25' => 'Noël',
    ],

    // Token (last name) => rhyme key.
    // Keep keys small and reusable.
    'rhyme_keys' => [
        'paul' => 'ol',
        'raoul' => 'ol',
        'marc' => 'ar',
        'patrick' => 'ik',
        'martin' => 'in',
        'andré' => 'e',
        'andre' => 'e',
        'michel' => 'el',
        'jean' => 'an',
        'laurent' => 'an',
        'nicolas' => 'a',
        'luc' => 'uk',
        'agnès' => 'es',
        'agnes' => 'es',
        'barbe' => 'ar',
        'valentin' => 'in',
        'joseph' => 'ef',
        'georges' => 'or',
        'françois' => 'wa',
        'francois' => 'wa',
        'lucie' => 'i',
        'marie' => 'i',
        'estelle' => 'el',
        'antoine' => 'en',
        'gaston' => 'on',
        'isabelle' => 'el',
        'eric' => 'ik',
        'éric' => 'ik',
    ],

    // Rhyme key => allowed last words.
    // Absolute rule: proverb must end with a word from the matching bank.
    'rhyme_banks' => [
        'ol' => ['farandole', 'boussole', 'gondole', 'rigole', 'bricole', 'casserole'],
        'ar' => ['bazar', 'regard', 'brouillard', 'hasard', 'départ', 'retard'],
        'ik' => ['musique', 'pratique', 'unique', 'magique', 'rythmique'],
        'in' => ['matin', 'chemin', 'jardin', 'câlin', 'voisin', 'destin'],
        'e' => ['journée', 'rosée', 'volonté', 'clarté', 'bonté', 'santé'],
        'el' => ['ciel', 'miel', 'appel', 'étincelle', 'fidèle', 'modèle'],
        'an' => ['printemps', 'avant', 'moment', 'écran', 'courant', 'serein'],
        'a' => ['voilà', 'là', 'ça', 'basta', 'papa', 'mama'],
        'uk' => ['truc', 'duc', 'chic', 'bloc'],
        'es' => ['promesses', 'tendresse', 'sagesse', 'richesses'],
        'ef' => ['bref', 'chef', 'relief'],
        'or' => ['trésor', 'accord', 'dehors', 'effort'],
        'wa' => ['joie', 'voie', 'foi', 'loi'],
        'i' => ['harmonie', 'mélodie', 'poésie', 'magie', 'folie'],
        'en' => ['présent', 'souvent', 'gentiment', 'lentement'],
        'on' => ['maison', 'saison', 'frisson', 'horizon'],
    ],

    // Theme pools.
    'themes' => [
        'nature' => [
            'actions' => ['Observe', 'Respire', 'Sème', 'Arrose', 'Écoute', 'Laisse', 'Marche'],
            'advices' => ['garde le cœur léger', 'prends le temps', 'reste constant', 'sois patient', 'avance doucement'],
            'images' => ['le vent', 'la pluie', 'le soleil', 'la rosée', 'les feuilles', 'le ruisseau', 'la montagne'],
        ],
        'musique' => [
            'actions' => ['Accorde', 'Chante', 'Bats', 'Joue', 'Écoute', 'Ralentis', 'Compose'],
            'advices' => ['garde le rythme', 'reste à l’écoute', 'sois régulier', 'vise juste', 'prends la mesure'],
            'images' => ['le refrain', 'la note', 'le tempo', 'la mélodie', 'le silence', 'le chœur'],
        ],
    ],

    // Locked templates. The final token MUST be {rhyme_word}.
    'templates' => [
        'À la {saint_name}, {fragment}, {rhyme_word}.',
        'À la {saint_name}, {fragment} — {rhyme_word}.',
        'À la {saint_name}, {fragment} : {rhyme_word}.',
        'À la {saint_name}, {fragment}; {rhyme_word}.',
        'À la {saint_name}, {fragment}… {rhyme_word}.',
        'À la {saint_name}, {fragment}, {rhyme_word} !',
    ],
];
