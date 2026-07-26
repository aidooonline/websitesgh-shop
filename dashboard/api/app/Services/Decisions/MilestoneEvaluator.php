<?php

namespace App\Services\Decisions;

use App\Models\AttributionEvent;
use App\Models\Decision;
use App\Models\Milestone;
use Carbon\CarbonImmutable;

/**
 * The threshold ladder.
 *
 * The point: the owner should never have to remember "when do I switch
 * bidding" or "is there enough data yet". The system watches the numbers and
 * raises a guided decision when a gate is crossed, saying what changed, what to
 * do now, and why.
 *
 * TWO KINDS OF GATE, AND THEY BEHAVE DIFFERENTLY
 * Progress gates (GATE 0 to 4) fire ONCE and stay reached. Crossing 30
 * conversions is a fact about the account's history, and un-reaching it when
 * a quiet week drops the rolling count would turn a milestone into a flapping
 * alarm.
 *
 * Guardrail gates (G-A to G-D) fire WHENEVER their condition is true and clear
 * when it stops being true, because they are protecting something live. An
 * overdue export is only worth telling you about while it is still overdue.
 *
 * The offline conversion loop is treated as first order throughout. Feeding
 * confirmed sales back to Google is what makes the SAME budget produce more
 * sales over time, so anything throttling that loop outranks anything else the
 * engine has to say.
 */
class MilestoneEvaluator
{
    public function evaluate(): array
    {
        $now = CarbonImmutable::now('UTC');
        $since = $now->subDays(30);

        $conversions30d = AttributionEvent::where('status', 'converted')
            ->where('converted_at', '>=', $since)
            ->count();

        $conversionsTotal = AttributionEvent::where('status', 'converted')->count();

        // Match rate: of the confirmed sales that could be uploaded, how many
        // carry BOTH a click id and a hashable phone. That pair is what Google
        // matches on, and a thin rate is invisible until it has already cost
        // weeks of bidding performance.
        $uploadable = AttributionEvent::where('status', 'converted')
            ->whereNotNull('click_id')
            ->count();

        $withPhone = AttributionEvent::where('status', 'converted')
            ->whereNotNull('click_id')
            ->whereNotNull('cust_phone_sha256')
            ->count();

        $matchRate = $uploadable > 0 ? $withPhone / $uploadable : 0.0;

        $lastExport = AttributionEvent::where('exported', true)->max('updated_at');
        $daysSinceExport = $lastExport
            ? CarbonImmutable::parse($lastExport)->diffInDays($now)
            : null;

        $unexported = AttributionEvent::where('status', 'converted')
            ->where('exported', false)
            ->whereNotNull('click_id')
            ->count();

        // Cart to WhatsApp: people who added to the basket and then did not
        // message. They are the most recoverable audience the shop has.
        $carts = AttributionEvent::where('status', 'cart')->where('created_at', '>=', $since)->count();
        $taps = AttributionEvent::where('placement', 'cart_whatsapp')->where('created_at', '>=', $since)->count();
        $cartToTap = $carts > 0 ? $taps / $carts : null;

        $facts = [
            'conversions_30d' => $conversions30d,
            'conversions_total' => $conversionsTotal,
            'match_rate' => round($matchRate, 3),
            'uploadable' => $uploadable,
            'with_phone' => $withPhone,
            'days_since_export' => $daysSinceExport,
            'unexported_conversions' => $unexported,
            'cart_to_whatsapp_rate' => $cartToTap !== null ? round($cartToTap, 3) : null,
            'evaluated_at' => $now->toIso8601String(),
        ];

        $floor = (int) config('wgh.loop.smart_bidding_floor');
        $overdue = (int) config('wgh.loop.export_overdue_days');
        $strong = (float) config('wgh.loop.match_rate_strong');
        $thin = (float) config('wgh.loop.match_rate_thin');

        $progress = [
            'GATE0' => $conversionsTotal >= 1,
            'GATE1' => $conversions30d >= 15,
            'GATE2' => $conversions30d >= $floor,
            'GATE3' => $conversions30d >= 50,
            'GATE4' => $uploadable > 0 && $matchRate >= $strong,
        ];

        $guardrails = [
            // Overdue only counts once something has been exported at least
            // once. Nagging about an overdue upload before the first sale
            // exists is noise that teaches the owner to ignore the warnings.
            'G-A' => $daysSinceExport !== null && $daysSinceExport > $overdue && $unexported > 0,
            'G-B' => $this->reached('GATE2') && $conversions30d < $floor,
            'G-C' => $uploadable >= 5 && $matchRate < $thin,
            'G-D' => $carts >= 10 && $cartToTap !== null && $cartToTap < 0.30,
        ];

        $newlyReached = [];
        $active = [];

        foreach ($progress as $code => $isMet) {
            $gate = Milestone::where('gate_code', $code)->first();

            if (! $gate || ! $isMet || $gate->reached_at !== null) {
                continue;
            }

            $gate->forceFill(['reached_at' => $now, 'evidence_json' => $facts])->save();

            Decision::create([
                'dimension' => 'channel',
                'entity_ref' => 'MILESTONE '.$code,
                'verdict' => 'gate',
                'reason' => $gate->gate_label.' reached.',
                'suggested_action' => $gate->decision_text,
                'evidence_json' => $facts,
                'source' => 'engine',
                'created_at' => $now,
            ]);

            $newlyReached[] = ['code' => $code, 'label' => $gate->gate_label, 'decision' => $gate->decision_text];
        }

        foreach ($guardrails as $code => $isFiring) {
            $gate = Milestone::where('gate_code', $code)->first();

            if (! $gate) {
                continue;
            }

            if ($isFiring) {
                // Re-stamped every time it fires, because a guardrail is about
                // now, not about the first time it ever happened.
                $gate->forceFill(['reached_at' => $now, 'evidence_json' => $facts])->save();

                Decision::create([
                    'dimension' => 'channel',
                    'entity_ref' => 'GUARDRAIL '.$code,
                    'verdict' => 'guardrail',
                    'reason' => $gate->gate_label,
                    'suggested_action' => $gate->decision_text,
                    'evidence_json' => $facts,
                    'source' => 'engine',
                    'created_at' => $now,
                ]);

                $active[] = ['code' => $code, 'label' => $gate->gate_label, 'decision' => $gate->decision_text];
            } elseif ($gate->reached_at !== null) {
                $gate->forceFill(['reached_at' => null])->save();
            }
        }

        return [
            'facts' => $facts,
            'newly_reached' => $newlyReached,
            'active_guardrails' => $active,
            'next_gate' => $this->nextGate($conversions30d, $floor),
        ];
    }

    private function reached(string $code): bool
    {
        return Milestone::where('gate_code', $code)->whereNotNull('reached_at')->exists();
    }

    /**
     * The next unreached progress gate, with how far away it is.
     *
     * @return array{code:string, label:string, progress:string}|null
     */
    private function nextGate(int $conversions30d, int $floor): ?array
    {
        $ladder = [
            'GATE0' => 1,
            'GATE1' => 15,
            'GATE2' => $floor,
            'GATE3' => 50,
        ];

        foreach ($ladder as $code => $target) {
            $gate = Milestone::where('gate_code', $code)->first();

            if ($gate && $gate->reached_at === null) {
                return [
                    'code' => $code,
                    'label' => $gate->gate_label,
                    'progress' => $conversions30d.' / '.$target,
                ];
            }
        }

        return null;
    }
}
