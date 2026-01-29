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

                        // Books (P50 author) + paintings (P170 creator).
                        // Returns: ?subjectQid ?subjectLabel ?answerQid ?answerLabel
                        'sparql_query' => <<<'SPARQL'
SELECT DISTINCT ?subjectQid ?subjectLabel ?answerQid ?answerLabel WHERE {
    {
        # Books
        ?subjectQid wdt:P31/wdt:P279* wd:Q571 .
        ?subjectQid wdt:P50 ?answerQid .
        FILTER NOT EXISTS {
            ?subjectQid wdt:P50 ?otherAuthor .
            FILTER(?otherAuthor != ?answerQid)
        }
    }
    UNION
    {
        # Paintings
        ?subjectQid wdt:P31/wdt:P279* wd:Q3305213 .
        ?subjectQid wdt:P170 ?answerQid .
        FILTER NOT EXISTS {
            ?subjectQid wdt:P170 ?otherCreator .
            FILTER(?otherCreator != ?answerQid)
        }
    }

    # Author/creator must be a human
    ?answerQid wdt:P31 wd:Q5 .

    # Get French labels
    ?subjectQid rdfs:label ?subjectLabel . FILTER(LANG(?subjectLabel) = "fr")
    ?answerQid rdfs:label ?answerLabel . FILTER(LANG(?answerLabel) = "fr")
}
LIMIT 800
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
