<?php

namespace App\Services\Ads;

use App\Models\AdSpend;
use App\Models\Keyword;
use App\Services\Ads\Parsers\GoogleAdsParser;
use App\Services\Ads\Parsers\MetaParser;
use App\Services\Ads\Parsers\TikTokParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports a platform export into ad_spend, idempotently.
 *
 * The one rule that matters: importing the same file twice must not change the
 * total. Ad spend is half of every profit number in this system, so a double
 * count does not just inflate one figure, it makes every verdict downstream
 * wrong in the direction that loses money, because a keyword that looks twice
 * as expensive gets killed while it was actually profitable.
 *
 * Defended by the natural key on ad_spend plus a content-hash guarded upsert,
 * the same pattern as the WooCommerce connector: a re-import over unchanged
 * data performs zero writes, not a no-op UPDATE.
 */
class SpendImporter
{
    /** @return list<Parser> */
    public static function parsers(): array
    {
        return [new GoogleAdsParser, new MetaParser, new TikTokParser];
    }

    public static function parserFor(string $platform): Parser
    {
        foreach (self::parsers() as $parser) {
            if ($parser->platform() === $platform) {
                return $parser;
            }
        }

        throw new RuntimeException("Unknown platform '{$platform}'. Expected google, meta or tiktok.");
    }

    /**
     * Guess the platform from the file's own header row.
     *
     * Better than trusting a filename, which is the first thing a human gets
     * wrong when they have three exports open at once.
     */
    public static function detect(string $path): ?Parser
    {
        $best = null;
        $bestHits = 0;

        foreach (self::parsers() as $parser) {
            try {
                $csv = new CsvReader($path, $parser->fingerprint());
            } catch (RuntimeException) {
                continue;
            }

            $hits = count(array_intersect($parser->fingerprint(), $csv->header));

            if ($hits > $bestHits) {
                $bestHits = $hits;
                $best = $parser;
            }
        }

        return $best;
    }

    /**
     * @return array{platform:string, rows:int, inserted:int, updated:int, unchanged:int,
     *               skipped:int, file_total:string, stored_total:string, notes:list<string>}
     */
    public function import(string $path, ?string $platform = null, ?string $from = null, ?string $to = null): array
    {
        $parser = $platform ? self::parserFor($platform) : self::detect($path);

        if (! $parser) {
            throw new RuntimeException(
                'Could not tell which platform this export came from. Pass --platform=google, meta or tiktok.'
            );
        }

        $parsed = $parser->parse($path, $from, $to);
        $rows = $parsed['rows'];

        if (! $rows) {
            throw new RuntimeException('The export parsed cleanly but contained no spend rows.');
        }

        // The total in the FILE, computed before anything touches the database.
        // The acceptance test compares this against what was stored, to the
        // cent, and that comparison is only meaningful if it is measured here.
        $fileTotal = '0.00';
        foreach ($rows as $row) {
            $fileTotal = number_format((float) $fileTotal + (float) $row['spend_usd'], 2, '.', '');
        }

        $now = CarbonImmutable::now('UTC');
        $file = basename($path);
        $stats = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0];

        DB::transaction(function () use ($rows, $now, $file, &$stats) {
            foreach ($rows as $row) {
                /*
                 * The two date columns are matched with whereDate, not a plain
                 * equality. period_start is cast to a date, so the stored value
                 * carries a time component while the parsed value is a bare
                 * YYYY-MM-DD string. A plain where() finds nothing, the code
                 * decides the row is new, and the insert dies on the unique
                 * constraint it was supposed to be respecting. The failure mode
                 * is a hard error on every second import, which is at least
                 * loud, but the fix belongs at the lookup.
                 */
                $existing = AdSpend::query()
                    ->where('platform', $row['platform'])
                    ->where('campaign', $row['campaign'])
                    ->where('ad_group', $row['ad_group'])
                    ->where('keyword', $row['keyword'])
                    ->whereDate('period_start', $row['period_start'])
                    ->whereDate('period_end', $row['period_end'])
                    ->first();

                if ($existing) {
                    $same = (string) $existing->spend_usd === $row['spend_usd']
                        && (int) $existing->clicks === $row['clicks']
                        && (int) $existing->impressions === $row['impressions'];

                    if ($same) {
                        $stats['unchanged']++;

                        continue;
                    }

                    // A changed figure for a period already imported is a
                    // RESTATEMENT, not an addition. Google revises recent days
                    // for invalid clicks. Overwrite, never accumulate.
                    $existing->forceFill([
                        'match_type' => $row['match_type'],
                        'impressions' => $row['impressions'],
                        'clicks' => $row['clicks'],
                        'spend_usd' => $row['spend_usd'],
                        'source_file' => $file,
                        'imported_at' => $now,
                    ])->save();

                    $stats['updated']++;

                    continue;
                }

                AdSpend::create($row + [
                    'currency' => 'USD',
                    'source_file' => $file,
                    'imported_at' => $now,
                ]);

                $stats['inserted']++;
            }
        });

        $this->refreshKeywordRegistry($parser->platform());

        $storedTotal = number_format(
            (float) AdSpend::where('platform', $parser->platform())->sum('spend_usd'),
            2, '.', ''
        );

        return [
            'platform' => $parser->platform(),
            'rows' => count($rows),
            'inserted' => $stats['inserted'],
            'updated' => $stats['updated'],
            'unchanged' => $stats['unchanged'],
            'skipped' => $parsed['skipped'],
            'file_total' => $fileTotal,
            'stored_total' => $storedTotal,
            'notes' => $parsed['notes'],
        ];
    }

    /**
     * Keep the keyword registry in step with what has been imported.
     *
     * Lifetime spend and clicks are RECOMPUTED from ad_spend rather than
     * incremented, so a re-import or a restatement can never drift the totals.
     * Incrementing is how a registry slowly stops matching its own source data.
     */
    public function refreshKeywordRegistry(string $platform): void
    {
        if ($platform !== 'google') {
            return;   // Only search has keywords to register.
        }

        $totals = AdSpend::query()
            ->selectRaw('keyword, match_type, ad_group, campaign,
                SUM(spend_usd) AS spend, SUM(clicks) AS clicks,
                MIN(period_start) AS first_seen, MAX(period_end) AS last_seen')
            ->where('platform', 'google')
            ->where('keyword', '<>', '')
            ->groupBy('keyword', 'match_type', 'ad_group', 'campaign')
            ->get();

        foreach ($totals as $t) {
            Keyword::updateOrCreate(
                [
                    'keyword' => $t->keyword,
                    'match_type' => $t->match_type,
                    'ad_group' => $t->ad_group,
                    'campaign' => $t->campaign,
                ],
                [
                    'first_seen' => $t->first_seen,
                    'last_seen' => $t->last_seen,
                    'lifetime_spend_usd' => number_format((float) $t->spend, 2, '.', ''),
                    'lifetime_clicks' => (int) $t->clicks,
                ]
            );
        }
    }
}
