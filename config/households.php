<?php

return [
    'chez-papimami' => [
        'label' => 'Chez PapiMami',
        'icon' => '🏖️',
        'host_user_id' => 1, // Christophe
        'member_user_ids' => [1, 2], // Christophe, Valérie
        'address' => [
            'line1' => '15 Rue des Côtes',
            'line2' => null,
            'postal_code' => '17670',
            'city' => 'La Couarde-sur-Mer',
            'label' => '15 Rue des Côtes, 17670 La Couarde-sur-Mer',
        ],
        // Coordonnées à obtenir via Nominatim si besoin
        'coords' => null,
    ],

    'chez-pepememe' => [
        'label' => 'Chez PépéMémé',
        'icon' => '🏡',
        'host_user_id' => 8, // Gilles
        'member_user_ids' => [8, 7], // Gilles, Claude
        'address' => [
            'line1' => 'rue de zeiting',
            'line2' => null,
            'postal_code' => '17220',
            'city' => 'La Jarne',
            'label' => 'rue de zeiting, 17220 La Jarne',
        ],
        'coords' => null,
    ],

    'chez-manon-mathieu' => [
        'label' => 'Chez Manon et Mathieu',
        'icon' => '🏠',
        'host_user_id' => 3, // Manon
        'member_user_ids' => [3, 9], // Manon, Mathieu
        'address' => [
            'line1' => '26 impasse de l\'escale',
            'line2' => null,
            'postal_code' => '17290',
            'city' => 'Le Thou',
            'label' => '26 impasse de l\'escale, 17290 Le Thou',
        ],
        'coords' => null,
    ],

    'chez-hugo-noelie' => [
        'label' => 'Chez Hugo et Noélie',
        'icon' => '🏘️',
        'host_user_id' => 4, // Hugo/Oly
        'member_user_ids' => [4, 6], // Hugo, Noélie
        'address' => [
            'line1' => '18c route d\'en bas',
            'line2' => null,
            'postal_code' => '17700',
            'city' => 'Surgères',
            'label' => '18c route d\'en bas, 17700 Surgères',
        ],
        'coords' => null,
    ],

    'chez-nico' => [
        'label' => 'Chez Nico',
        'icon' => '🏚️',
        'host_user_id' => 5, // Nicolas
        'member_user_ids' => [5, 11, 10], // Nicolas, Marcus, Charlotte
        'address' => [
            'line1' => '541 rue du grand four',
            'line2' => null,
            'postal_code' => '17450',
            'city' => 'Saint-Laurent-de-la-Prée',
            'label' => '541 rue du grand four, 17450 Saint-Laurent-de-la-Prée',
        ],
        'coords' => null,
    ],
];
