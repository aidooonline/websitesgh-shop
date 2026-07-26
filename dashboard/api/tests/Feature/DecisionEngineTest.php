<?php

namespace Tests\Feature;

use App\Models\AttributionEvent;
use App\Models\Decision;
use App\Models\Milestone;
use App\Services\Decisions\MilestoneEvaluator;
use App\Services\Decisions\PatternDetector;
use App\Services\Decisions\VerdictEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\MilestoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Sprint 3's acceptance test.
 *
 * From the spec: every keyword gets a verdict and a reason; a keyword with five
 * days of data is WATCH with a countdown, never KILL; a keyword with good taps
 * and no sale is FIX, not KILL; every verdict carries evidence; at least one
 * pattern is detected and stored; GATE 2 stamps at 30 conversions; an overdue
 * export fires G-A.
 */
class DecisionEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'keyword' => 'blender price accra',
            'match_type' => 'e',
            'campaign' => 'WGH - Search',
            'ad_group' => 'Blenders',
            'spend_usd' => '41.28',
            'clicks' => 150,
            'carts' => 6,
            'taps' => 12,
            'orders' => 8,
            'revenue_ghs' => '4820.00',
            'days' => 28,
            'join_strength' => 'click_id',
        ], $overrides);
    }

    public function test_a_profitable_keyword_is_kept_and_told_to_scale(): void
    {
        $j = (new VerdictEngine)->judge($this->row());

        $this->assertSame('keep', $j['verdict']);
        $this->assertStringContainsString('5.16', $j['reason']);
        $this->assertStringContainsString('Scale', $j['action']);
    }

    public function test_thin_data_is_watched_with_a_countdown_never_killed(): void
    {
        // Five days and barely any clicks. Killing here is killing on
        // impatience, and it is the mistake the dual condition exists to stop.
        $j = (new VerdictEngine)->judge($this->row([
            'days' => 5, 'clicks' => 12, 'taps' => 0, 'carts' => 0, 'orders' => 0,
            'spend_usd' => '4.10', 'revenue_ghs' => '0.00',
        ]));

        $this->assertSame('watch', $j['verdict']);
        $this->assertArrayHasKey('countdown', $j['evidence']);
        $this->assertSame(9, $j['evidence']['countdown']['days_remaining']);
        $this->assertSame(88, $j['evidence']['countdown']['clicks_remaining']);
    }

    public function test_a_keyword_that_has_spent_enough_but_had_no_time_is_not_killed(): void
    {
        // Enough money at risk, but only three days of it. Time AND spend, or
        // a keyword that had a bad first weekend gets cut before it can work.
        $j = (new VerdictEngine)->judge($this->row([
            'days' => 3, 'clicks' => 200, 'taps' => 0, 'carts' => 0, 'orders' => 0,
            'spend_usd' => '60.00', 'revenue_ghs' => '0.00',
        ]));

        $this->assertSame('watch', $j['verdict']);
        $this->assertNotSame('kill', $j['verdict']);
    }

    public function test_taps_but_no_sale_is_FIX_not_KILL(): void
    {
        // The money insight. These people wanted it enough to open WhatsApp.
        // Killing the keyword switches off demand already paid for; the real
        // fault is after the click.
        $j = (new VerdictEngine)->judge($this->row([
            'clicks' => 180, 'taps' => 14, 'carts' => 9, 'orders' => 0,
            'spend_usd' => '55.00', 'revenue_ghs' => '0.00', 'days' => 28,
        ]));

        $this->assertSame('fix', $j['verdict']);
        $this->assertStringContainsString('opened WhatsApp', $j['reason']);
        $this->assertStringContainsString('landing page', $j['action']);
    }

    public function test_a_selling_keyword_that_costs_more_than_it_earns_is_FIX_not_KILL(): void
    {
        // It converts. The bid is wrong, not the keyword.
        $j = (new VerdictEngine)->judge($this->row([
            'orders' => 2, 'spend_usd' => '40.00', 'clicks' => 200, 'days' => 28,
        ]));

        $this->assertSame('fix', $j['verdict']);
        $this->assertStringContainsString('loses', $j['reason']);
    }

    public function test_old_enough_spent_enough_and_sold_nothing_is_killed(): void
    {
        $j = (new VerdictEngine)->judge($this->row([
            'days' => 28, 'clicks' => 188, 'taps' => 0, 'carts' => 0, 'orders' => 0,
            'spend_usd' => '61.55', 'revenue_ghs' => '0.00',
        ]));

        $this->assertSame('kill', $j['verdict']);
        $this->assertStringContainsString('61.55', $j['reason']);
    }

    public function test_every_verdict_carries_evidence_and_a_reason(): void
    {
        $rows = [
            $this->row(),
            $this->row(['keyword' => 'blender', 'match_type' => 'b', 'orders' => 0, 'taps' => 0, 'spend_usd' => '61.55']),
            $this->row(['keyword' => 'kettle', 'match_type' => 'b', 'days' => 4, 'clicks' => 9, 'orders' => 0, 'taps' => 0]),
        ];

        $result = (new VerdictEngine)->judgeKeywords($rows);

        $this->assertSame(3, $result['judged']);
        $this->assertSame(3, Decision::count());

        foreach (Decision::all() as $d) {
            $this->assertNotEmpty($d->reason);
            $this->assertNotEmpty($d->evidence_json);
            $this->assertArrayHasKey('spend_usd', $d->evidence_json);
            $this->assertArrayHasKey('thresholds', $d->evidence_json);
        }
    }

    public function test_a_verdict_without_evidence_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Decision::create([
            'dimension' => 'keyword',
            'entity_ref' => 'blender',
            'verdict' => 'kill',
            'reason' => 'because I said so',
            'evidence_json' => [],
            'source' => 'engine',
            'created_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function test_the_decision_log_is_immutable(): void
    {
        $d = Decision::create([
            'dimension' => 'keyword', 'entity_ref' => 'blender', 'verdict' => 'kill',
            'reason' => 'spent with no sale', 'evidence_json' => ['spend_usd' => '61.55'],
            'source' => 'engine', 'created_at' => CarbonImmutable::now('UTC'),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $d->update(['verdict' => 'keep']);
    }

    public function test_a_pattern_is_detected_and_stored(): void
    {
        $engine = new VerdictEngine;

        $rows = [
            $this->row(['keyword' => 'blender price accra', 'match_type' => 'e']),
            $this->row(['keyword' => 'blender', 'match_type' => 'b', 'orders' => 0, 'taps' => 0, 'spend_usd' => '61.55', 'revenue_ghs' => '0.00']),
            $this->row(['keyword' => 'microwave', 'match_type' => 'b', 'orders' => 0, 'taps' => 0, 'spend_usd' => '52.40', 'revenue_ghs' => '0.00']),
            $this->row(['keyword' => 'kettle', 'match_type' => 'b', 'orders' => 0, 'taps' => 0, 'spend_usd' => '38.75', 'revenue_ghs' => '0.00']),
        ];

        $result = $engine->judgeKeywords($rows);
        $patterns = (new PatternDetector)->detect($result['verdicts']);

        $this->assertNotEmpty($patterns, 'three broad-match category terms killed against one exact keeper is a pattern');

        $names = array_column($patterns, 'pattern');
        $this->assertContains('were broad match', $names);

        $stored = Decision::where('verdict', 'pattern')->get();
        $this->assertNotEmpty($stored);
        $this->assertNotEmpty($stored->first()->evidence_json['examples']);
    }

    public function test_a_trait_shared_by_winners_and_losers_is_not_reported_as_a_pattern(): void
    {
        // Everything is broad match here, winners included, so "broad match"
        // describes the account rather than explaining the failures. Reporting
        // it would be finding a pattern in noise.
        $engine = new VerdictEngine;

        $rows = [
            $this->row(['keyword' => 'blender price accra', 'match_type' => 'b']),
            $this->row(['keyword' => 'microwave oven ghana', 'match_type' => 'b']),
            $this->row(['keyword' => 'blender', 'match_type' => 'b', 'orders' => 0, 'taps' => 0, 'spend_usd' => '61.55', 'revenue_ghs' => '0.00']),
            $this->row(['keyword' => 'microwave', 'match_type' => 'b', 'orders' => 0, 'taps' => 0, 'spend_usd' => '52.40', 'revenue_ghs' => '0.00']),
            $this->row(['keyword' => 'kettle', 'match_type' => 'b', 'orders' => 0, 'taps' => 0, 'spend_usd' => '38.75', 'revenue_ghs' => '0.00']),
        ];

        $patterns = (new PatternDetector)->detect($engine->judgeKeywords($rows)['verdicts']);

        $this->assertNotContains('were broad match', array_column($patterns, 'pattern'));
    }

/* ---------------- the join must not invent revenue ---------------- */

    public function test_two_campaigns_on_one_platform_do_not_both_claim_the_same_sales(): void
    {
        // This bug shipped once and it is the worst kind: every Meta campaign
        // claimed every Meta sale, so total attributed revenue came to 2.6x the
        // money actually taken, cost per order was understated everywhere, and a
        // cold audience that had sold nothing was handed a KEEP verdict at $4.00
        // an order. That is exactly how a losing campaign gets scaled.
        \App\Models\AdSpend::create([
            'platform' => 'meta', 'campaign' => 'CTWA-Blenders', 'ad_group' => '', 'keyword' => '',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-28',
            'impressions' => 1000, 'clicks' => 100, 'spend_usd' => '50.00',
            'currency' => 'USD', 'imported_at' => CarbonImmutable::now('UTC'),
        ]);
        \App\Models\AdSpend::create([
            'platform' => 'meta', 'campaign' => 'CTWA-Cold', 'ad_group' => '', 'keyword' => '',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-28',
            'impressions' => 900, 'clicks' => 90, 'spend_usd' => '40.00',
            'currency' => 'USD', 'imported_at' => CarbonImmutable::now('UTC'),
        ]);

        // One sale, belonging to CTWA-Blenders by name.
        AttributionEvent::create([
            'woo_attr_id' => 1, 'created_at' => '2026-07-10 10:00:00', 'updated_at' => '2026-07-10 10:00:00',
            'status' => 'converted', 'converted_at' => '2026-07-10 11:00:00', 'conv_value_ghs' => '900.00',
            'utm_source' => 'meta', 'utm_campaign' => 'CTWA-Blenders',
            'payload_hash' => hash('sha256', 'a'), 'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        $picture = (new \App\Services\Ads\JoinEngine)->build('2026-07-01', '2026-07-28');

        $named = collect($picture['channels'])->firstWhere('campaign', 'CTWA-Blenders');
        $cold = collect($picture['channels'])->firstWhere('campaign', 'CTWA-Cold');

        $this->assertSame(1, $named['orders']);
        $this->assertSame(0, $cold['orders'], 'the cold campaign sold nothing and must not borrow the other one\'s sale');
        $this->assertSame('900.00', $named['revenue_ghs']);
        $this->assertSame('0.00', $cold['revenue_ghs']);
    }

    public function test_channel_revenue_never_exceeds_the_revenue_actually_taken(): void
    {
        \App\Models\AdSpend::create([
            'platform' => 'meta', 'campaign' => 'A', 'ad_group' => '', 'keyword' => '',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-28',
            'impressions' => 100, 'clicks' => 10, 'spend_usd' => '10.00',
            'currency' => 'USD', 'imported_at' => CarbonImmutable::now('UTC'),
        ]);
        \App\Models\AdSpend::create([
            'platform' => 'meta', 'campaign' => 'B', 'ad_group' => '', 'keyword' => '',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-28',
            'impressions' => 100, 'clicks' => 10, 'spend_usd' => '10.00',
            'currency' => 'USD', 'imported_at' => CarbonImmutable::now('UTC'),
        ]);

        foreach ([1, 2, 3] as $i) {
            AttributionEvent::create([
                'woo_attr_id' => $i, 'created_at' => '2026-07-10 10:00:00', 'updated_at' => '2026-07-10 10:00:00',
                'status' => 'converted', 'converted_at' => '2026-07-10 11:00:00', 'conv_value_ghs' => '500.00',
                'utm_source' => 'meta',   // platform known, campaign NOT known
                'payload_hash' => hash('sha256', (string) $i), 'synced_at' => CarbonImmutable::now('UTC'),
            ]);
        }

        $picture = (new \App\Services\Ads\JoinEngine)->build('2026-07-01', '2026-07-28');

        $attributed = 0.0;
        foreach ($picture['channels'] as $c) {
            $attributed += (float) $c['revenue_ghs'];
        }

        // 1500 taken. Anything above that means a sale was counted twice.
        $this->assertSame(1500.0, $attributed);
        $this->assertSame('1500.00', $picture['totals']['revenue_ghs']);
    }

    public function test_ambiguous_traffic_is_labelled_unknown_rather_than_shared_out(): void
    {
        \App\Models\AdSpend::create([
            'platform' => 'meta', 'campaign' => 'A', 'ad_group' => '', 'keyword' => '',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-28',
            'impressions' => 100, 'clicks' => 10, 'spend_usd' => '10.00',
            'currency' => 'USD', 'imported_at' => CarbonImmutable::now('UTC'),
        ]);
        \App\Models\AdSpend::create([
            'platform' => 'meta', 'campaign' => 'B', 'ad_group' => '', 'keyword' => '',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-28',
            'impressions' => 100, 'clicks' => 10, 'spend_usd' => '10.00',
            'currency' => 'USD', 'imported_at' => CarbonImmutable::now('UTC'),
        ]);

        AttributionEvent::create([
            'woo_attr_id' => 1, 'created_at' => '2026-07-10 10:00:00', 'updated_at' => '2026-07-10 10:00:00',
            'status' => 'converted', 'converted_at' => '2026-07-10 11:00:00', 'conv_value_ghs' => '500.00',
            'utm_source' => 'meta',
            'payload_hash' => hash('sha256', 'x'), 'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        $picture = (new \App\Services\Ads\JoinEngine)->build('2026-07-01', '2026-07-28');
        $unknown = collect($picture['channels'])->firstWhere('campaign', '(campaign not identified)');

        // A number split on a guess is worse than one labelled unknown: it
        // looks like knowledge.
        $this->assertNotNull($unknown);
        $this->assertSame(1, $unknown['orders']);
        $this->assertSame('unassigned', $unknown['attribution_confidence']);
    }

    public function test_a_platform_running_a_single_campaign_can_still_claim_its_traffic(): void
    {
        // With only one campaign there is nothing to be ambiguous about, so
        // refusing to attribute would be uselessly pedantic.
        \App\Models\AdSpend::create([
            'platform' => 'tiktok', 'campaign' => 'Promote-blender', 'ad_group' => '', 'keyword' => '',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-28',
            'impressions' => 100, 'clicks' => 10, 'spend_usd' => '18.50',
            'currency' => 'USD', 'imported_at' => CarbonImmutable::now('UTC'),
        ]);

        AttributionEvent::create([
            'woo_attr_id' => 1, 'created_at' => '2026-07-10 10:00:00', 'updated_at' => '2026-07-10 10:00:00',
            'status' => 'converted', 'converted_at' => '2026-07-10 11:00:00', 'conv_value_ghs' => '300.00',
            'utm_source' => 'tiktok',
            'payload_hash' => hash('sha256', 'y'), 'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        $picture = (new \App\Services\Ads\JoinEngine)->build('2026-07-01', '2026-07-28');
        $c = collect($picture['channels'])->firstWhere('campaign', 'Promote-blender');

        $this->assertSame(1, $c['orders']);
        $this->assertSame('300.00', $c['revenue_ghs']);
    }

    /* ---------------- the milestone ladder ---------------- */

    private function seedConversions(int $n, bool $withPhone = true, bool $exported = false, int $offset = 0): void
    {
        for ($i = $offset; $i < $offset + $n; $i++) {
            AttributionEvent::create([
                'woo_attr_id' => 1000 + $i,
                'created_at' => CarbonImmutable::now('UTC')->subDays(5),
                'updated_at' => CarbonImmutable::now('UTC')->subDays(5),
                'status' => 'converted',
                'converted_at' => CarbonImmutable::now('UTC')->subDays(5),
                'conv_value_ghs' => '450.00',
                'click_id' => 'CjwK'.$i,
                'click_type' => 'gclid',
                'ref' => 'WG-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'cust_phone_sha256' => $withPhone ? hash('sha256', '+23354214802'.$i) : null,
                'exported' => $exported,
                'payload_hash' => hash('sha256', (string) $i),
                'synced_at' => CarbonImmutable::now('UTC'),
            ]);
        }
    }

    public function test_gate_2_stamps_at_thirty_conversions_with_evidence(): void
    {
        (new MilestoneSeeder)->run();

        $this->seedConversions(29);
        (new MilestoneEvaluator)->evaluate();
        $this->assertNull(Milestone::where('gate_code', 'GATE2')->first()->reached_at, '29 is not 30');

        AttributionEvent::create([
            'woo_attr_id' => 9999,
            'created_at' => CarbonImmutable::now('UTC')->subDay(),
            'updated_at' => CarbonImmutable::now('UTC')->subDay(),
            'status' => 'converted',
            'converted_at' => CarbonImmutable::now('UTC')->subDay(),
            'conv_value_ghs' => '450.00',
            'click_id' => 'CjwKlast',
            'click_type' => 'gclid',
            'ref' => 'WG-LAST',
            'cust_phone_sha256' => hash('sha256', '+233542148099'),
            'payload_hash' => hash('sha256', 'last'),
            'synced_at' => CarbonImmutable::now('UTC'),
        ]);

        $result = (new MilestoneEvaluator)->evaluate();
        $gate = Milestone::where('gate_code', 'GATE2')->first();

        $this->assertNotNull($gate->reached_at);
        $this->assertSame(30, $gate->evidence_json['conversions_30d']);
        $this->assertStringContainsString('Target CPA', $gate->decision_text);
        $this->assertContains('GATE2', array_column($result['newly_reached'], 'code'));
    }

    public function test_a_reached_gate_does_not_fire_twice(): void
    {
        (new MilestoneSeeder)->run();
        $this->seedConversions(30);

        $first = (new MilestoneEvaluator)->evaluate();
        $second = (new MilestoneEvaluator)->evaluate();

        $this->assertContains('GATE2', array_column($first['newly_reached'], 'code'));
        $this->assertSame([], $second['newly_reached'], 'a milestone is a fact about history, not a repeating alarm');
    }

    public function test_an_overdue_export_fires_the_guardrail(): void
    {
        (new MilestoneSeeder)->run();

        // Something was exported long ago, and there are unexported sales now.
        $this->seedConversions(3, true, true);
        AttributionEvent::query()->update(['updated_at' => CarbonImmutable::now('UTC')->subDays(12)]);
        $this->seedConversions(2, true, false, offset: 500);

        $result = (new MilestoneEvaluator)->evaluate();

        $this->assertContains('G-A', array_column($result['active_guardrails'], 'code'));
    }

    public function test_the_export_guardrail_stays_quiet_before_the_first_export(): void
    {
        // Nagging about an overdue upload before anything has ever been
        // exported teaches the owner to ignore the warnings.
        (new MilestoneSeeder)->run();
        $this->seedConversions(3, true, false);

        $result = (new MilestoneEvaluator)->evaluate();

        $this->assertNotContains('G-A', array_column($result['active_guardrails'], 'code'));
    }

    public function test_a_thin_match_rate_fires_its_guardrail(): void
    {
        (new MilestoneSeeder)->run();
        $this->seedConversions(10, withPhone: false);

        $result = (new MilestoneEvaluator)->evaluate();

        $this->assertContains('G-C', array_column($result['active_guardrails'], 'code'));
    }

    public function test_a_guardrail_clears_when_its_condition_stops_being_true(): void
    {
        (new MilestoneSeeder)->run();
        $this->seedConversions(10, withPhone: false);
        (new MilestoneEvaluator)->evaluate();

        $this->assertNotNull(Milestone::where('gate_code', 'G-C')->first()->reached_at);

        AttributionEvent::query()->update(['cust_phone_sha256' => hash('sha256', '+233542148020')]);
        $result = (new MilestoneEvaluator)->evaluate();

        $this->assertNotContains('G-C', array_column($result['active_guardrails'], 'code'));
        $this->assertNull(Milestone::where('gate_code', 'G-C')->first()->reached_at);
    }
}
