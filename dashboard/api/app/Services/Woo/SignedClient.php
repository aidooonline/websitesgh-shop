<?php

namespace App\Services\Woo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The shop side of the connector: one signed GET against wghs/v1/export.
 *
 * The signature must be byte-identical to what inc/dashboard-export.php
 * computes. Both sides build the same canonical string, in the same order,
 * with the same encoding. Any drift shows up immediately as a 401 rather than
 * as quietly wrong data, which is the point of signing rather than using a
 * bearer token in a query string.
 */
class SignedClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $path,
        private readonly string $secret,
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
    ) {
    }

    public static function fromConfig(): self
    {
        $secret = (string) config('wgh.shop.secret');

        if ($secret === '') {
            throw new RuntimeException(
                'WGH_SHOP_SECRET is empty. Generate it on the shop at Tools > WGH Dashboard Access, then put it in .env.'
            );
        }

        return new self(
            baseUrl: (string) config('wgh.shop.base_url'),
            path: (string) config('wgh.shop.export_path'),
            secret: $secret,
            timeout: (int) config('wgh.shop.timeout'),
            retries: (int) config('wgh.shop.retries'),
        );
    }

    /**
     * The canonical string both sides sign.
     *
     * Query arguments are sorted by key so that a different parameter order is
     * not a different signature, and rawurlencoded so a space or a plus in a
     * cursor cannot change the meaning of the string.
     *
     * @param  array<string, scalar>  $query
     */
    public static function canonical(string $method, string $route, array $query, string $timestamp, string $nonce): string
    {
        unset($query['signature'], $query['timestamp'], $query['nonce'], $query['_locale']);
        ksort($query);

        $pairs = [];
        foreach ($query as $k => $v) {
            $pairs[] = rawurlencode((string) $k).'='.rawurlencode((string) $v);
        }

        return strtoupper($method)."\n".$route."\n".implode('&', $pairs)."\n".$timestamp."\n".$nonce;
    }

    /**
     * Fetch one page of the export.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function fetch(array $query): array
    {
        $timestamp = (string) time();
        $nonce = Str::random(32);

        // The route the shop signs is the REST route, not the full URL path.
        // WordPress may serve it at /wp-json/... or at /?rest_route=..., and
        // the signature must not depend on which.
        $signature = hash_hmac(
            'sha256',
            self::canonical('GET', '/wghs/v1/export', $query, $timestamp, $nonce),
            $this->secret
        );

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retries, 500, throw: false)
                ->withHeaders([
                    'X-WGHS-Timestamp' => $timestamp,
                    'X-WGHS-Nonce' => $nonce,
                    'X-WGHS-Signature' => $signature,
                    'Accept' => 'application/json',
                    'User-Agent' => 'WGH-Intelligence/1.0 (sprint-1 connector)',
                ])
                ->get($this->baseUrl.$this->path, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the shop: '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 401) {
            throw new RuntimeException(
                'The shop rejected the signature (401). Either WGH_SHOP_SECRET does not match the '
                .'secret at Tools > WGH Dashboard Access, or this server\'s clock is more than five '
                .'minutes out. Check the clock first; it is the more common cause.'
            );
        }

        if ($response->status() === 503) {
            throw new RuntimeException('The shop has no dashboard secret set yet. Generate one at Tools > WGH Dashboard Access.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Shop export failed with HTTP '.$response->status().': '.Str::limit($response->body(), 300));
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            throw new RuntimeException('Shop export returned an unexpected body: '.Str::limit($response->body(), 300));
        }

        return $payload;
    }
}
