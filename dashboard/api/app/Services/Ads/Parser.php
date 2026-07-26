<?php

namespace App\Services\Ads;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Base class for the three platform parsers.
 *
 * COLUMN MAPS ARE DATA, NOT CODE PATHS
 * Each platform declares its columns as a list of aliases, because Google
 * renames columns between report types ("Cost" vs "Cost (USD)"), Meta suffixes
 * the currency into the header ("Amount spent (USD)"), and TikTok changes
 * "Clicks" to "Clicks (destination)". Mapping by NAME with aliases is the only
 * approach that survives; mapping by position silently loads cost into the
 * clicks column and the numbers still look like numbers.
 *
 * MONEY PARSING
 * Platform exports carry currency symbols, thousands separators and, in some
 * locales, a comma decimal point. "1,234.56" and "1.234,56" are the same
 * amount. Getting this wrong by a factor of a thousand is a real failure mode,
 * so it is handled once, here, and tested.
 */
abstract class Parser
{
    /** The platform key stored on ad_spend. */
    abstract public function platform(): string;

    /**
     * Column names that identify this platform's header row.
     *
     * @return list<string>
     */
    abstract public function fingerprint(): array;

    /**
     * Field name to the list of header aliases that may carry it.
     *
     * @return array<string, list<string>>
     */
    abstract public function columns(): array;

    /**
     * Turn a file into normalised spend rows.
     *
     * @return array{rows: list<array<string, mixed>>, skipped: int, notes: list<string>}
     */
    public function parse(string $path, ?string $from = null, ?string $to = null): array
    {
        $csv = new CsvReader($path, $this->fingerprint());
        $map = [];

        foreach ($this->columns() as $field => $aliases) {
            $map[$field] = $csv->column($aliases);
        }

        foreach (['campaign', 'spend'] as $required) {
            if ($map[$required] === null) {
                throw new RuntimeException(
                    "This {$this->platform()} export has no column for '{$required}'. "
                    .'Found: '.implode(', ', $csv->header).'. '
                    .'Re-export including '.implode(' or ', $this->columns()[$required]).'.'
                );
            }
        }

        [$preFrom, $preTo] = $csv->preambleDateRange();
        $from = $from ?: $preFrom;
        $to = $to ?: $preTo;

        $rows = [];
        $skipped = 0;
        $notes = [];

        foreach ($csv->rows as $row) {
            $get = fn (string $f) => $map[$f] !== null ? (string) ($row[$map[$f]] ?? '') : '';

            $campaign = trim($get('campaign'));

            if ($campaign === '') {
                $skipped++;

                continue;
            }

            // A per-row date column beats the report's overall range, because a
            // day-segmented export is the only way daily spend can be judged.
            $day = $map['day'] !== null ? $this->date($get('day')) : null;
            $start = $day ?: $from;
            $end = $day ?: $to;

            if (! $start || ! $end) {
                throw new RuntimeException(
                    'This export states no date range and has no date column, so its spend cannot be '
                    .'attributed to a period. Re-run with --from and --to, or export segmented by day.'
                );
            }

            $spend = $this->money($get('spend'));

            $rows[] = [
                'platform' => $this->platform(),
                'period_start' => $start,
                'period_end' => $end,
                'campaign' => mb_substr($campaign, 0, 191),
                'ad_group' => mb_substr(trim($get('ad_group')), 0, 191),
                // Lowercased and trimmed on the way in, so it joins against the
                // attribution table's utm_term without a case mismatch.
                'keyword' => mb_substr(\App\Models\Keyword::normalise($get('keyword')), 0, 191),
                'match_type' => $this->matchType($get('match_type')),
                'impressions' => $this->number($get('impressions')),
                'clicks' => $this->number($get('clicks')),
                'spend_usd' => $spend,
            ];
        }

        if ($map['keyword'] === null) {
            $notes[] = 'No keyword column in this export, so rows are campaign level only. '
                .'That is normal for Meta and TikTok, and means keyword verdicts cannot be produced from it.';
        }

        return ['rows' => $rows, 'skipped' => $skipped, 'notes' => $notes];
    }

    /**
     * Parse a money value that may carry symbols and either decimal convention.
     */
    protected function money(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '--' || $raw === '-') {
            return '0.00';
        }

        // Strip currency symbols, currency codes and spaces, keep digits and
        // separators.
        $clean = preg_replace('/[^0-9,.\-]/', '', $raw) ?? '';

        if ($clean === '' || $clean === '-') {
            return '0.00';
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Whichever separator comes last is the decimal point.
            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($lastComma !== false) {
            // "1,234" is thousands; "12,34" is a European decimal.
            $tail = strlen($clean) - $lastComma - 1;
            $clean = $tail === 3 ? str_replace(',', '', $clean) : str_replace(',', '.', $clean);
        }

        return number_format((float) $clean, 2, '.', '');
    }

    protected function number(string $raw): int
    {
        $clean = preg_replace('/[^0-9\-]/', '', trim($raw)) ?? '';

        return $clean === '' ? 0 : (int) $clean;
    }

    protected function date(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalise a match type to the single letter the attribution table uses.
     */
    protected function matchType(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return match (true) {
            $raw === '' => '',
            str_contains($raw, 'exact') => 'e',
            str_contains($raw, 'phrase') => 'p',
            str_contains($raw, 'broad') => 'b',
            in_array($raw, ['e', 'p', 'b', 'a'], true) => $raw,
            default => mb_substr($raw, 0, 4),
        };
    }
}
