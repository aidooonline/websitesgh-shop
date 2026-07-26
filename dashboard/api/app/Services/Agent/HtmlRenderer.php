<?php

namespace App\Services\Agent;

/**
 * Renders a briefing as a single self-contained HTML page.
 *
 * WHY THIS EXISTS
 * Until the React dashboard is built there is no screen to read anything on,
 * and a markdown file over SSH is not a report anyone will actually look at
 * twice a month. This produces one file that opens in a browser, prints
 * cleanly to PDF, and needs no server, no network and no login. Download it,
 * double click it, done.
 *
 * SELF-CONTAINED, DELIBERATELY
 * Everything is inlined. No stylesheet, no font file, no script. The file can
 * be emailed, kept in a folder, or opened on a phone with no signal, and it
 * will look the same in two years. Fonts fall back to the system stack rather
 * than fetching from a CDN, because a report that renders differently
 * depending on the network is a report you cannot trust to print.
 *
 * It is not written anywhere web-accessible. These are the business's profit
 * numbers and there is no login on this application yet, so the file stays
 * inside storage/ where the web server cannot serve it, and it leaves only
 * when the owner downloads it.
 */
class HtmlRenderer
{
    /**
     * A very small markdown subset: enough for these documents and nothing more.
     *
     * A full parser would be a dependency and an attack surface for a file that
     * only ever contains headings, tables, lists, bold, code and paragraphs.
     */
    public function render(string $markdown, string $title): string
    {
        $body = $this->toHtml($markdown);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$safeTitle}</title>
        <style>
        :root {
          --paper: #f8f6f2;
          --ink: #20211C;
          --accent: #e8630a;
          --muted: #6b6a63;
          --rule: #e2ded6;
          --keep: #0E8C5A;
          --kill: #b3261e;
          --fix: #b26a00;
        }
        * { box-sizing: border-box; }
        body {
          margin: 0;
          background: var(--paper);
          color: var(--ink);
          font: 16px/1.65 "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
          -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 880px; margin: 0 auto; padding: 56px 28px 96px; }
        h1 {
          font-size: 2.4rem; line-height: 1.1; letter-spacing: -0.01em;
          margin: 0 0 8px; font-weight: 700;
        }
        h2 {
          font-size: 1.35rem; margin: 44px 0 14px; padding-top: 18px;
          border-top: 2px solid var(--ink); font-weight: 700; letter-spacing: -0.01em;
        }
        h3 { font-size: 1.05rem; margin: 26px 0 8px; font-weight: 700; }
        p { margin: 0 0 14px; }
        ul { margin: 0 0 16px; padding-left: 20px; }
        li { margin-bottom: 6px; }
        strong { font-weight: 700; }
        code, .mono {
          font-family: "DM Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
          font-size: 0.9em; background: rgba(32,33,28,.06);
          padding: 1px 5px; border-radius: 3px;
        }
        blockquote {
          margin: 0 0 22px; padding: 14px 18px;
          background: rgba(232,99,10,.07);
          border-left: 3px solid var(--accent);
        }
        blockquote p:last-child { margin-bottom: 0; }
        table {
          width: 100%; border-collapse: collapse; margin: 0 0 22px;
          font-size: 0.92rem;
        }
        th {
          text-align: left; font-family: "DM Mono", ui-monospace, monospace;
          font-size: 0.72rem; text-transform: uppercase; letter-spacing: .07em;
          color: var(--muted); border-bottom: 1.5px solid var(--ink);
          padding: 7px 9px 7px 0; font-weight: 500; white-space: nowrap;
        }
        td { padding: 8px 9px 8px 0; border-bottom: 1px solid var(--rule); vertical-align: top; }
        tbody tr:last-child td { border-bottom: 1.5px solid var(--ink); }
        .pill {
          display: inline-block; font-family: "DM Mono", ui-monospace, monospace;
          font-size: 0.7rem; text-transform: uppercase; letter-spacing: .06em;
          padding: 2px 8px; border-radius: 99px; border: 1px solid currentColor;
          font-weight: 500;
        }
        .keep { color: var(--keep); }
        .kill { color: var(--kill); }
        .fix  { color: var(--fix); }
        .watch { color: var(--muted); }
        .meta {
          font-family: "DM Mono", ui-monospace, monospace; font-size: .8rem;
          color: var(--muted); margin: 0 0 34px;
        }
        hr { border: 0; border-top: 1px solid var(--rule); margin: 34px 0; }
        pre {
          background: rgba(32,33,28,.05); padding: 14px 16px; overflow-x: auto;
          font-family: "DM Mono", ui-monospace, monospace; font-size: .82rem;
          border-radius: 4px;
        }
        pre code { background: none; padding: 0; }
        @media print {
          body { background: #fff; }
          .wrap { padding: 0; max-width: none; }
          h2 { break-after: avoid; }
          table, blockquote { break-inside: avoid; }
        }
        </style>
        </head>
        <body><div class="wrap">
        {$body}
        </div></body>
        </html>
        HTML;
    }

    private function toHtml(string $md): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $md));
        $out = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = $lines[$i];

            // Fenced code
            if (preg_match('/^\s*```/', $line)) {
                $buf = [];
                $i++;
                while ($i < $n && ! preg_match('/^\s*```/', $lines[$i])) {
                    $buf[] = htmlspecialchars($lines[$i], ENT_QUOTES);
                    $i++;
                }
                $i++;
                $out[] = '<pre><code>'.implode("\n", $buf).'</code></pre>';

                continue;
            }

            // Table: a header row followed by a separator row.
            if (str_starts_with(trim($line), '|') && isset($lines[$i + 1]) && preg_match('/^\s*\|[\s:|-]+\|\s*$/', $lines[$i + 1])) {
                $head = $this->cells($line);
                $i += 2;
                $rows = [];
                while ($i < $n && str_starts_with(trim($lines[$i]), '|')) {
                    $rows[] = $this->cells($lines[$i]);
                    $i++;
                }

                $t = '<table><thead><tr>';
                foreach ($head as $h) {
                    $t .= '<th>'.$this->inline($h).'</th>';
                }
                $t .= '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $t .= '<tr>';
                    foreach ($r as $c) {
                        $t .= '<td>'.$this->verdictPill($c).'</td>';
                    }
                    $t .= '</tr>';
                }
                $out[] = $t.'</tbody></table>';

                continue;
            }

            // Blockquote
            if (preg_match('/^\s*>\s?(.*)$/', $line, $m)) {
                $buf = [$m[1]];
                $i++;
                while ($i < $n && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $m2)) {
                    $buf[] = $m2[1];
                    $i++;
                }
                $out[] = '<blockquote><p>'.$this->inline(implode(' ', $buf)).'</p></blockquote>';

                continue;
            }

            // List
            if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
                $buf = [$m[1]];
                $i++;
                while ($i < $n && preg_match('/^\s*[-*]\s+(.*)$/', $lines[$i], $m2)) {
                    $buf[] = $m2[1];
                    $i++;
                }
                $out[] = '<ul><li>'.implode('</li><li>', array_map(fn ($b) => $this->inline($b), $buf)).'</li></ul>';

                continue;
            }

            if (preg_match('/^\s*(#{1,6})\s+(.*)$/', $line, $m)) {
                $level = min(3, strlen($m[1]));
                $out[] = "<h{$level}>".$this->inline($m[2])."</h{$level}>";
                $i++;

                continue;
            }

            if (preg_match('/^\s*---+\s*$/', $line)) {
                $out[] = '<hr>';
                $i++;

                continue;
            }

            if (trim($line) === '') {
                $i++;

                continue;
            }

            // Paragraph: gather until a blank line or a block start.
            $buf = [$line];
            $i++;
            while ($i < $n && trim($lines[$i]) !== ''
                && ! preg_match('/^\s*([#>|-]|```|\*\s)/', $lines[$i])) {
                $buf[] = $lines[$i];
                $i++;
            }
            $out[] = '<p>'.$this->inline(implode(' ', $buf)).'</p>';
        }

        return implode("\n", $out);
    }

    /** @return list<string> */
    private function cells(string $row): array
    {
        $row = trim(trim($row), '|');

        return array_map('trim', explode('|', $row));
    }

    /**
     * Colour a verdict word so the table can be read at a glance.
     *
     * The whole point of the report is that the owner sees what to do in
     * seconds. Four words in four colours does more for that than any amount
     * of prose underneath.
     */
    private function verdictPill(string $cell): string
    {
        $v = strtolower(trim($cell));

        if (in_array($v, ['keep', 'kill', 'fix', 'watch'], true)) {
            return '<span class="pill '.$v.'">'.$v.'</span>';
        }

        return $this->inline($cell);
    }

    private function inline(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;

        return $text;
    }
}
