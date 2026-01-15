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

$fetchArticleImagesRaw = strtolower(trim((string) env('NEWS_FETCH_ARTICLE_IMAGES', '1')));
$fetchArticleImages = !in_array($fetchArticleImagesRaw, ['0', 'false', 'off', 'no'], true);

$fetchArticleImagesMaxPerFeed = (int) env('NEWS_FETCH_ARTICLE_IMAGES_MAX_PER_FEED', 8);
if ($fetchArticleImagesMaxPerFeed < 0) {
    $fetchArticleImagesMaxPerFeed = 0;
}

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

    // Some feeds (e.g. Sud Ouest) do not provide image URLs in RSS. When enabled,
    // the importer will fetch the article HTML for a few new items per feed to
    // extract an image from og:image/twitter:image.
    'fetch_article_images' => $fetchArticleImages,
    'fetch_article_images_max_per_feed' => $fetchArticleImagesMaxPerFeed,

    // Safety limit per feed per run.
    'max_items_per_feed' => (int) env('NEWS_MAX_ITEMS_PER_FEED', 40),
];
