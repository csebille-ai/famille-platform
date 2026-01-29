<?php

return [

    // How many finished attempts (per user & quiz) we use to avoid repeating questions.
    // Set to 0 to disable.
    'avoid_repeat_last_attempts' => (int) env('QUIZ_AVOID_REPEAT_LAST_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Quiz Templates
    |--------------------------------------------------------------------------
    |
    | Templates for automatic quiz generation from Wikidata.
    | Each template defines:
    | - SPARQL query to fetch (subject, correct_answer) pairs
    | - Question text with placeholders
    | - Rules for generating distractor answers
    | - Quality filters
    |
    */

    'templates' => [

        'capitals' => [
            'name' => 'Capitales du monde',
            'category' => 'Géographie',
            'difficulty' => 'easy',
            
            // Question template with {subject} placeholder
            'question_template' => 'Quelle est la capitale de {subject} ?',
            
            // SPARQL query to fetch countries and their capitals
            // Returns: ?subjectQid ?subjectLabel ?answerQid ?answerLabel
            'sparql_query' => <<<'SPARQL'
SELECT DISTINCT ?subjectQid ?subjectLabel ?answerQid ?answerLabel WHERE {
  ?subjectQid wdt:P31 wd:Q3624078 .  # sovereign state
  ?subjectQid wdt:P36 ?answerQid .   # capital
  
  # Get French labels
  ?subjectQid rdfs:label ?subjectLabel . FILTER(LANG(?subjectLabel) = "fr")
  ?answerQid rdfs:label ?answerLabel . FILTER(LANG(?answerLabel) = "fr")
  
  # Quality filters
  FILTER NOT EXISTS { ?subjectQid wdt:P576 ?dissolved }  # not dissolved
  FILTER NOT EXISTS { ?subjectQid wdt:P582 ?endTime }    # not ended
}
LIMIT 500
SPARQL,
            
            // Distractor generation rules
            'distractor_query' => <<<'SPARQL'
SELECT DISTINCT ?qid ?label WHERE {
  ?qid wdt:P31 wd:Q5119 .  # instance of capital
  ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
}
LIMIT 1000
SPARQL,
            
            'distractor_count' => 3,
            
            // Quality constraints
            'filters' => [
                'require_french_label' => true,
                'reject_multi_value' => true,
                'reject_duplicates' => true,
                'min_label_length' => 2,
            ],
            
            // Generation settings
            'default_questions_count' => 200,
            'points_per_question' => 10,
        ],

                'country_currency' => [
                        'name' => 'Monnaies du monde',
                        'category' => 'Géographie',
                        'difficulty' => 'easy',

                        // Question template with {subject} placeholder
                        'question_template' => 'Quelle est la monnaie de {subject} ?',

                        // SPARQL query to fetch countries and their currencies
                        // Returns: ?subjectQid ?subjectLabel ?answerQid ?answerLabel
                        'sparql_query' => <<<'SPARQL'
SELECT DISTINCT ?subjectQid ?subjectLabel ?answerQid ?answerLabel WHERE {
    ?subjectQid wdt:P31 wd:Q3624078 .  # sovereign state
    ?subjectQid wdt:P38 ?answerQid .   # currency

    # Get French labels
    ?subjectQid rdfs:label ?subjectLabel . FILTER(LANG(?subjectLabel) = "fr")
    ?answerQid rdfs:label ?answerLabel . FILTER(LANG(?answerLabel) = "fr")

    # Quality filters
    FILTER NOT EXISTS { ?subjectQid wdt:P576 ?dissolved }  # not dissolved
    FILTER NOT EXISTS { ?subjectQid wdt:P582 ?endTime }    # not ended
}
LIMIT 500
SPARQL,

                        // Distractor pool: currencies
                        'distractor_query' => <<<'SPARQL'
SELECT DISTINCT ?qid ?label WHERE {
    ?qid wdt:P31 wd:Q8142 .  # instance of currency
    ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
}
LIMIT 1500
SPARQL,

                        'distractor_count' => 3,

                        'filters' => [
                                'require_french_label' => true,
                                'reject_multi_value' => true,
                                'reject_duplicates' => true,
                                'min_label_length' => 2,
                        ],

                        'default_questions_count' => 200,
                        'points_per_question' => 10,
                ],

                'country_flags' => [
                        'name' => 'Drapeaux du monde',
                        'category' => 'Géographie',
                        'difficulty' => 'easy',

                        // No subject placeholder needed here; the flag image is shown.
                        'question_template' => 'À quel pays appartient ce drapeau ?',

                        // Returns: ?subjectQid ?subjectLabel ?answerQid ?answerLabel ?imageUrl
                        'sparql_query' => <<<'SPARQL'
SELECT DISTINCT ?subjectQid ?subjectLabel ?answerQid ?answerLabel ?imageUrl WHERE {
    ?subjectQid wdt:P31 wd:Q3624078 .  # sovereign state
    ?subjectQid wdt:P41 ?imageUrl .    # flag image (commons file path)

    # Get French labels
    ?subjectQid rdfs:label ?subjectLabel . FILTER(LANG(?subjectLabel) = "fr")

    # For this template, the correct answer is the country itself
    BIND(?subjectQid AS ?answerQid)
    BIND(?subjectLabel AS ?answerLabel)

    # Quality filters
    FILTER NOT EXISTS { ?subjectQid wdt:P576 ?dissolved }
    FILTER NOT EXISTS { ?subjectQid wdt:P582 ?endTime }
}
LIMIT 700
SPARQL,

                        // Distractor pool: countries (sovereign states)
                        'distractor_query' => <<<'SPARQL'
SELECT DISTINCT ?qid ?label WHERE {
    ?qid wdt:P31 wd:Q3624078 .
    ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
    FILTER NOT EXISTS { ?qid wdt:P576 ?dissolved }
    FILTER NOT EXISTS { ?qid wdt:P582 ?endTime }
}
LIMIT 1200
SPARQL,

                        'distractor_count' => 3,

                        'filters' => [
                                'require_french_label' => true,
                                'reject_multi_value' => true,
                                'reject_duplicates' => true,
                                'require_image' => true,
                                'min_label_length' => 2,
                        ],

                        'default_questions_count' => 200,
                        'points_per_question' => 10,
                ],

                'works_authors' => [
                        'name' => 'Œuvres et auteurs',
                        'category' => 'Culture',
                        'difficulty' => 'medium',

                        'question_template' => 'Qui est l\'auteur de « {subject} » ?',

                        // This query can be heavier than simple geography templates.
                        'wikidata_timeout' => 90,

                        // Books (P50 author) + paintings (P170 creator).
                        // Returns: ?subjectQid ?subjectLabel ?answerQid ?answerLabel
                        'sparql_query' => <<<'SPARQL'
SELECT DISTINCT ?subjectQid ?subjectQidLabel ?answerQid ?answerQidLabel WHERE {
    {
        # Books
        ?subjectQid wdt:P31 wd:Q571 .
        ?subjectQid wdt:P50 ?answerQid .
    }
    UNION
    {
        # Paintings
        ?subjectQid wdt:P31 wd:Q3305213 .
        ?subjectQid wdt:P170 ?answerQid .
    }

    # Author/creator must be a human
    ?answerQid wdt:P31 wd:Q5 .

    # Labels in French via label service
    SERVICE wikibase:label { bd:serviceParam wikibase:language "fr". }
}
LIMIT 600
SPARQL,

                        // Distractor pool: humans who are authors/writers/painters
                        'distractor_query' => <<<'SPARQL'
SELECT DISTINCT ?qid ?label WHERE {
    ?qid wdt:P31 wd:Q5 .
    ?qid wdt:P106 ?occupation .
    VALUES ?occupation { wd:Q36180 wd:Q482980 wd:Q1028181 } # writer, author, painter
    ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
}
LIMIT 1500
SPARQL,

                        'distractor_count' => 3,

                        'filters' => [
                                'require_french_label' => true,
                                'reject_multi_value' => true,
                                'reject_duplicates' => true,
                                'min_label_length' => 2,
                        ],

                        'default_questions_count' => 200,
                        'points_per_question' => 10,
                ],

                'music_tracks_artists' => [
                        'name' => 'Chansons et artistes',
                        'category' => 'Culture',
                        'difficulty' => 'medium',

                        'question_template' => 'Qui interprète « {subject} » ?',

                        // Music queries can be heavy; keep stable on shared hosting.
                        'wikidata_timeout' => 60,

                        // Returns: ?subjectQid ?subjectQidLabel ?answerQid ?answerQidLabel
                        'sparql_query' => <<<'SPARQL'
SELECT DISTINCT ?subjectQid ?subjectQidLabel ?answerQid ?answerQidLabel WHERE {
    ?subjectQid wdt:P31/wdt:P279* wd:Q7366 .   # song
    ?subjectQid wdt:P175 ?answerQid .          # performer

    # Performer is human or musical group
    VALUES ?t { wd:Q5 wd:Q215380 }
    ?answerQid wdt:P31 ?t .

    SERVICE wikibase:label { bd:serviceParam wikibase:language "fr". }
}
LIMIT 700
SPARQL,

                        // Distractors: humans in music occupations + musical groups
                        'distractor_query' => <<<'SPARQL'
SELECT DISTINCT ?qid ?label WHERE {
    {
        ?qid wdt:P31 wd:Q5 .
        ?qid wdt:P106 ?occupation .
        VALUES ?occupation {
            wd:Q177220    # singer
            wd:Q639669    # musician
            wd:Q2252262   # rapper
            wd:Q753110    # songwriter
        }
        ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
    }
    UNION
    {
        ?qid wdt:P31 wd:Q215380 .  # musical group
        ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
    }
}
LIMIT 2000
SPARQL,

                        'distractor_count' => 3,
                        'filters' => [
                                'require_french_label' => true,
                                'reject_multi_value' => true,
                                'reject_duplicates' => true,
                                'min_label_length' => 2,
                        ],

                        'default_questions_count' => 200,
                        'points_per_question' => 10,
                ],

                'music_albums_artists' => [
                        'name' => 'Albums et artistes',
                        'category' => 'Culture',
                        'difficulty' => 'medium',

                        'question_template' => 'Qui est l\'artiste de l\'album « {subject} » ?',

                        'wikidata_timeout' => 60,

                        // Returns: ?subjectQid ?subjectQidLabel ?answerQid ?answerQidLabel
                        'sparql_query' => <<<'SPARQL'
SELECT DISTINCT ?subjectQid ?subjectQidLabel ?answerQid ?answerQidLabel WHERE {
    ?subjectQid wdt:P31/wdt:P279* wd:Q482994 . # album
    ?subjectQid wdt:P175 ?answerQid .          # performer

    # Performer is human or musical group
    VALUES ?t { wd:Q5 wd:Q215380 }
    ?answerQid wdt:P31 ?t .

    SERVICE wikibase:label { bd:serviceParam wikibase:language "fr". }
}
LIMIT 700
SPARQL,

                        // Distractors: reuse same pool
                        'distractor_query' => <<<'SPARQL'
SELECT DISTINCT ?qid ?label WHERE {
    {
        ?qid wdt:P31 wd:Q5 .
        ?qid wdt:P106 ?occupation .
        VALUES ?occupation {
            wd:Q177220    # singer
            wd:Q639669    # musician
            wd:Q2252262   # rapper
            wd:Q753110    # songwriter
        }
        ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
    }
    UNION
    {
        ?qid wdt:P31 wd:Q215380 .  # musical group
        ?qid rdfs:label ?label . FILTER(LANG(?label) = "fr")
    }
}
LIMIT 2000
SPARQL,

                        'distractor_count' => 3,
                        'filters' => [
                                'require_french_label' => true,
                                'reject_multi_value' => true,
                                'reject_duplicates' => true,
                                'min_label_length' => 2,
                        ],

                        'default_questions_count' => 200,
                        'points_per_question' => 10,
                ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Wikidata Query Service Configuration
    |--------------------------------------------------------------------------
    */

    'wikidata' => [
        'endpoint' => 'https://query.wikidata.org/sparql',
        'user_agent' => 'FamillePlatform/1.0 (https://famille.example.com; contact@example.com)',
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 2, // seconds
        'backoff_multiplier' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quiz Settings
    |--------------------------------------------------------------------------
    */

    'default_category' => 'Général',
    'default_difficulty' => 'medium',
    'difficulties' => ['easy', 'medium', 'hard'],
    'categories' => [
        'Géographie',
        'Histoire',
        'Sciences',
        'Culture',
        'Sport',
        'Général',
    ],

    /*
    |--------------------------------------------------------------------------
    | Leaderboard Configuration
    |--------------------------------------------------------------------------
    */

    'leaderboard' => [
        'top_limit' => 50,
        'cache_ttl' => 300, // 5 minutes
    ],

];
