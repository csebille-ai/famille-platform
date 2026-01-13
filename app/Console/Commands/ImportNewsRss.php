<?php

namespace App\Console\Commands;

use App\Models\NewsItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportNewsRss extends Command
{
    protected $signature = 'news:import-rss';

    protected $description = 'Import RSS/Atom feeds into news_items.';

    public function handle(): int
    {
        $sources = (array) config('news.sources', []);
        $sources = array_values(array_filter($sources, fn ($v) => is_array($v)));

        // Backward compatibility: accept config('news.feeds') as a flat list.
        if (count($sources) === 0) {
            $feeds = (array) config('news.feeds', []);
            $feeds = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $feeds), fn ($v) => $v !== ''));
            $sources = array_map(fn ($u) => ['url' => $u], $feeds);
        }

        // Normalize + keep enabled only (default enabled).
        $sources = array_values(array_filter(array_map(function (array $s) {
            $url = trim((string) ($s['url'] ?? ''));
            if ($url === '') {
                return null;
            }

            $enabled = $s['enabled'] ?? true;
            if (is_string($enabled)) {
                $enabled = !in_array(strtolower($enabled), ['0', 'false', 'off', 'no'], true);
            }

            return [
                'name' => isset($s['name']) ? trim((string) $s['name']) : null,
                'url' => $url,
                'tag' => isset($s['tag']) ? trim((string) $s['tag']) : null,
                'enabled' => (bool) $enabled,
            ];
        }, $sources), fn ($s) => is_array($s) && ($s['enabled'] ?? false) === true));

        if (count($sources) === 0) {
            $this->error('No feeds configured. Use NEWS_SOURCES_JSON or NEWS_FEEDS_JSON (or NEWS_FEEDS) in .env.');
            return self::FAILURE;
        }

        $timeout = (int) config('news.timeout', 10);
        if ($timeout < 1) {
            $timeout = 10;
        }

        $ua = (string) config('news.user_agent', 'FamillePlatform');
        $maxPerFeed = (int) config('news.max_items_per_feed', 40);
        if ($maxPerFeed < 1) {
            $maxPerFeed = 40;
        }

        $total = 0;
        $anyFeedOk = false;

        Log::info('news.import.start', [
            'sources_count' => count($sources),
        ]);
        foreach ($sources as $src) {
            $feedUrl = (string) $src['url'];
            $sourceName = (string) (($src['name'] ?? null) ?: $this->inferSourceFromUrl($feedUrl));
            $fixedTag = ($src['tag'] ?? null) !== null && (string) $src['tag'] !== '' ? (string) $src['tag'] : null;

            $this->info(sprintf('Fetching: %s%s', $feedUrl, $sourceName !== '' ? ' (' . $sourceName . ')' : ''));

            try {
                $resp = Http::retry(2, 500)
                    ->timeout($timeout)
                    ->withHeaders([
                        'User-Agent' => $ua,
                        'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.1',
                    ])
                    ->get($feedUrl);
            } catch (\Throwable $e) {
                $this->warn('HTTP error: ' . $e->getMessage());
                Log::warning('news.import.http_error', [
                    'url' => $feedUrl,
                    'source' => $sourceName,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (!$resp->ok()) {
                $this->warn('HTTP ' . $resp->status());
                Log::warning('news.import.http_status', [
                    'url' => $feedUrl,
                    'source' => $sourceName,
                    'status' => $resp->status(),
                ]);
                continue;
            }

            $xml = $this->parseXml((string) $resp->body());
            if (!$xml) {
                $this->warn('Invalid XML');
                Log::warning('news.import.invalid_xml', [
                    'url' => $feedUrl,
                    'source' => $sourceName,
                ]);
                continue;
            }

            $items = $this->extractItems($xml);
            if (count($items) === 0) {
                $this->warn('No items found');
                Log::warning('news.import.no_items', [
                    'url' => $feedUrl,
                    'source' => $sourceName,
                ]);
                continue;
            }

            $anyFeedOk = true;

            $imported = 0;
            foreach (array_slice($items, 0, $maxPerFeed) as $it) {
                $url = $it['url'] ?? '';
                $title = $it['title'] ?? '';
                if ($url === '' || $title === '') {
                    continue;
                }

                $publishedAt = $it['published_at'] ?? null;
                if (!$publishedAt instanceof Carbon) {
                    $publishedAt = now();
                }

                $excerpt = $it['excerpt'] ?? null;
                $imageUrl = $it['image_url'] ?? null;
                $tag = $fixedTag ?: $this->guessTag($title . ' ' . ((string) ($excerpt ?? '')));

                $urlHash = hash('sha256', $url);

                $model = NewsItem::query()->where('url_hash', $urlHash)->first();
                if (!$model) {
                    $model = new NewsItem();
                    $model->url_hash = $urlHash;
                    $model->url = $url;
                }

                $model->title = Str::of($title)->squish()->limit(500)->toString();
                $model->excerpt = $excerpt !== null ? Str::of($excerpt)->squish()->limit(220)->toString() : null;
                $model->image_url = $imageUrl !== null ? (string) $imageUrl : null;
                $model->source = $sourceName;
                $model->tag = $tag;
                $model->published_at = $publishedAt;
                $model->fetched_at = now();

                $model->save();
                $imported++;
            }

            $this->info(sprintf('Imported/updated: %d', $imported));
            $total += $imported;
        }

        $this->info(sprintf('Done. Total: %d', $total));

        Log::info('news.import.done', [
            'total' => $total,
            'any_feed_ok' => $anyFeedOk,
        ]);

        // If nothing succeeded at all, return failure so cron/scheduler can detect the problem.
        return $anyFeedOk ? self::SUCCESS : self::FAILURE;
    }

    private function inferSourceFromUrl(string $feedUrl): string
    {
        $host = parse_url($feedUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }
        return 'RSS';
    }

    private function parseXml(string $body): ?\SimpleXMLElement
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            if (!$xml) {
                return null;
            }
            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    /**
     * @return array<int,array{title:string,url:string,excerpt:?string,image_url:?string,published_at:?Carbon}>
     */
    private function extractItems(\SimpleXMLElement $xml): array
    {
        // RSS 2.0
        if (isset($xml->channel) && isset($xml->channel->item)) {
            return $this->extractRssItems($xml);
        }

        // Atom
        if (isset($xml->entry)) {
            return $this->extractAtomEntries($xml);
        }

        // Fallback: try xpath for both styles
        $rssItems = $xml->xpath('//channel/item') ?: [];
        if (count($rssItems) > 0) {
            return $this->extractRssItems($xml);
        }

        $atomEntries = $xml->xpath('//*[local-name()="entry"]') ?: [];
        if (count($atomEntries) > 0) {
            return $this->extractAtomEntries($xml);
        }

        return [];
    }

    /**
     * @return array<int,array{title:string,url:string,excerpt:?string,image_url:?string,published_at:?Carbon}>
     */
    private function extractRssItems(\SimpleXMLElement $xml): array
    {
        $items = [];

        foreach ($xml->channel->item as $item) {
            $title = $this->asString($item->title ?? null);
            $url = $this->asString($item->link ?? null);

            $desc = $this->asString($item->description ?? null);
            $contentEncoded = $this->extractContentEncoded($item);
            $html = $contentEncoded !== '' ? $contentEncoded : $desc;
            $excerpt = $this->makeExcerpt($html);

            $imageUrl = $this->extractImageUrlFromRssItem($item, $html);
            $publishedAt = $this->parseDate($this->asString($item->pubDate ?? null));

            $items[] = [
                'title' => $title,
                'url' => $url,
                'excerpt' => $excerpt,
                'image_url' => $imageUrl,
                'published_at' => $publishedAt,
            ];
        }

        return $items;
    }

    /**
     * @return array<int,array{title:string,url:string,excerpt:?string,image_url:?string,published_at:?Carbon}>
     */
    private function extractAtomEntries(\SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->entry as $entry) {
            $title = $this->asString($entry->title ?? null);
            $url = $this->extractAtomLink($entry);

            $summary = $this->asString($entry->summary ?? null);
            $content = $this->asString($entry->content ?? null);
            $html = $content !== '' ? $content : $summary;
            $excerpt = $this->makeExcerpt($html);

            $imageUrl = $this->extractImageUrlFromHtml($html);
            $publishedAt = $this->parseDate(
                $this->asString($entry->published ?? null) ?: $this->asString($entry->updated ?? null)
            );

            $items[] = [
                'title' => $title,
                'url' => $url,
                'excerpt' => $excerpt,
                'image_url' => $imageUrl,
                'published_at' => $publishedAt,
            ];
        }

        return $items;
    }

    private function extractAtomLink(\SimpleXMLElement $entry): string
    {
        if (!isset($entry->link)) {
            return '';
        }

        foreach ($entry->link as $link) {
            $attrs = $link->attributes();
            if (!$attrs) {
                continue;
            }
            $href = (string) ($attrs['href'] ?? '');
            $rel = (string) ($attrs['rel'] ?? '');

            if ($href === '') {
                continue;
            }
            if ($rel === '' || $rel === 'alternate') {
                return $href;
            }
        }

        $first = $entry->link[0]->attributes();
        return $first ? (string) ($first['href'] ?? '') : '';
    }

    private function extractContentEncoded(\SimpleXMLElement $item): string
    {
        $children = $item->children('content', true);
        if (isset($children->encoded)) {
            return (string) $children->encoded;
        }
        return '';
    }

    private function extractImageUrlFromRssItem(\SimpleXMLElement $item, string $html): ?string
    {
        if (isset($item->enclosure)) {
            $attrs = $item->enclosure->attributes();
            if ($attrs && isset($attrs['url'])) {
                $u = (string) $attrs['url'];
                if ($u !== '') {
                    return $u;
                }
            }
        }

        $media = $item->children('media', true);
        if (isset($media->content)) {
            $attrs = $media->content->attributes();
            if ($attrs && isset($attrs['url'])) {
                $u = (string) $attrs['url'];
                if ($u !== '') {
                    return $u;
                }
            }
        }

        if (isset($media->thumbnail)) {
            $attrs = $media->thumbnail->attributes();
            if ($attrs && isset($attrs['url'])) {
                $u = (string) $attrs['url'];
                if ($u !== '') {
                    return $u;
                }
            }
        }

        return $this->extractImageUrlFromHtml($html);
    }

    private function extractImageUrlFromHtml(string $html): ?string
    {
        if ($html === '') {
            return null;
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            $u = trim((string) ($m[1] ?? ''));
            if ($u !== '') {
                return $u;
            }
        }

        return null;
    }

    private function makeExcerpt(string $htmlOrText): ?string
    {
        $s = trim((string) $htmlOrText);
        if ($s === '') {
            return null;
        }

        $s = strip_tags($s);
        $s = Str::of($s)->squish()->toString();
        if ($s === '') {
            return null;
        }

        return Str::of($s)->limit(220)->toString();
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function asString($v): string
    {
        if ($v === null) {
            return '';
        }
        return trim((string) $v);
    }

    private function guessTag(string $text): ?string
    {
        $t = Str::of($text)->lower()->toString();

        $map = [
            'meteo' => ['météo', 'meteo', 'vigilance', 'tempête', 'orage', 'neige', 'canicule'],
            'travaux' => ['travaux', 'chantier', 'circulation', 'route barrée', 'déviation', 'deviation'],
            'securite' => ['gendarmerie', 'police', 'incendie', 'accident', 'alerte', 'cambriolage', 'vol'],
            'sport' => ['match', 'tournoi', 'club', 'football', 'basket', 'handball', 'rugby'],
            'culture' => ['concert', 'festival', 'expo', 'exposition', 'théâtre', 'theatre', 'cinéma', 'cinema'],
            'commune' => ['mairie', 'conseil municipal', 'arrêté', 'arrete', 'commune', 'municipal'],
        ];

        foreach ($map as $tag => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($t, Str::of($needle)->lower()->toString())) {
                    return $tag;
                }
            }
        }

        return null;
    }
}
