<?php

namespace App\Services\Decisions;

use App\Models\Decision;
use Carbon\CarbonImmutable;

/**
 * The compounding brain.
 *
 * A verdict tells you what to do with one keyword. A pattern tells you what to
 * stop doing across the whole account, which is worth far more: it stops the
 * next fifty keywords being built the same wrong way.
 *
 * The detector looks for traits shared across the killed set that are NOT
 * shared across the kept set. A trait present in both proves nothing, and
 * reporting it would be the classic mistake of finding a pattern in noise. Each
 * finding therefore carries both sides of the comparison, so the owner can see
 * why it is a pattern rather than a coincidence.
 */
class PatternDetector
{
    /** Below this, a "pattern" is just a couple of keywords having a bad week. */
    private const MIN_SET = 3;

    /** The killed set must be this much more likely to carry the trait. */
    private const MIN_LIFT = 2.0;

    /**
     * @param  list<array<string, mixed>>  $verdicts
     * @return list<array<string, mixed>>
     */
    public function detect(array $verdicts): array
    {
        $killed = array_values(array_filter($verdicts, fn ($v) => $v['verdict'] === 'kill'));
        $kept = array_values(array_filter($verdicts, fn ($v) => $v['verdict'] === 'keep'));
        $leaking = array_values(array_filter($verdicts, fn ($v) => $v['verdict'] === 'fix'));

        $found = [];

        if (count($killed) >= self::MIN_SET) {
            foreach ($this->traits() as $name => $test) {
                $inKilled = $this->share($killed, $test);
                $inKept = $this->share($kept, $test);

                if ($inKilled < 0.6) {
                    continue;   // Not even a majority of the killed set.
                }

                // A trait that is just as common in the winners is not a
                // pattern, it is a description of the account.
                $lift = $inKept > 0 ? $inKilled / $inKept : ($inKilled > 0 ? INF : 0);

                if ($lift < self::MIN_LIFT) {
                    continue;
                }

                $n = (int) round($inKilled * count($killed));

                $found[] = [
                    'pattern' => $name,
                    'observation' => sprintf(
                        '%d of %d killed keywords %s, against %d%% of the keepers.',
                        $n, count($killed), $name, (int) round($inKept * 100)
                    ),
                    'evidence' => [
                        'killed_total' => count($killed),
                        'killed_with_trait' => $n,
                        'kept_total' => count($kept),
                        'kept_share' => round($inKept, 3),
                        'lift' => is_infinite($lift) ? 'only in the killed set' : round($lift, 2),
                        'examples' => array_slice(array_map(
                            fn ($v) => $v['entity_ref'],
                            array_values(array_filter($killed, $test))
                        ), 0, 5),
                    ],
                ];
            }
        }

        // A leaking set large enough to be systemic is itself a finding, and a
        // more urgent one than any kill pattern: it is demand already paid for.
        if (count($leaking) >= self::MIN_SET) {
            $wasted = 0.0;
            foreach ($leaking as $v) {
                $wasted += (float) ($v['row']['spend_usd'] ?? 0);
            }

            $found[] = [
                'pattern' => 'demand is arriving and leaving without buying',
                'observation' => sprintf(
                    '%d keywords brought people who opened WhatsApp and did not buy, costing $%s. That is a page or price problem, not a targeting one.',
                    count($leaking), number_format($wasted, 2)
                ),
                'evidence' => [
                    'leaking_keywords' => count($leaking),
                    'spend_at_risk_usd' => number_format($wasted, 2),
                    'examples' => array_slice(array_map(fn ($v) => $v['entity_ref'], $leaking), 0, 5),
                ],
            ];
        }

        foreach ($found as $f) {
            Decision::create([
                'dimension' => 'keyword',
                'entity_ref' => 'PATTERN: '.$f['pattern'],
                'verdict' => 'pattern',
                'reason' => $f['observation'],
                'suggested_action' => $this->actionFor($f['pattern']),
                'evidence_json' => $f['evidence'],
                'source' => 'engine',
                'created_at' => CarbonImmutable::now('UTC'),
            ]);
        }

        return $found;
    }

    /**
     * The traits worth testing, each a predicate over a verdict row.
     *
     * These are shaped by this specific market: a Ghanaian shopper searching
     * "blender price accra" is closer to buying than one searching "blender",
     * and that difference is exactly what the engine should learn.
     *
     * @return array<string, callable(array<string, mixed>): bool>
     */
    private function traits(): array
    {
        return [
            'were broad match' => fn ($v) => ($v['row']['match_type'] ?? '') === 'b',
            'were exact match' => fn ($v) => ($v['row']['match_type'] ?? '') === 'e',
            'were bare category terms with no product named' => function ($v) {
                $k = (string) ($v['row']['keyword'] ?? '');

                return str_word_count($k) <= 2 && ! preg_match('/\d/', $k);
            },
            'carried no buying word like price, buy or cost' => function ($v) {
                $k = (string) ($v['row']['keyword'] ?? '');

                return ! preg_match('/\b(price|prices|buy|cost|cheap|deal|sale|order)\b/i', $k);
            },
            'named no location' => function ($v) {
                $k = (string) ($v['row']['keyword'] ?? '');

                return ! preg_match('/\b(accra|ghana|kumasi|tema|takoradi|east legon|spintex)\b/i', $k);
            },
            'never produced a single WhatsApp tap' => fn ($v) => (int) ($v['row']['taps'] ?? 0) === 0,
        ];
    }

    private function actionFor(string $pattern): string
    {
        return match (true) {
            str_contains($pattern, 'broad match') => 'Stop adding broad match. Move the surviving broad keywords to phrase and let the search terms report tell you what to add as exact.',
            str_contains($pattern, 'bare category') => 'Stop bidding on bare category words. Bid on specific products, and on terms that name a price or a place.',
            str_contains($pattern, 'buying word') => 'Weight new keywords towards price, buy and cost. People who type those are further down the decision than people typing the product alone.',
            str_contains($pattern, 'no location') => 'Add Accra and Ghana variants. Local intent converts here and untargeted national terms do not.',
            str_contains($pattern, 'WhatsApp tap') => 'These never reached the order button at all, so the problem is upstream of the page. Check the landing page matches the search intent.',
            str_contains($pattern, 'leaving without buying') => 'Fix the page before spending another cedi on new keywords. This traffic is already paid for and it is walking away at the last step.',
            default => 'Review the examples and decide whether to keep building keywords this way.',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $set
     * @param  callable(array<string, mixed>): bool  $test
     */
    private function share(array $set, callable $test): float
    {
        if (! $set) {
            return 0.0;
        }

        return count(array_filter($set, $test)) / count($set);
    }
}
