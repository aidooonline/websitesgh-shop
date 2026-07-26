<?php

namespace Database\Seeders;

use App\Models\Milestone;
use Illuminate\Database\Seeder;

/**
 * The threshold ladder.
 *
 * The point of the ladder is that the owner never has to remember when to
 * switch bidding or when there is enough data to judge anything. The engine
 * watches the numbers and raises a guided decision when a gate is crossed:
 * what changed, what to do now, and why.
 *
 * Seeded here in sprint 1 so sprint 3 evaluates a ladder that already exists.
 * updateOrCreate on gate_code, so re-seeding never duplicates a gate and never
 * wipes a reached_at that has already been stamped.
 */
class MilestoneSeeder extends Seeder
{
    public function run(): void
    {
        $gates = [
            [
                'gate_code' => 'GATE0',
                'gate_label' => 'First conversion tracked',
                'threshold_json' => ['metric' => 'offline_conversions_total', 'op' => '>=', 'value' => 1],
                'decision_text' => 'Attribution is working end to end. Keep exporting every confirmed sale.',
                'sort_order' => 0,
            ],
            [
                'gate_code' => 'GATE1',
                'gate_label' => '15 offline conversions in a rolling 30 days',
                'threshold_json' => ['metric' => 'offline_conversions_30d', 'op' => '>=', 'value' => 15],
                'decision_text' => 'Halfway to the Smart Bidding threshold. Stay on Max Conversions and keep exporting daily to weekly.',
                'sort_order' => 1,
            ],
            [
                'gate_code' => 'GATE2',
                'gate_label' => '30 offline conversions in a rolling 30 days',
                'threshold_json' => ['metric' => 'offline_conversions_30d', 'op' => '>=', 'value' => 30],
                'decision_text' => 'Smart Bidding can now stabilise. Switch bidding to Target CPA, starting at your current cost per delivered order. Google now has enough confirmed sales to optimise for buyers rather than tappers.',
                'sort_order' => 2,
            ],
            [
                'gate_code' => 'GATE3',
                'gate_label' => '50 offline conversions in a rolling 30 days',
                'threshold_json' => ['metric' => 'offline_conversions_30d', 'op' => '>=', 'value' => 50],
                'decision_text' => 'Enough volume for Target ROAS if you want to optimise by value. Consider raising budget on the KEEP keywords; the loop is proven.',
                'sort_order' => 3,
            ],
            [
                'gate_code' => 'GATE4',
                'gate_label' => 'Match rate at or above 60 percent, sustained',
                'threshold_json' => ['metric' => 'match_rate_30d', 'op' => '>=', 'value' => 0.60],
                'decision_text' => 'The gclid and phone match rate is strong, so the signal reaching Google is high quality. Safe to lean harder on automated bidding.',
                'sort_order' => 4,
            ],

            // Guardrails fire whenever their condition is true, not once. They
            // protect the offline-conversion loop, which is the growth engine.
            [
                'gate_code' => 'G-A',
                'gate_label' => 'Export overdue by more than 7 days',
                'threshold_json' => ['metric' => 'days_since_export', 'op' => '>', 'value' => 7],
                'decision_text' => 'The conversion upload is overdue and Smart Bidding is going stale. Export now.',
                'is_guardrail' => true,
                'sort_order' => 10,
            ],
            [
                'gate_code' => 'G-B',
                'gate_label' => 'Rolling 30 day conversions fell back under 30',
                'threshold_json' => ['metric' => 'offline_conversions_30d', 'op' => '<', 'value' => 30, 'after' => 'GATE2'],
                'decision_text' => 'Volume dropped below the Smart Bidding floor. Consider reverting to Max Conversions until it recovers, or widen budget and keywords.',
                'is_guardrail' => true,
                'sort_order' => 11,
            ],
            [
                'gate_code' => 'G-C',
                'gate_label' => 'Match rate below 40 percent',
                'threshold_json' => ['metric' => 'match_rate_30d', 'op' => '<', 'value' => 0.40],
                'decision_text' => 'Match rate is too thin to trust. Check gclid capture and phone normalization before relying on automated bidding.',
                'is_guardrail' => true,
                'sort_order' => 12,
            ],
            [
                'gate_code' => 'G-D',
                'gate_label' => 'Cart to WhatsApp rate dropped sharply',
                'threshold_json' => ['metric' => 'cart_to_whatsapp_drop', 'op' => '>', 'value' => 0.30],
                'decision_text' => 'People add to cart but stop before messaging. The cart or the WhatsApp step is leaking; check it. These people are also a retargeting audience, so run a win-back campaign.',
                'is_guardrail' => true,
                'sort_order' => 13,
            ],
        ];

        foreach ($gates as $gate) {
            Milestone::updateOrCreate(
                ['gate_code' => $gate['gate_code']],
                [
                    'gate_label' => $gate['gate_label'],
                    'threshold_json' => $gate['threshold_json'],
                    'decision_text' => $gate['decision_text'],
                    'is_guardrail' => $gate['is_guardrail'] ?? false,
                    'sort_order' => $gate['sort_order'],
                ]
            );
        }
    }
}
