<?php

namespace App\Services\Ads;

use RuntimeException;

/**
 * Reads an ad platform export into labelled rows.
 *
 * Ad platform exports are not clean CSVs and pretending they are is how spend
 * numbers end up wrong. Three things this handles that a naive fgetcsv does
 * not:
 *
 * 1. PREAMBLE. Google Ads exports begin with a report title and a date range
 *    before the header row. The header is FOUND by looking for a row that
 *    contains recognisable column names, never assumed to be line 1 and never
 *    read by position. Google renames and reorders columns between report
 *    types; position-based parsing silently maps cost into the clicks column.
 *
 * 2. TOTAL ROWS. Google appends "Total: campaigns" style rows at the end.
 *    Importing those double counts every cent of spend, and the resulting
 *    number looks plausible, which is what makes it dangerous.
 *
 * 3. ENCODING. UTF-8 BOMs, and semicolon delimiters from European locale
 *    exports.
 */
class CsvReader
{
    /** @var list<string> */
    public array $header = [];

    /** @var list<array<string, string>> */
    public array $rows = [];

    /** @var list<string> Preamble lines that appeared before the header. */
    public array $preamble = [];

    /**
     * @param  list<string>  $expect  Lower-cased column names that identify the header row.
     */
    public function __construct(string $path, array $expect)
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}");
        }

        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;   // UTF-8 BOM
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $lines = array_values(array_filter(explode("\n", $raw), fn ($l) => trim($l) !== ''));

        if (! $lines) {
            throw new RuntimeException('The file is empty.');
        }

        $delimiter = $this->sniffDelimiter($lines);
        $headerAt = null;

        foreach ($lines as $i => $line) {
            $cells = array_map(
                fn ($c) => mb_strtolower(trim($c, " \t\"'")),
                str_getcsv($line, $delimiter, '"', '\\')
            );

            $hits = count(array_intersect($expect, $cells));

            // Two matches, not one: a preamble line can accidentally contain a
            // single word like "campaign".
            if ($hits >= 2) {
                $headerAt = $i;
                $this->header = array_map(
                    fn ($c) => $this->canonical($c),
                    str_getcsv($line, $delimiter, '"', '\\')
                );
                break;
            }

            $this->preamble[] = $line;
        }

        if ($headerAt === null) {
            throw new RuntimeException(
                'Could not find a header row. Looked for at least two of: '.implode(', ', $expect)
                .'. Export the report with column headers included, or check it is the right platform.'
            );
        }

        $width = count($this->header);

        foreach (array_slice($lines, $headerAt + 1) as $line) {
            $cells = str_getcsv($line, $delimiter, '"', '\\');

            if ($this->isTotalRow($cells)) {
                continue;
            }

            // Short rows are padded rather than dropped: a trailing empty cell
            // is common and losing the whole row would lose its spend.
            $cells = array_pad(array_slice($cells, 0, $width), $width, '');

            $row = [];
            foreach ($this->header as $j => $name) {
                $row[$name] = trim((string) $cells[$j]);
            }

            // A row with no content in any mapped column is padding.
            if (implode('', $row) === '') {
                continue;
            }

            $this->rows[] = $row;
        }
    }

    /**
     * Google appends totals; importing them doubles the spend.
     *
     * @param  list<string>  $cells
     */
    private function isTotalRow(array $cells): bool
    {
        $first = mb_strtolower(trim((string) ($cells[0] ?? '')));

        return $first !== ''
            && (str_starts_with($first, 'total')
                || str_starts_with($first, 'grand total')
                || str_starts_with($first, '--'));
    }

    /**
     * @param  list<string>  $lines
     */
    private function sniffDelimiter(array $lines): string
    {
        $sample = implode("\n", array_slice($lines, 0, 20));

        $counts = [
            ',' => substr_count($sample, ','),
            "\t" => substr_count($sample, "\t"),
            ';' => substr_count($sample, ';'),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * Normalise a header cell so lookups are stable.
     */
    private function canonical(string $name): string
    {
        $name = trim($name, " \t\"'");
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return mb_strtolower(trim($name));
    }

    /**
     * The first header present from a list of aliases.
     *
     * Platforms rename columns between report types, so every field is looked
     * up by a list of known names rather than one.
     *
     * @param  list<string>  $aliases
     */
    public function column(array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (in_array($alias, $this->header, true)) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * The date range stated in the preamble, if the export declares one.
     *
     * @return array{0:?string,1:?string}
     */
    public function preambleDateRange(): array
    {
        foreach ($this->preamble as $line) {
            // "Jul 1, 2026-Jul 26, 2026" and "2026-07-01 - 2026-07-26"
            if (preg_match('/([A-Z][a-z]{2} \d{1,2}, \d{4})\s*[-\x{2013}]\s*([A-Z][a-z]{2} \d{1,2}, \d{4})/u', $line, $m)) {
                return [date('Y-m-d', (int) strtotime($m[1])), date('Y-m-d', (int) strtotime($m[2]))];
            }
            if (preg_match('/(\d{4}-\d{2}-\d{2})\s*[-\x{2013}]\s*(\d{4}-\d{2}-\d{2})/u', $line, $m)) {
                return [$m[1], $m[2]];
            }
        }

        return [null, null];
    }
}
