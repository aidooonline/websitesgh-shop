<?php

namespace App\Services\Agent;

/**
 * The one-page visual report the owner actually reads.
 *
 * TWO DOCUMENTS, TWO AUDIENCES
 * The markdown pack is written for a cold reader who knows nothing about this
 * business: it carries the goal, the constraints, the assumptions and the reply
 * template, because an analyst or a model needs all of it. This page is written
 * for the person who owns the business and already knows all that. He needs the
 * numbers and what to do about them, so everything explanatory is stripped out.
 * Sending one document to both audiences would mean boring one of them.
 *
 * WHY SVG BY HAND
 * No chart library, no CDN, no script. One file that opens on a phone with no
 * signal, prints straight to PDF, and looks identical in two years. A charting
 * dependency would buy animation nobody needs and take away all of that.
 *
 * COLOUR IS NOT DECORATION HERE
 * Every palette below was run through the validator against this page's actual
 * surface, not eyeballed:
 *   - Diverging blue/red for profit against loss: all checks pass, both modes.
 *   - The funnel's ordinal blue ramp had to be RE-STEPPED. The documented steps
 *     failed on this warmer paper: two were 0.047 apart in lightness and the
 *     lightest sat at 1.96:1 against the surface, under the 2:1 floor.
 *   - Verdicts use the fixed status palette, which is exempt from the
 *     categorical gates, and every status mark carries its word beside it so
 *     colour never has to carry meaning alone.
 * Green and red for money was deliberately NOT used: it is the classic
 * colourblind trap, and this report is meant to be read by anyone.
 */
class ReportRenderer
{
    /* Validated against surface #f8f6f2 (light) and #1a1a19 (dark). */
    private const POS = '#2a78d6';        // earned
    private const NEG = '#e34948';        // lost
    private const FUNNEL = ['#6da7ec', '#3987e5', '#1c5cab'];
    private const STATUS = [
        'keep' => '#0ca30c',
        'watch' => '#898781',
        'fix' => '#fab219',
        'kill' => '#d03b3b',
    ];

    /**
     * @param  array<string, mixed>  $p  The briefing pack.
     * @param  array<string, mixed>|null  $advice  The latest stored briefing, if any.
     */
    public function render(array $p, ?array $advice = null): string
    {
        $t = $p['totals'];
        $d = $p['derived'];

        $sections = [
            $this->header($p),
            $this->headline($p, $advice),
            $this->kpis($t, $d, $p),
            $this->loopMeter($p),
            $this->profitByChannel($p),
            $this->spendByVerdict($p),
            $this->funnel($t, $d),
            $this->actionTable($p),
            $this->footer($p),
        ];

        $body = implode("\n", array_filter($sections));

        return $this->shell($body, "WGH report {$p['period']['from']} to {$p['period']['to']}");
    }

    /* ------------------------------------------------------------------ */

    private function header(array $p): string
    {
        $from = $this->e($p['period']['from']);
        $to = $this->e($p['period']['to']);

        return <<<HTML
        <header>
          <div class="eyebrow">WGH Intelligence</div>
          <h1>What the money did</h1>
          <p class="period">{$from} to {$to}</p>
        </header>
        HTML;
    }

    /**
     * The single most important thing on the page, at the top, in the largest type.
     */
    private function headline(array $p, ?array $advice): string
    {
        if ($advice && ! empty($advice['top_action'])) {
            $action = $this->e($this->firstSentences($advice['top_action'], 3));
            $src = $this->e((string) ($advice['model_used'] ?? 'imported'));

            return <<<HTML
            <section class="hero">
              <div class="hero-label">Do this now</div>
              <p class="hero-text">{$action}</p>
              <div class="hero-src">from the briefing imported {$src}</div>
            </section>
            HTML;
        }

        // No advice imported: lead with the engine's own strongest finding.
        $kills = array_values(array_filter($p['keywords'], fn ($k) => ($k['verdict'] ?? '') === 'kill'));
        $keeps = array_values(array_filter($p['keywords'], fn ($k) => ($k['verdict'] ?? '') === 'keep'));

        if ($kills) {
            $wasted = 0.0;
            foreach ($kills as $k) {
                $wasted += (float) $k['spend_usd'];
            }
            $best = $keeps[0]['keyword'] ?? null;
            $text = sprintf(
                '$%s is in %d keyword%s that have spent enough, run long enough, and sold nothing.',
                number_format($wasted, 2), count($kills), count($kills) === 1 ? '' : 's'
            );
            if ($best) {
                $text .= ' Move it to "'.$best.'", which is the one keyword currently paying for itself.';
            }

            return '<section class="hero"><div class="hero-label">The engine says</div>'
                .'<p class="hero-text">'.$this->e($text).'</p>'
                .'<div class="hero-src">no briefing imported yet, so this is the rule engine, not judgement</div></section>';
        }

        return '';
    }

    /**
     * Four stat tiles. A single number is a stat tile, never a one-bar chart.
     */
    private function kpis(array $t, array $d, array $p): string
    {
        $rev = $d['revenue_usd'] ?? null;
        $spend = (float) $t['spend_usd'];
        $net = $rev !== null ? (float) $rev - $spend : null;

        $tiles = [
            ['Ad spend', '$'.number_format($spend, 2), 'USD, all platforms', null],
            ['Revenue', 'GHS '.number_format((float) $t['revenue_ghs'], 0), $rev !== null ? 'about $'.number_format((float) $rev, 2) : 'no fx rate recorded', null],
            [
                'Return less spend',
                $net === null ? 'n/a' : ($net >= 0 ? '+$'.number_format($net, 2) : '-$'.number_format(abs($net), 2)),
                'before dealer and delivery cost',
                $net === null ? null : ($net >= 0 ? 'good' : 'bad'),
            ],
            [
                'Spend with no story',
                '$'.number_format((float) $t['unmatched_spend_usd'], 2),
                ($d['unmatched_share_of_spend'] ?? '0%').' of everything spent',
                (float) $t['unmatched_spend_usd'] > 0 ? 'bad' : null,
            ],
        ];

        $html = '<section class="kpis">';
        foreach ($tiles as [$label, $value, $note, $tone]) {
            $cls = $tone ? ' class="v '.$tone.'"' : ' class="v"';
            $html .= '<div class="tile"><div class="k">'.$this->e($label).'</div>'
                .'<div'.$cls.'>'.$this->e($value).'</div>'
                .'<div class="n">'.$this->e($note).'</div></div>';
        }

        return $html.'</section>';
    }

    /**
     * A single ratio against a limit is a meter, not a pie of two slices.
     */
    private function loopMeter(array $p): string
    {
        $have = (int) ($p['loop']['conversions_30d'] ?? 0);
        $floor = 30;
        $pct = min(1.0, $floor > 0 ? $have / $floor : 0);
        $w = round($pct * 100, 1);
        $short = max(0, $floor - $have);

        $note = $short > 0
            ? "{$short} more confirmed sales in 30 days and Google has enough signal to bid for buyers instead of tappers."
            : 'Google has enough confirmed sales to optimise for buyers. Switch bidding to Target CPA.';

        $fill = $short > 0 ? self::FUNNEL[1] : self::STATUS['keep'];
        $unexported = (int) ($p['loop']['unexported_conversions'] ?? 0);

        $warn = $unexported > 0
            ? '<p class="warn"><span class="dot bad"></span><strong>'.$unexported.' confirmed sale'
                .($unexported === 1 ? '' : 's').' not yet uploaded to Google.</strong> Until they are, this bar cannot move.</p>'
            : '';

        return <<<HTML
        <section>
          <h2>Progress to Smart Bidding</h2>
          <p class="q">Can Google learn who actually buys yet?</p>
          <div class="meter" role="img" aria-label="{$have} of {$floor} confirmed sales in the last 30 days">
            <div class="meter-fill" style="width:{$w}%;background:{$fill}"></div>
          </div>
          <p class="meter-cap"><strong>{$have} of {$floor}</strong> confirmed sales in the last 30 days. {$note}</p>
          {$warn}
        </section>
        HTML;
    }

    /**
     * Diverging bar: what each channel returned, minus what it cost.
     *
     * Two measures of different scale (USD spend, GHS revenue) are NEVER put on
     * two axes. Revenue is converted through the dated fx rate and the chart
     * plots one number on one scale: money in minus money out.
     */
    private function profitByChannel(array $p): string
    {
        $rate = $p['currency']['fx']['rate'] ?? null;

        if (! $rate) {
            return '<section><h2>Profit by channel</h2><p class="q">No exchange rate recorded, so USD spend and GHS revenue cannot be compared. Run <code>php artisan wgh:fx 11.85</code>.</p></section>';
        }

        $rows = [];
        $unattributedRevenue = 0.0;

        foreach ($p['channels'] as $c) {
            $spend = (float) $c['spend_usd'];
            $rev = (float) $c['revenue_ghs'] / (float) $rate;
            $net = $rev - $spend;

            /*
             * A row with no spend has no profit to show. The unassigned bucket
             * carries real revenue but zero cost, so on a profit chart it drew
             * a tall "earned" bar and read as the second best campaign in the
             * account. It is not a campaign at all: it is revenue we could not
             * tie to one. Counted separately, below the chart, where it reads
             * as the tracking gap it is.
             */
            if ($spend <= 0) {
                $unattributedRevenue += $rev;

                continue;
            }

            $rows[] = [
                'label' => $c['platform'].' / '.$c['campaign'],
                'net' => $net,
                'spend' => $spend,
                'orders' => (int) $c['orders'],
            ];
        }

        if (! $rows) {
            return '';
        }

        usort($rows, fn ($a, $b) => $b['net'] <=> $a['net']);

        $max = 0.0;
        foreach ($rows as $r) {
            $max = max($max, abs($r['net']));
        }
        $max = $max ?: 1.0;

        $rowH = 34;
        $h = count($rows) * $rowH + 26;
        $w = 720;
        $labelW = 250;
        $plotW = $w - $labelW - 70;
        $mid = $labelW + $plotW / 2;

        $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" width="100%" height="'.$h.'" role="img" aria-label="Money returned minus money spent, per channel, in US dollars">';
        $svg .= '<line x1="'.$mid.'" y1="4" x2="'.$mid.'" y2="'.($h - 22).'" stroke="var(--baseline)" stroke-width="1"/>';

        foreach ($rows as $i => $r) {
            $y = $i * $rowH + 6;
            $len = abs($r['net']) / $max * ($plotW / 2 - 6);
            $len = max($len, 2);
            $pos = $r['net'] >= 0;
            $x = $pos ? $mid : $mid - $len;
            $fill = $pos ? 'var(--pos)' : 'var(--neg)';
            $val = ($pos ? '+$' : '-$').number_format(abs($r['net']), 2);
            $tip = $this->e($r['label'].': '.$val.' after $'.number_format($r['spend'], 2).' of spend, '.$r['orders'].' sales');

            $svg .= '<text x="'.($labelW - 12).'" y="'.($y + 15).'" text-anchor="end" class="cat">'.$this->e($this->trim($r['label'], 34)).'</text>';
            // 4px rounded data-end, anchored at the baseline.
            $svg .= '<rect x="'.round($x, 1).'" y="'.$y.'" width="'.round($len, 1).'" height="20" rx="4" fill="'.$fill.'"><title>'.$tip.'</title></rect>';
            $svg .= '<text x="'.round($pos ? $x + $len + 8 : $x - 8, 1).'" y="'.($y + 15).'" text-anchor="'.($pos ? 'start' : 'end').'" class="val">'.$this->e($val).'</text>';
        }

        $svg .= '<text x="'.($mid - 8).'" y="'.($h - 6).'" text-anchor="end" class="axis">lost</text>';
        $svg .= '<text x="'.($mid + 8).'" y="'.($h - 6).'" text-anchor="start" class="axis">earned</text>';
        $svg .= '</svg>';

        $gap = $unattributedRevenue > 0.01
            ? '<p class="warn"><span class="dot bad"></span><strong>$'
                .number_format($unattributedRevenue, 2)
                .' of revenue could not be tied to any campaign.</strong> It is real money, but until the '
                .'tracking template is set it cannot be credited to what earned it, so no campaign above gets credit for it.</p>'
            : '';

        return '<section><h2>Profit by channel</h2>'
            .'<p class="q">Which campaigns brought back more than they cost?</p>'
            .$svg
            .$gap
            .'<p class="fine">Revenue converted at '.$this->e((string) $rate).' GHS per USD, dated '
            .$this->e((string) ($p['currency']['fx']['date'] ?? '')).'. Before dealer and delivery cost.</p></section>';
    }

    /**
     * Part-to-whole: where the keyword money currently sits.
     */
    private function spendByVerdict(array $p): string
    {
        $buckets = ['keep' => 0.0, 'watch' => 0.0, 'fix' => 0.0, 'kill' => 0.0];
        $counts = ['keep' => 0, 'watch' => 0, 'fix' => 0, 'kill' => 0];

        foreach ($p['keywords'] as $k) {
            $v = $k['verdict'] ?? null;
            if ($v === null || ! isset($buckets[$v])) {
                continue;
            }
            $buckets[$v] += (float) $k['spend_usd'];
            $counts[$v]++;
        }

        $total = array_sum($buckets);
        if ($total <= 0) {
            return '';
        }

        $meaning = [
            'keep' => 'paying for itself',
            'watch' => 'too early to judge',
            'fix' => 'the page or price is losing them',
            'kill' => 'spent enough, sold nothing',
        ];

        $w = 720;
        $barH = 34;
        $svg = '<svg viewBox="0 0 '.$w.' '.($barH + 4).'" width="100%" height="'.($barH + 4).'" role="img" aria-label="Keyword spend split by verdict">';
        $x = 0.0;

        foreach ($buckets as $v => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $seg = $amount / $total * $w;
            // 2px surface gap between stacked segments.
            $draw = max(1, $seg - 2);
            $svg .= '<rect x="'.round($x, 1).'" y="2" width="'.round($draw, 1).'" height="'.$barH.'" rx="4" fill="'.self::STATUS[$v].'">'
                .'<title>'.$this->e(ucfirst($v).': $'.number_format($amount, 2).' across '.$counts[$v].' keyword'.($counts[$v] === 1 ? '' : 's').', '.$meaning[$v]).'</title></rect>';
            if ($seg > 74) {
                $svg .= '<text x="'.round($x + $draw / 2, 1).'" y="'.($barH / 2 + 7).'" text-anchor="middle" class="onbar">$'
                    .$this->e(number_format($amount, 0)).'</text>';
            }
            $x += $seg;
        }
        $svg .= '</svg>';

        // Legend is always present for two or more series, so identity is
        // never carried by colour alone.
        $legend = '<div class="legend">';
        foreach ($buckets as $v => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $legend .= '<span class="li"><i style="background:'.self::STATUS[$v].'"></i>'
                .'<b>'.$this->e(ucfirst($v)).'</b> $'.$this->e(number_format($amount, 2))
                .' <span class="fine">'.$counts[$v].' kw, '.$this->e($meaning[$v]).'</span></span>';
        }
        $legend .= '</div>';

        return '<section><h2>Where the keyword money sits</h2>'
            .'<p class="q">How much is in keywords that are not working?</p>'
            .$svg.$legend.'</section>';
    }

    /**
     * Ordered stages, so an ordinal ramp rather than categorical hues.
     */
    private function funnel(array $t, array $d): string
    {
        /*
         * ONLY GENUINELY NESTED STAGES BELONG IN A FUNNEL.
         *
         * The first version drew cart -> cart message -> sale and produced
         * 31 -> 0 -> 17, which is impossible on its face and instantly
         * destroys trust in every other number on the page. The cause is real,
         * not cosmetic: this shop's funnel is not a single chain. A visitor can
         * message from a product page without ever adding to the basket, so
         * cart messages are not a superset of sales.
         *
         * Every WhatsApp message IS a superset of every confirmed sale, because
         * a sale can only be confirmed against a message. So that pair is a
         * real funnel and it is the only pair drawn. The basket is shown beside
         * it as context, which is what it actually is.
         */
        $stages = [
            ['Messaged on WhatsApp', (int) $t['taps']],
            ['Confirmed sale', (int) $t['orders']],
        ];

        if ($stages[0][1] <= 0) {
            return '';
        }

        // A funnel that widens is a bug. Refuse to draw one rather than ship a
        // picture that cannot be true.
        for ($i = 1; $i < count($stages); $i++) {
            if ($stages[$i][1] > $stages[$i - 1][1]) {
                return '';
            }
        }

        $max = $stages[0][1] ?: 1;
        $w = 720;
        $rowH = 40;
        $labelW = 200;
        $plotW = $w - $labelW - 130;
        $h = count($stages) * $rowH + 4;

        $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" width="100%" height="'.$h.'" role="img" aria-label="Funnel from WhatsApp message to confirmed sale">';

        foreach ($stages as $i => [$label, $n]) {
            $y = $i * $rowH + 6;
            $len = max(2, $n / $max * $plotW);
            $shade = self::FUNNEL[$i === 0 ? 0 : 2];

            $svg .= '<text x="'.($labelW - 14).'" y="'.($y + 18).'" text-anchor="end" class="cat">'.$this->e($label).'</text>';
            $svg .= '<rect x="'.$labelW.'" y="'.$y.'" width="'.round($len, 1).'" height="26" rx="4" fill="'.$shade.'">'
                .'<title>'.$this->e($label.': '.$n).'</title></rect>';
            $svg .= '<text x="'.round($labelW + $len + 10, 1).'" y="'.($y + 18).'" class="val">'.$n.'</text>';

            if ($i > 0) {
                $prev = $stages[$i - 1][1];
                $rate = $prev > 0 ? round($n / $prev * 100) : 0;
                $svg .= '<text x="'.round($labelW + $len + 54, 1).'" y="'.($y + 18).'" class="axis">'.$rate.'% of those messages</text>';
            }
        }
        $svg .= '</svg>';

        $carts = (int) $t['carts'];
        $context = $carts > 0
            ? '<p class="fine">'.$carts.' people also put something in the basket over the same period. '
                .'That is not a step on the way to a message here, because the shop lets people message '
                .'straight from a product page, so it is shown beside the funnel rather than inside it.</p>'
            : '';

        return '<section><h2>Where people stop</h2>'
            .'<p class="q">Of the people who opened WhatsApp, how many actually bought?</p>'
            .$svg
            .$context.'</section>';
    }

    /**
     * Only the rows that need a decision. Everything healthy is a count.
     */
    private function actionTable(array $p): string
    {
        $needs = array_values(array_filter(
            $p['keywords'],
            fn ($k) => in_array($k['verdict'] ?? '', ['kill', 'fix'], true)
        ));

        if (! $needs) {
            return '';
        }

        usort($needs, fn ($a, $b) => (float) $b['spend_usd'] <=> (float) $a['spend_usd']);

        $rows = '';
        foreach (array_slice($needs, 0, 12) as $k) {
            $v = $k['verdict'];
            $rows .= '<tr><td><span class="pill '.$v.'">'.$this->e($v).'</span></td>'
                .'<td class="kw">'.$this->e($k['keyword']).'</td>'
                .'<td class="num">$'.$this->e(number_format((float) $k['spend_usd'], 2)).'</td>'
                .'<td class="num">'.(int) $k['taps'].'</td>'
                .'<td class="num">'.(int) $k['orders'].'</td>'
                .'<td class="why">'.$this->e($this->firstSentences((string) ($k['engine_reason'] ?? ''), 1)).'</td></tr>';
        }

        $more = count($needs) > 12
            ? '<p class="fine">And '.(count($needs) - 12).' more in the decision log.</p>'
            : '';

        return <<<HTML
        <section>
          <h2>Needs a decision</h2>
          <p class="q">Only the keywords where money is at stake. Everything else is fine or too early.</p>
          <table>
            <thead><tr><th>Verdict</th><th>Keyword</th><th class="num">Spend</th><th class="num">Taps</th><th class="num">Sales</th><th>Why</th></tr></thead>
            <tbody>{$rows}</tbody>
          </table>
          {$more}
        </section>
        HTML;
    }

    private function footer(array $p): string
    {
        $gen = $this->e((string) $p['generated_at']);
        $profit = $this->e((string) $p['assumptions']['profit_per_order_usd']);

        return '<footer><p class="fine">Generated '.$gen.'. Profit per order assumed at $'.$profit
            .', which is an estimate until dealer costs are entered, so verdicts are directionally right rather than exact. '
            .'The full pack, with the reasoning behind every verdict, is in the .md file beside this one.</p></footer>';
    }

    /* ------------------------------------------------------------------ */

    private function shell(string $body, string $title): string
    {
        $t = $this->e($title);

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$t}</title>
        <style>
        :root {
          color-scheme: light;
          --paper:#f8f6f2; --ink:#20211C; --secondary:#52514e; --muted:#898781;
          --rule:#e1e0d9; --baseline:#c3c2b7; --accent:#e8630a;
          --pos:#2a78d6; --neg:#e34948;
          --good:#0ca30c; --bad:#d03b3b;
          --card:#fcfcfb;
        }
        @media (prefers-color-scheme: dark) {
          :root {
            color-scheme: dark;
            --paper:#1a1a19; --ink:#ffffff; --secondary:#c3c2b7; --muted:#898781;
            --rule:#2c2c2a; --baseline:#383835;
            --pos:#3987e5; --neg:#e66767;
            --card:#232322;
          }
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--paper); color:var(--ink);
          font:16px/1.6 "DM Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
          -webkit-font-smoothing:antialiased; }
        .wrap { max-width:820px; margin:0 auto; padding:44px 26px 80px; }
        .eyebrow { font-family:"DM Mono",ui-monospace,monospace; font-size:.7rem;
          letter-spacing:.14em; text-transform:uppercase; color:var(--accent); margin-bottom:6px; }
        h1 { font-size:2.5rem; line-height:1.05; margin:0 0 4px; letter-spacing:-.02em; }
        .period { font-family:"DM Mono",ui-monospace,monospace; font-size:.82rem; color:var(--muted); margin:0 0 30px; }
        h2 { font-size:1.15rem; margin:0 0 2px; letter-spacing:-.01em; }
        section { margin:0 0 40px; }
        .q { color:var(--secondary); font-size:.92rem; margin:0 0 16px; }
        .fine { color:var(--muted); font-size:.78rem; margin:8px 0 0; }
        .hero { background:var(--card); border-left:4px solid var(--accent);
          padding:20px 24px; border-radius:0 6px 6px 0; }
        .hero-label { font-family:"DM Mono",ui-monospace,monospace; font-size:.68rem;
          letter-spacing:.14em; text-transform:uppercase; color:var(--accent); margin-bottom:8px; }
        .hero-text { font-size:1.32rem; line-height:1.4; margin:0; font-weight:500; }
        .hero-src { font-size:.74rem; color:var(--muted); margin-top:10px; }
        .kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--rule);
          border:1px solid var(--rule); border-radius:6px; overflow:hidden; }
        .tile { background:var(--card); padding:16px 14px; }
        .tile .k { font-family:"DM Mono",ui-monospace,monospace; font-size:.65rem;
          letter-spacing:.09em; text-transform:uppercase; color:var(--muted); margin-bottom:7px; }
        .tile .v { font-size:1.5rem; font-weight:700; letter-spacing:-.02em; line-height:1.15; }
        .tile .v.good { color:var(--good); } .tile .v.bad { color:var(--bad); }
        .tile .n { font-size:.72rem; color:var(--muted); margin-top:5px; }
        .meter { height:16px; background:var(--rule); border-radius:99px; overflow:hidden; }
        .meter-fill { height:100%; border-radius:99px; }
        .meter-cap { font-size:.92rem; margin:10px 0 0; }
        .warn { font-size:.88rem; margin:8px 0 0; color:var(--secondary); }
        .dot { display:inline-block; width:9px; height:9px; border-radius:99px; margin-right:7px; }
        .dot.bad { background:var(--bad); }
        svg { display:block; margin:4px 0 0; overflow:visible; }
        .cat { font-size:12px; fill:var(--secondary); font-family:"DM Sans",sans-serif; }
        .val { font-size:12px; fill:var(--ink); font-weight:700; font-family:"DM Mono",ui-monospace,monospace; }
        .axis { font-size:10.5px; fill:var(--muted); font-family:"DM Mono",ui-monospace,monospace; }
        .onbar { font-size:11.5px; fill:#fff; font-weight:700; font-family:"DM Mono",ui-monospace,monospace; }
        .legend { display:flex; flex-wrap:wrap; gap:8px 20px; margin-top:14px; font-size:.82rem; }
        .li { display:flex; align-items:baseline; gap:7px; }
        .li i { width:10px; height:10px; border-radius:2px; flex:none; transform:translateY(1px); }
        table { width:100%; border-collapse:collapse; font-size:.88rem; margin-top:4px; }
        th { text-align:left; font-family:"DM Mono",ui-monospace,monospace; font-size:.66rem;
          letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:500;
          padding:6px 10px 6px 0; border-bottom:1.5px solid var(--ink); white-space:nowrap; }
        td { padding:9px 10px 9px 0; border-bottom:1px solid var(--rule); vertical-align:top; }
        .num { text-align:right; font-family:"DM Mono",ui-monospace,monospace; }
        .kw { font-weight:500; }
        .why { color:var(--secondary); font-size:.82rem; }
        .pill { display:inline-block; font-family:"DM Mono",ui-monospace,monospace; font-size:.64rem;
          text-transform:uppercase; letter-spacing:.07em; padding:2px 8px; border-radius:99px;
          border:1px solid currentColor; font-weight:500; }
        .pill.keep{color:#0ca30c} .pill.kill{color:#d03b3b} .pill.fix{color:#b26a00} .pill.watch{color:var(--muted)}
        footer { border-top:1px solid var(--rule); padding-top:16px; }
        @media (max-width:640px) { .kpis { grid-template-columns:repeat(2,1fr); } h1 { font-size:2rem; } }
        @media print {
          body { background:#fff; } .wrap { padding:0; max-width:none; }
          section { break-inside:avoid; } .hero { border-left-color:#e8630a; }
        }
        </style>
        </head>
        <body><div class="wrap">
        {$body}
        </div></body>
        </html>
        HTML;
    }

    private function firstSentences(string $text, int $n): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? $text);
        $parts = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];

        return trim(implode(' ', array_slice($parts, 0, $n)));
    }

    private function trim(string $s, int $len): string
    {
        return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1).'…' : $s;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES);
    }
}
