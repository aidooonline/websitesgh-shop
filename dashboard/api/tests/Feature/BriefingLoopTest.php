<?php

namespace Tests\Feature;

use App\Models\AgentBriefing;
use App\Services\Agent\ResponseParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The manual briefing loop.
 *
 * The parser has to survive a file that came out of a chat window, was pasted
 * into a text editor, and possibly had its heading levels mangled on the way.
 * A strict parser would reject good advice over a stray character and the owner
 * would stop using the loop, so these tests pin the forgiving behaviour as
 * deliberate rather than accidental.
 */
class BriefingLoopTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $body): string
    {
        $path = sys_get_temp_dir().'/wgh-brief-'.md5($body).'.md';
        file_put_contents($path, $body);

        return $path;
    }

    private function goodResponse(): string
    {
        return <<<'MD'
        # WGH Briefing Response
        Period: 2026-07-01 to 2026-07-28

        ## Biggest win
        "blender price accra" costs $5.16 per order against $8.75 profit.

        ## Biggest leak
        $308.05 matched no attribution at all.

        ## Do this now
        Pause the three broad terms and move the budget to the exact keyword.

        ## The risk in doing it
        Profit per order is an estimate, so this could be scaling a loss.

        ## Keyword notes
        - binatone blender | 23 taps and no sale, check the price.
        MD;
    }

    public function test_a_well_formed_response_parses_and_stores(): void
    {
        $parsed = (new ResponseParser)->parse($this->file($this->goodResponse()));

        $this->assertSame('2026-07-01 to 2026-07-28', $parsed['period']);
        $this->assertArrayHasKey('win', $parsed['sections']);
        $this->assertArrayHasKey('leak', $parsed['sections']);
        $this->assertArrayHasKey('top_action', $parsed['sections']);
        $this->assertArrayHasKey('risk', $parsed['sections']);
        $this->assertStringContainsString('Pause the three broad terms', $parsed['top_action']);
    }

    public function test_the_command_stores_it_and_records_where_it_came_from(): void
    {
        $this->artisan('wgh:brief', ['--import' => $this->file($this->goodResponse())])
            ->assertSuccessful();

        $b = AgentBriefing::first();

        $this->assertSame('manual', $b->trigger);
        // Honest about provenance: in six months it must be possible to ask
        // who actually said this and get a straight answer.
        $this->assertStringContainsString('manual', $b->model_used);
        $this->assertSame('0.0000', (string) $b->tokens_cost);
        $this->assertStringContainsString('Pause the three broad terms', $b->top_action);
    }

    public function test_headings_are_matched_loosely_not_exactly(): void
    {
        // Different level, different wording, different order. All still valid
        // advice and all still readable.
        $parsed = (new ResponseParser)->parse($this->file(<<<'MD'
        ### What could go wrong
        The margin is an estimate.

        # WHAT IS WORKING
        The exact keyword pays for itself.

        ##### Next action
        Move budget to the exact keyword.
        MD));

        $this->assertArrayHasKey('risk', $parsed['sections']);
        $this->assertArrayHasKey('win', $parsed['sections']);
        $this->assertSame('Move budget to the exact keyword.', $parsed['top_action']);
    }

    public function test_a_response_wrapped_in_a_code_fence_still_parses(): void
    {
        // Copying out of a chat window very often brings the fence along.
        $parsed = (new ResponseParser)->parse($this->file("```markdown\n".$this->goodResponse()."\n```"));

        $this->assertArrayHasKey('top_action', $parsed['sections']);
    }

    public function test_an_unrecognised_section_is_kept_not_dropped(): void
    {
        $parsed = (new ResponseParser)->parse($this->file(<<<'MD'
        ## Biggest win
        The exact keyword pays.

        ## Do this now
        Move the budget.

        ## A thought about seasonality
        December will be different.
        MD));

        $extras = array_filter(array_keys($parsed['sections']), fn ($k) => str_starts_with($k, 'extra_'));

        $this->assertNotEmpty($extras, 'an analyst who adds a useful section should not have it silently deleted');
    }

    public function test_a_briefing_with_no_action_is_refused(): void
    {
        // The whole point of the loop is that it ends in one move. A briefing
        // that does not is an essay, and storing it would leave a record that
        // later reads as though the system had nothing to say.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Do this now/');

        (new ResponseParser)->parse($this->file(<<<'MD'
        ## Biggest win
        Things are going well.

        ## Biggest leak
        Some money is being wasted.
        MD));
    }

    public function test_a_file_with_no_headings_is_refused_with_an_explanation(): void
    {
        $this->expectException(RuntimeException::class);

        (new ResponseParser)->parse($this->file('Just move the budget to the exact keyword, it is doing well.'));
    }

    public function test_an_empty_file_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty/');

        (new ResponseParser)->parse($this->file("   \n\n  "));
    }

    public function test_the_export_writes_a_self_describing_pack(): void
    {
        $dir = sys_get_temp_dir().'/wgh-pack-test';

        $this->artisan('wgh:brief', ['--export' => true, '--dir' => $dir])
            ->assertSuccessful();

        $files = glob($dir.'/wgh-briefing-*.md');
        $this->assertNotEmpty($files);

        $md = (string) file_get_contents($files[0]);

        // The pack must be handable to anyone with no covering explanation.
        $this->assertStringContainsString('## The goal', $md);
        $this->assertStringContainsString('What you cannot change about this business', $md);
        $this->assertStringContainsString('## What to send back', $md);
        $this->assertStringContainsString('# WGH Briefing Response', $md, 'the reply template must be inside the pack');
        $this->assertStringContainsString('wgh:brief --import', $md);

        $this->assertNotEmpty(glob($dir.'/wgh-data-*.csv'));

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    public function test_the_pack_never_reports_a_conversion_rate_above_one_hundred_percent(): void
    {
        $dir = sys_get_temp_dir().'/wgh-pack-rate-test';

        $this->artisan('wgh:brief', ['--export' => true, '--dir' => $dir])->assertSuccessful();

        $md = (string) file_get_contents(glob($dir.'/wgh-briefing-*.md')[0]);

        // A rate over 100% in a document sent to an analyst destroys trust in
        // every other number on the page. It happened once, by dividing all
        // taps by carts when a product-page tap never touched a basket.
        preg_match_all('/(\d+(?:\.\d+)?)%/', $md, $m);

        foreach ($m[1] as $pct) {
            $this->assertLessThanOrEqual(100.0, (float) $pct, "found a rate of {$pct}% in the pack");
        }

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    /* ---------------- the visual report ---------------- */

    /**
     * @return array<string, mixed>
     */
    private function pack(array $overrides = []): array
    {
        return array_merge([
            'generated_at' => '2026-07-26T00:00:00+00:00',
            'period' => ['from' => '2026-07-01', 'to' => '2026-07-28'],
            'currency' => ['spend' => 'USD', 'sales' => 'GHS', 'fx' => ['rate' => '11.850000', 'date' => '2026-07-15']],
            'totals' => [
                'spend_usd' => '100.00', 'clicks' => 500, 'impressions' => 9000,
                'taps' => 40, 'carts' => 31, 'cart_taps' => 0, 'orders' => 10,
                'revenue_ghs' => '5000.00', 'unmatched_spend_usd' => '20.00',
            ],
            'derived' => [
                'cost_per_order_usd' => '10.00', 'revenue_usd' => '421.94',
                'cart_to_tap' => '0.0%', 'tap_to_sale' => '25.0%',
                'carts_per_100_ad_clicks' => '6.2', 'unmatched_share_of_spend' => '20.0%',
            ],
            'keywords' => [], 'keywords_omitted' => null, 'channels' => [],
            'unmatched_spend' => [], 'patterns' => [],
            'loop' => ['conversions_30d' => 10, 'unexported_conversions' => 2, 'match_rate' => 0.9,
                'uploadable' => 10, 'with_phone' => 9, 'days_since_export' => 2, 'conversions_total' => 10,
                'cart_to_whatsapp_rate' => null, 'evaluated_at' => '2026-07-26T00:00:00+00:00'],
            'milestones' => ['reached' => [], 'next' => null, 'active_guardrails' => []],
            'assumptions' => ['profit_per_order_usd' => '8.75', 'profit_per_order_is_an_estimate' => true,
                'why' => 'estimate', 'min_days_to_judge' => 14, 'min_clicks_to_judge' => 100],
            'constraints' => ['WhatsApp is the checkout.'],
        ], $overrides);
    }

    public function test_the_funnel_is_never_drawn_widening(): void
    {
        // 31 carts, 0 cart messages, 17 sales shipped once and drew a funnel
        // that went 31 to 0 to 17. A picture that cannot be true destroys
        // trust in every other number on the page.
        $html = (new \App\Services\Agent\ReportRenderer)->render($this->pack([
            'totals' => [
                'spend_usd' => '100.00', 'clicks' => 500, 'impressions' => 9000,
                'taps' => 5, 'carts' => 31, 'cart_taps' => 0, 'orders' => 40,
                'revenue_ghs' => '5000.00', 'unmatched_spend_usd' => '0.00',
            ],
        ]));

        // Sales exceed messages, which is impossible, so the funnel is omitted
        // rather than drawn wrong.
        $this->assertStringNotContainsString('Where people stop', $html);
    }

    public function test_the_funnel_is_drawn_when_the_stages_really_do_nest(): void
    {
        $html = (new \App\Services\Agent\ReportRenderer)->render($this->pack());

        $this->assertStringContainsString('Where people stop', $html);
        $this->assertStringContainsString('25% of those messages', $html);
    }

    public function test_a_channel_with_no_spend_is_kept_out_of_the_profit_chart(): void
    {
        // The unassigned bucket carries real revenue and zero cost, so on a
        // profit chart it drew a tall "earned" bar and read as the second best
        // campaign in the account. It is not a campaign at all.
        $html = (new \App\Services\Agent\ReportRenderer)->render($this->pack([
            'channels' => [
                ['platform' => 'meta', 'campaign' => 'CTWA-Blenders', 'spend_usd' => '50.00',
                    'clicks' => 100, 'carts' => 2, 'taps' => 10, 'orders' => 5,
                    'revenue_ghs' => '3000.00', 'cost_per_order_usd' => '10.00', 'days' => 28, 'verdict' => 'keep'],
                ['platform' => 'google', 'campaign' => '(campaign not identified)', 'spend_usd' => '0.00',
                    'clicks' => 0, 'carts' => 0, 'taps' => 5, 'orders' => 5,
                    'revenue_ghs' => '2000.00', 'cost_per_order_usd' => null, 'days' => 0, 'verdict' => null],
            ],
        ]));

        $this->assertStringContainsString('CTWA-Blenders', $html);
        $this->assertStringNotContainsString('>google / (campaign not identified)<', $html);
        // It is not hidden either: it is named as a tracking gap.
        $this->assertStringContainsString('could not be tied to any campaign', $html);
    }

    public function test_the_report_never_shows_a_rate_above_one_hundred_percent(): void
    {
        $html = (new \App\Services\Agent\ReportRenderer)->render($this->pack());

        preg_match_all('/>(\d+(?:\.\d+)?)%/', $html, $m);

        foreach ($m[1] as $pct) {
            $this->assertLessThanOrEqual(100.0, (float) $pct, "found {$pct}% in the report");
        }
    }

    public function test_the_report_leads_with_the_imported_action_when_there_is_one(): void
    {
        $html = (new \App\Services\Agent\ReportRenderer)->render($this->pack(), [
            'top_action' => 'Pause the three broad terms and move the budget.',
            'model_used' => 'manual (file import)',
        ]);

        // The decision goes above the evidence. That is the whole point of the
        // page: what to do, then why.
        $this->assertStringContainsString('Do this now', $html);
        $this->assertStringContainsString('Pause the three broad terms', $html);
        $this->assertLessThan(
            strpos($html, 'Ad spend'),
            strpos($html, 'Pause the three broad terms'),
            'the action must appear before the numbers'
        );
    }

    public function test_every_verdict_colour_is_paired_with_its_word(): void
    {
        // Status colour never carries meaning alone.
        $html = (new \App\Services\Agent\ReportRenderer)->render($this->pack([
            'keywords' => [
                ['keyword' => 'blender', 'match_type' => 'b', 'campaign' => 'c', 'spend_usd' => '60.00',
                    'clicks' => 180, 'carts' => 0, 'taps' => 0, 'orders' => 0, 'revenue_ghs' => '0.00',
                    'cost_per_order_usd' => null, 'days' => 28, 'join_strength' => 'none',
                    'verdict' => 'kill', 'engine_reason' => 'Spent with no sale.'],
            ],
        ]));

        $this->assertStringContainsString('class="pill kill">kill<', $html);
        $this->assertStringContainsString('<b>Kill</b>', $html);
    }
}
