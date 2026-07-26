<?php

namespace App\Services\Agent;

/**
 * Writes the briefing pack to disk in two shapes.
 *
 * A markdown pack, which is what gets handed to an analyst or a model. It is
 * SELF-DESCRIBING: it carries the goal, the constraints, the numbers, and the
 * exact template of the reply expected back. That matters because it means the
 * pack can be handed to anyone or anything without a covering explanation, and
 * the reply will still be in a shape this system can read. A file that needs a
 * verbal briefing to accompany it is a file that will eventually travel without
 * one.
 *
 * A CSV, which is the same per-keyword and per-channel numbers flattened for a
 * spreadsheet. Prose does not survive a CSV and nested sections do not either,
 * so the CSV is the data and the markdown is the brief.
 */
class PackWriter
{
    /**
     * @param  array<string, mixed>  $pack
     * @return array{md: string, csv: string}
     */
    public function write(array $pack, string $dir): array
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stamp = $pack['period']['from'].'_to_'.$pack['period']['to'];
        $md = rtrim($dir, '/')."/wgh-briefing-{$stamp}.md";
        $csv = rtrim($dir, '/')."/wgh-data-{$stamp}.csv";

        file_put_contents($md, $this->markdown($pack));
        file_put_contents($csv, $this->csv($pack));

        return ['md' => $md, 'csv' => $csv];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    public function markdown(array $p): string
    {
        $t = $p['totals'];
        $d = $p['derived'];
        $out = [];

        $out[] = '# WGH Intelligence briefing pack';
        $out[] = '';
        $out[] = "Period **{$p['period']['from']} to {$p['period']['to']}**. Generated {$p['generated_at']}.";
        $out[] = '';
        $out[] = '> **If you are reading this to give advice:** everything you need is in this';
        $out[] = '> file. The reply format is at the bottom, under "What to send back". Keep to';
        $out[] = '> it and the system can read your answer straight back in.';
        $out[] = '';

        $out[] = '## The goal';
        $out[] = '';
        $out[] = 'Sell more, at profit. Every recommendation must cite a number from this file';
        $out[] = 'and say what it is expected to do to sales. No generic advice.';
        $out[] = '';

        $out[] = '## What you cannot change about this business';
        $out[] = '';
        foreach ($p['constraints'] as $c) {
            $out[] = "- {$c}";
        }
        $out[] = '';

        $out[] = '## The period';
        $out[] = '';
        $out[] = '| | |';
        $out[] = '|---|---|';
        $out[] = "| Ad spend | \${$t['spend_usd']} USD |";
        $out[] = "| Revenue | GHS {$t['revenue_ghs']}".($d['revenue_usd'] ? " (about \${$d['revenue_usd']} USD)" : '').' |';
        $out[] = "| Clicks | {$t['clicks']} |";
        $out[] = "| Added to cart | {$t['carts']} |";
        $out[] = "| WhatsApp taps | {$t['taps']} |";
        $out[] = "| Confirmed sales | {$t['orders']} |";
        $out[] = '| Cost per order | '.($d['cost_per_order_usd'] ? '$'.$d['cost_per_order_usd'] : 'no sales yet').' |';
        $out[] = "| Unmatched spend | \${$t['unmatched_spend_usd']}".($d['unmatched_share_of_spend'] ? " ({$d['unmatched_share_of_spend']} of all spend)" : '').' |';
        $out[] = '';
        $out[] = 'Funnel, each rate measured within one population:';
        $out[] = '';
        $out[] = '- Cart to WhatsApp message: '.($d['cart_to_tap'] ?? 'n/a')
            .' (of people who added to the basket, how many then messaged)';
        $out[] = '- WhatsApp message to confirmed sale: '.($d['tap_to_sale'] ?? 'n/a');
        $out[] = '';
        $out[] = 'Note: clicks are PAID clicks only, while carts and taps include organic and';
        $out[] = 'direct visitors, so do not divide one by the other. For scale, there were '
            .($d['carts_per_100_ad_clicks'] ?? 'n/a').' carts per 100 paid clicks,';
        $out[] = 'but the two populations are not the same people.';
        $out[] = '';

        if ($p['currency']['fx']['rate']) {
            $out[] = "Currency: spend is USD, sales are GHS, converted at {$p['currency']['fx']['rate']} GHS per USD dated {$p['currency']['fx']['date']}.";
        } else {
            $out[] = '**No exchange rate recorded**, so USD spend and GHS revenue cannot be compared. Say so if it blocks an answer.';
        }
        $out[] = '';

        $out[] = '## Assumptions you must respect';
        $out[] = '';
        $out[] = "- Profit per order is assumed to be **\${$p['assumptions']['profit_per_order_usd']}**. ".$p['assumptions']['why'];
        $out[] = "- Nothing is judged before {$p['assumptions']['min_days_to_judge']} days and {$p['assumptions']['min_clicks_to_judge']} clicks.";
        $out[] = '';

        if ($p['keywords']) {
            $out[] = '## Keywords';
            $out[] = '';
            $out[] = '| Keyword | Match | Spend $ | Clicks | Carts | Taps | Sales | Rev GHS | CPO $ | Days | Verdict |';
            $out[] = '|---|---|---|---|---|---|---|---|---|---|---|';
            foreach ($p['keywords'] as $k) {
                $out[] = sprintf(
                    '| %s | %s | %s | %d | %d | %d | %d | %s | %s | %d | %s |',
                    $k['keyword'], $k['match_type'] ?: '-', $k['spend_usd'], $k['clicks'],
                    $k['carts'], $k['taps'], $k['orders'], $k['revenue_ghs'],
                    $k['cost_per_order_usd'] ?? '-', $k['days'], $k['verdict'] ?? '-'
                );
            }
            $out[] = '';

            if ($p['keywords_omitted']) {
                $out[] = "Plus {$p['keywords_omitted']['count']} smaller keywords not listed, "
                    ."\${$p['keywords_omitted']['combined_spend_usd']} and {$p['keywords_omitted']['combined_orders']} sales between them.";
                $out[] = '';
            }
        }

        if ($p['channels']) {
            $out[] = '## Channels';
            $out[] = '';
            $out[] = '| Platform | Campaign | Spend $ | Clicks | Taps | Sales | Rev GHS | CPO $ | Verdict |';
            $out[] = '|---|---|---|---|---|---|---|---|---|';
            foreach ($p['channels'] as $c) {
                $out[] = sprintf(
                    '| %s | %s | %s | %d | %d | %d | %s | %s | %s |',
                    $c['platform'], $c['campaign'], $c['spend_usd'], $c['clicks'],
                    $c['taps'], $c['orders'], $c['revenue_ghs'],
                    $c['cost_per_order_usd'] ?? '-', $c['verdict'] ?? '-'
                );
            }
            $out[] = '';
        }

        if ($p['unmatched_spend']) {
            $out[] = '## Spend that matched nothing';
            $out[] = '';
            $out[] = 'This is money with no story attached. It is either a tracking gap, where the';
            $out[] = 'money works but is invisible, or a real leak. Both are worth attention.';
            $out[] = '';
            foreach (array_slice($p['unmatched_spend'], 0, 15) as $u) {
                $out[] = "- **\${$u['spend_usd']}** {$u['platform']} / {$u['campaign']} "
                    .($u['keyword'] ? "\"{$u['keyword']}\" " : '')."({$u['clicks']} clicks). {$u['likely_cause']}";
            }
            $out[] = '';
        }

        if ($p['patterns']) {
            $out[] = '## Patterns the engine noticed';
            $out[] = '';
            foreach ($p['patterns'] as $pat) {
                $out[] = "- {$pat['observation']}";
            }
            $out[] = '';
        }

        $l = $p['loop'];
        $out[] = '## The offline conversion loop';
        $out[] = '';
        $out[] = 'This is the growth engine. Feeding confirmed sales back to Google is what makes';
        $out[] = 'the same budget produce more sales over time, so anything throttling it';
        $out[] = 'outranks anything else here.';
        $out[] = '';
        $out[] = "- Confirmed sales in the last 30 days: **{$l['conversions_30d']}** (Smart Bidding stabilises around 30)";
        $out[] = "- Uploadable, meaning they carry a click id: {$l['uploadable']}";
        $out[] = '- Match rate, click id plus hashed phone: '.(int) round($l['match_rate'] * 100).'%';
        $out[] = "- Sales waiting to be exported: {$l['unexported_conversions']}";
        $out[] = '- Days since the last export: '.($l['days_since_export'] ?? 'never exported');
        $out[] = '';

        if ($p['milestones']['reached']) {
            $out[] = 'Gates reached: '.implode('; ', $p['milestones']['reached']).'.';
        }
        if ($p['milestones']['next']) {
            $out[] = "Next gate: {$p['milestones']['next']['label']} ({$p['milestones']['next']['progress']}).";
        }
        foreach ($p['milestones']['active_guardrails'] as $g) {
            $out[] = "**Warning now active:** {$g['label']}. {$g['decision']}";
        }
        $out[] = '';

        $out[] = '---';
        $out[] = '';
        $out[] = '## What to send back';
        $out[] = '';
        $out[] = 'Reply as a file using exactly these headings. Keep every heading even if a';
        $out[] = 'section is short. Cite a number from above in each one, and end with a single';
        $out[] = 'action rather than a list of options.';
        $out[] = '';
        $out[] = '```markdown';
        $out[] = '# WGH Briefing Response';
        $out[] = "Period: {$p['period']['from']} to {$p['period']['to']}";
        $out[] = '';
        $out[] = '## Biggest win';
        $out[] = 'What is working, with the number that proves it, and what to do to get more of it.';
        $out[] = '';
        $out[] = '## Biggest leak';
        $out[] = 'Where money is being lost, with the number, and what it is costing per month.';
        $out[] = '';
        $out[] = '## Do this now';
        $out[] = 'ONE action. The single highest value move. Name the expected effect on sales.';
        $out[] = '';
        $out[] = '## The risk in doing it';
        $out[] = 'What could go wrong with that action, honestly. Never leave this blank.';
        $out[] = '';
        $out[] = '## Keyword notes';
        $out[] = '- keyword name | what to do and why';
        $out[] = '';
        $out[] = '## Anything the data cannot tell you';
        $out[] = 'What you would need to know to answer better.';
        $out[] = '```';
        $out[] = '';
        $out[] = 'Save it as a `.md` or `.txt` file, then on the server run:';
        $out[] = '';
        $out[] = '    php artisan wgh:brief --import=path/to/response.md';
        $out[] = '';

        return implode("\n", $out)."\n";
    }

    /**
     * The flat data, for a spreadsheet.
     *
     * @param  array<string, mixed>  $p
     */
    public function csv(array $p): string
    {
        $fh = fopen('php://memory', 'r+');

        fputcsv($fh, [
            'section', 'entity', 'match_type', 'campaign', 'spend_usd', 'clicks',
            'carts', 'taps', 'orders', 'revenue_ghs', 'cost_per_order_usd', 'days',
            'verdict', 'engine_reason',
        ]);

        foreach ($p['keywords'] as $k) {
            fputcsv($fh, [
                'keyword', $k['keyword'], $k['match_type'], $k['campaign'], $k['spend_usd'],
                $k['clicks'], $k['carts'], $k['taps'], $k['orders'], $k['revenue_ghs'],
                $k['cost_per_order_usd'], $k['days'], $k['verdict'], $k['engine_reason'],
            ]);
        }

        foreach ($p['channels'] as $c) {
            fputcsv($fh, [
                'channel', $c['platform'], '', $c['campaign'], $c['spend_usd'],
                $c['clicks'], $c['carts'], $c['taps'], $c['orders'], $c['revenue_ghs'],
                $c['cost_per_order_usd'], $c['days'], $c['verdict'], $c['engine_reason'],
            ]);
        }

        foreach ($p['unmatched_spend'] as $u) {
            fputcsv($fh, [
                'unmatched', $u['keyword'] ?: $u['campaign'], '', $u['campaign'], $u['spend_usd'],
                $u['clicks'], '', '', '', '', '', '', 'unmatched', $u['likely_cause'],
            ]);
        }

        foreach ($p['patterns'] as $pat) {
            fputcsv($fh, [
                'pattern', $pat['pattern'], '', '', '', '', '', '', '', '', '', '',
                'pattern', $pat['observation'],
            ]);
        }

        $t = $p['totals'];
        fputcsv($fh, [
            'total', 'ALL', '', '', $t['spend_usd'], $t['clicks'], $t['carts'],
            $t['taps'], $t['orders'], $t['revenue_ghs'],
            $p['derived']['cost_per_order_usd'], '', '',
            'unmatched spend '.$t['unmatched_spend_usd'].' USD',
        ]);

        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);

        return $out;
    }
}
