<?php

$parseJsonArray = function (?string $raw): array {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    return $data;
};

$feedsFromCsv = array_values(array_filter(array_map('trim', explode(',', (string) env('NEWS_FEEDS', '')))));
$feedsFromJson = $parseJsonArray(env('NEWS_FEEDS_JSON'));
$sourcesFromJson = $parseJsonArray(env('NEWS_SOURCES_JSON'));

// Default sources (can be overridden by env JSON).
$defaultSources = [];

if (count($sourcesFromJson) > 0) {
    $defaultSources = $sourcesFromJson;
} elseif (count($feedsFromJson) > 0) {
    $defaultSources = array_map(fn ($url) => ['url' => (string) $url], $feedsFromJson);
} elseif (count($feedsFromCsv) > 0) {
    $defaultSources = array_map(fn ($url) => ['url' => (string) $url], $feedsFromCsv);
}

return [
    // Preferred: define sources with metadata.
    // You can set them via NEWS_SOURCES_JSON=[{"name":"Pref","url":"...","tag":"securite","enabled":true}, ...]
    // or keep them in this file.
    'sources' => $defaultSources,

    // Optional: legacy flat feed list (still supported through NEWS_FEEDS / NEWS_FEEDS_JSON).
    'feeds' => $feedsFromCsv,

    'user_agent' => (string) env('NEWS_USER_AGENT', 'FamillePlatform/1.0'),
    'timeout' => (int) env('NEWS_HTTP_TIMEOUT', 10),

    // Safety limit per feed per run.
    'max_items_per_feed' => (int) env('NEWS_MAX_ITEMS_PER_FEED', 40),
];
