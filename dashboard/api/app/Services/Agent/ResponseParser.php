<?php

namespace App\Services\Agent;

use RuntimeException;

/**
 * Reads a briefing response back into the system.
 *
 * FORGIVING ON PURPOSE
 * This file is written by a person or a model, possibly copied out of a chat
 * window, possibly with the heading levels changed or the sections reordered.
 * A strict parser would reject perfectly good advice over a stray hash
 * character and the owner would stop using the loop. So headings are matched
 * loosely: any level, any case, and by the words that matter rather than the
 * exact phrase.
 *
 * STRICT ABOUT ONE THING
 * There must be an action. A briefing with no "do this now" is an essay, and
 * the whole point of the loop is to end with one move. It is rejected with an
 * explanation rather than stored as an empty recommendation that later reads
 * like the system had nothing to say.
 */
class ResponseParser
{
    /**
     * Heading keyword to canonical section, longest and most specific first.
     *
     * @var array<string, list<string>>
     */
    private const SECTIONS = [
        'top_action' => ['do this now', 'do now', 'the one move', 'action now', 'next action', 'recommended action'],
        'risk' => ['risk in doing', 'the risk', 'risks', 'what could go wrong', 'trade-off', 'tradeoff'],
        'win' => ['biggest win', 'what is working', 'wins', 'working well'],
        'leak' => ['biggest leak', 'leak', 'losing money', 'waste'],
        'keywords' => ['keyword notes', 'keywords', 'per keyword'],
        'unknowns' => ['cannot tell', 'unknown', 'what you would need', 'missing data', 'blind spots'],
    ];

    /**
     * @return array{period:?string, sections:array<string,string>, summary_md:string, top_action:string}
     */
    public function parse(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}");
        }

        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        // A response pasted out of a chat may arrive wrapped in a code fence.
        $raw = preg_replace('/^\s*```[a-z]*\n(.*)\n```\s*$/s', '$1', $raw) ?? $raw;

        if (trim($raw) === '') {
            throw new RuntimeException('That file is empty.');
        }

        $period = null;
        if (preg_match('/period\s*:?\s*(\d{4}-\d{2}-\d{2})\s*(?:to|-|\x{2013})\s*(\d{4}-\d{2}-\d{2})/iu', $raw, $m)) {
            $period = $m[1].' to '.$m[2];
        }

        $sections = [];
        $current = null;
        $buffer = [];

        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*$/', $line, $m)) {
                if ($current !== null) {
                    $sections[$current] = trim(implode("\n", $buffer));
                }

                $current = $this->canonical($m[1]);
                $buffer = [];

                continue;
            }

            if ($current !== null) {
                $buffer[] = $line;
            }
        }

        if ($current !== null) {
            $sections[$current] = trim(implode("\n", $buffer));
        }

        $sections = array_filter($sections, fn ($v) => $v !== '');

        $action = $sections['top_action'] ?? '';

        if (trim($action) === '') {
            throw new RuntimeException(
                'That response has no "Do this now" section, so there is no action to record. '
                .'A briefing that does not end in one move is an essay. Add the heading and re-import; '
                .'the template is at the bottom of the briefing pack.'
            );
        }

        if (count($sections) < 2) {
            throw new RuntimeException(
                'Only one section was recognised, which usually means the headings were lost in copying. '
                .'Each section needs its own markdown heading, for example "## Biggest win".'
            );
        }

        return [
            'period' => $period,
            'sections' => $sections,
            'summary_md' => trim($raw),
            'top_action' => trim($action),
        ];
    }

    /**
     * Map a heading to a canonical section name, or keep it as an extra.
     */
    private function canonical(string $heading): string
    {
        $h = mb_strtolower(trim($heading, " \t#*_:"));

        foreach (self::SECTIONS as $key => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($h, $needle)) {
                    return $key;
                }
            }
        }

        // Unrecognised headings are kept rather than dropped: an analyst who
        // adds a section worth reading should not have it silently deleted.
        return 'extra_'.preg_replace('/[^a-z0-9]+/', '_', $h);
    }
}
