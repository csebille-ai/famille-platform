<?php

namespace App\Services\Uploads;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class R2UploadService
{
    private string $accountId;
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $bucket;
    private string $region;
    private string $endpoint;
    private string $publicBaseUrl;

    public function __construct(
        ?string $accountId = null,
        ?string $accessKeyId = null,
        ?string $secretAccessKey = null,
        ?string $bucket = null,
        string $region = 'auto',
        ?string $endpoint = null,
        ?string $publicBaseUrl = null,
    ) {
        $this->accountId = (string) ($accountId ?? env('R2_ACCOUNT_ID', ''));
        $this->accessKeyId = (string) ($accessKeyId ?? env('R2_ACCESS_KEY_ID', ''));
        $this->secretAccessKey = (string) ($secretAccessKey ?? env('R2_SECRET_ACCESS_KEY', ''));
        $this->bucket = (string) ($bucket ?? env('R2_BUCKET', ''));
        $this->region = (string) env('R2_REGION', $region);
        $this->endpoint = (string) ($endpoint ?? env('R2_ENDPOINT', ''));
        $this->publicBaseUrl = (string) ($publicBaseUrl ?? config('uploads.r2_public_base_url'));
    }

    public function endpoint(): string
    {
        $endpoint = trim((string) $this->endpoint);
        if ($endpoint === '') {
            $accountId = trim((string) $this->accountId);
            if ($accountId !== '') {
                $endpoint = 'https://' . $accountId . '.r2.cloudflarestorage.com';
            }
        }
        return rtrim($endpoint, '/');
    }

    public function bucket(): string
    {
        return (string) $this->bucket;
    }

    public function publicUrlForKey(string $key): ?string
    {
        $base = trim((string) $this->publicBaseUrl);
        if ($base === '') {
            return null;
        }
        return rtrim($base, '/') . '/' . ltrim($key, '/');
    }

    public function buildObjectKey(string $context, string $kind, string $filename): string
    {
        $context = strtolower(trim($context));
        $kind = strtolower(trim($kind));
        if (!in_array($context, ['media', 'chat'], true)) {
            $context = 'media';
        }
        if (!in_array($kind, ['photo', 'video'], true)) {
            $kind = 'media';
        }

        $safe = trim($filename);
        if ($safe === '') {
            $safe = 'file';
        }

        // Keep filenames reasonably safe.
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $safe) ?? 'file';
        $safe = trim($safe, '-');
        $safe = substr($safe, 0, 80);
        if ($safe === '') {
            $safe = 'file';
        }

        $ym = now()->format('Y/m');
        $uuid = (string) Str::uuid();

        return "uploads/{$context}/{$kind}/{$ym}/{$uuid}-{$safe}";
    }

    /**
     * Presigned PUT URL for uploading a whole object.
     */
    public function presignPutObject(string $key, string $mime, int $expiresSeconds = 900): string
    {
        $expiresSeconds = max(60, min(7 * 24 * 3600, (int) $expiresSeconds));
        $path = $this->pathForKey($key);
        return $this->presign('PUT', $path, [], $expiresSeconds);
    }

    /**
     * Initiate multipart upload and return UploadId.
     */
    public function createMultipartUpload(string $key, string $mime): string
    {
        $path = $this->pathForKey($key);
        $query = ['uploads' => ''];

        $url = $this->endpoint() . $path . '?' . $this->buildCanonicalQueryString($query);
        $amzDate = gmdate('Ymd\THis\Z');
        $payload = '';
        $payloadHash = hash('sha256', $payload);

        $headers = $this->signHeaders('POST', $path, $query, [
            'host' => $this->host(),
            'content-type' => $mime,
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ], $payloadHash);

        $res = Http::withHeaders($headers)
            ->withBody($payload, $mime)
            ->post($url);

        if (!$res->successful()) {
            throw new \RuntimeException('Multipart init failed (' . $res->status() . ')');
        }

        $xml = (string) $res->body();
        $sx = @simplexml_load_string($xml);
        if (!$sx) {
            throw new \RuntimeException('Multipart init invalid XML response');
        }

        $uploadId = (string) ($sx->UploadId ?? '');
        if ($uploadId === '') {
            throw new \RuntimeException('Multipart init missing UploadId');
        }

        return $uploadId;
    }

    /**
     * Presigned PUT URL for a single part.
     */
    public function presignUploadPart(string $key, string $uploadId, int $partNumber, int $expiresSeconds = 7200): string
    {
        $expiresSeconds = max(300, min(7 * 24 * 3600, (int) $expiresSeconds));
        $path = $this->pathForKey($key);
        $query = [
            'partNumber' => (string) $partNumber,
            'uploadId' => $uploadId,
        ];
        return $this->presign('PUT', $path, $query, $expiresSeconds);
    }

    /**
     * Complete multipart upload.
     *
     * @param array<int, array{PartNumber:int, ETag:string}> $parts
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): void
    {
        $path = $this->pathForKey($key);
        $query = ['uploadId' => $uploadId];

        $xml = $this->buildCompleteMultipartXml($parts);
        $payloadHash = hash('sha256', $xml);
        $amzDate = gmdate('Ymd\THis\Z');

        $url = $this->endpoint() . $path . '?' . $this->buildCanonicalQueryString($query);
        $headers = $this->signHeaders('POST', $path, $query, [
            'host' => $this->host(),
            'content-type' => 'application/xml',
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ], $payloadHash);

        $res = Http::withHeaders($headers)
            ->withBody($xml, 'application/xml')
            ->post($url);

        if (!$res->successful()) {
            throw new \RuntimeException('Multipart complete failed (' . $res->status() . ')');
        }
    }

    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        $path = $this->pathForKey($key);
        $query = ['uploadId' => $uploadId];

        $amzDate = gmdate('Ymd\THis\Z');
        $payloadHash = hash('sha256', '');

        $url = $this->endpoint() . $path . '?' . $this->buildCanonicalQueryString($query);
        $headers = $this->signHeaders('DELETE', $path, $query, [
            'host' => $this->host(),
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ], $payloadHash);

        $res = Http::withHeaders($headers)->send('DELETE', $url);

        if ($res->successful() || $res->status() === 404) {
            return;
        }

        throw new \RuntimeException('Abort multipart failed (' . $res->status() . ')');
    }

    public function deleteObject(string $key): void
    {
        $path = $this->pathForKey($key);
        $query = [];
        $amzDate = gmdate('Ymd\THis\Z');
        $payloadHash = hash('sha256', '');

        $url = $this->endpoint() . $path;
        $headers = $this->signHeaders('DELETE', $path, $query, [
            'host' => $this->host(),
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ], $payloadHash);

        $res = Http::withHeaders($headers)->send('DELETE', $url);

        if ($res->successful() || $res->status() === 404) {
            return;
        }

        throw new \RuntimeException('Delete failed (' . $res->status() . ')');
    }

    private function host(): string
    {
        $u = parse_url($this->endpoint());
        return (string) ($u['host'] ?? '');
    }

    private function pathForKey(string $key): string
    {
        $bucket = $this->bucket();
        $bucket = trim($bucket);
        $key = ltrim($key, '/');

        $encodedBucket = rawurlencode($bucket);
        $encodedKey = $this->encodePath($key);

        return '/' . $encodedBucket . '/' . $encodedKey;
    }

    private function encodePath(string $path): string
    {
        $parts = array_map('rawurlencode', explode('/', $path));
        return implode('/', $parts);
    }

    /**
     * @param array<string,string> $query
     */
    private function presign(string $method, string $canonicalUri, array $query, int $expiresSeconds): string
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $scope = $dateStamp . '/' . $this->region . '/s3/aws4_request';

        $query = array_merge($query, [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKeyId . '/' . $scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ]);

        $canonicalQuery = $this->buildCanonicalQueryString($query);
        $canonicalHeaders = 'host:' . $this->host() . "\n";
        $signedHeaders = 'host';
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = strtoupper($method) . "\n" .
            $canonicalUri . "\n" .
            $canonicalQuery . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeaders . "\n" .
            $payloadHash;

        $stringToSign = "AWS4-HMAC-SHA256\n" .
            $amzDate . "\n" .
            $scope . "\n" .
            hash('sha256', $canonicalRequest);

        $signature = $this->sign($dateStamp, $stringToSign);

        $finalQuery = $canonicalQuery . '&X-Amz-Signature=' . rawurlencode($signature);
        return $this->endpoint() . $canonicalUri . '?' . $finalQuery;
    }

    /**
     * @param array<string,string> $query
     * @param array<string,string> $headersLower
     * @return array<string,string>
     */
    private function signHeaders(string $method, string $canonicalUri, array $query, array $headersLower, string $payloadHash): array
    {
        $amzDate = (string) ($headersLower['x-amz-date'] ?? gmdate('Ymd\THis\Z'));
        $dateStamp = substr($amzDate, 0, 8);
        $scope = $dateStamp . '/' . $this->region . '/s3/aws4_request';

        ksort($headersLower);

        $canonicalHeaders = '';
        $signedHeadersParts = [];
        foreach ($headersLower as $k => $v) {
            $k2 = strtolower(trim($k));
            $v2 = preg_replace('/\s+/', ' ', trim((string) $v)) ?? trim((string) $v);
            $canonicalHeaders .= $k2 . ':' . $v2 . "\n";
            $signedHeadersParts[] = $k2;
        }
        $signedHeaders = implode(';', $signedHeadersParts);

        $canonicalQuery = $this->buildCanonicalQueryString($query);

        $canonicalRequest = strtoupper($method) . "\n" .
            $canonicalUri . "\n" .
            $canonicalQuery . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeaders . "\n" .
            $payloadHash;

        $stringToSign = "AWS4-HMAC-SHA256\n" .
            $amzDate . "\n" .
            $scope . "\n" .
            hash('sha256', $canonicalRequest);

        $signature = $this->sign($dateStamp, $stringToSign);

        $authorization = 'AWS4-HMAC-SHA256 ' .
            'Credential=' . $this->accessKeyId . '/' . $scope . ', ' .
            'SignedHeaders=' . $signedHeaders . ', ' .
            'Signature=' . $signature;

        $out = [];
        foreach ($headersLower as $k => $v) {
            $out[$this->normalizeHeaderKey($k)] = (string) $v;
        }
        $out['Authorization'] = $authorization;

        return $out;
    }

    private function normalizeHeaderKey(string $k): string
    {
        $k = strtolower($k);
        $parts = explode('-', $k);
        $parts = array_map(fn ($p) => ucfirst($p), $parts);
        return implode('-', $parts);
    }

    private function sign(string $dateStamp, string $stringToSign): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return hash_hmac('sha256', $stringToSign, $kSigning);
    }

    /**
     * @param array<string,string> $query
     */
    private function buildCanonicalQueryString(array $query): string
    {
        $pairs = [];
        ksort($query);
        foreach ($query as $k => $v) {
            $kEnc = rawurlencode((string) $k);
            $vEnc = rawurlencode((string) $v);
            $pairs[] = $kEnc . '=' . $vEnc;
        }
        return implode('&', $pairs);
    }

    /**
     * @param array<int, array{PartNumber:int, ETag:string}> $parts
     */
    private function buildCompleteMultipartXml(array $parts): string
    {
        usort($parts, fn ($a, $b) => ((int) $a['PartNumber']) <=> ((int) $b['PartNumber']));

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<CompleteMultipartUpload>";
        foreach ($parts as $p) {
            $pn = (int) $p['PartNumber'];
            $etag = trim((string) $p['ETag']);
            // ETag may include quotes; S3 expects quoted ETag in XML.
            if ($etag !== '' && $etag[0] !== '"') {
                $etag = '"' . $etag . '"';
            }
            $xml .= "<Part><PartNumber>{$pn}</PartNumber><ETag>{$etag}</ETag></Part>";
        }
        $xml .= "</CompleteMultipartUpload>";
        return $xml;
    }
}
