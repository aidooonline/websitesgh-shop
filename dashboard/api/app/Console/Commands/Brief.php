<?php

namespace App\Console\Commands;

use App\Models\AgentBriefing;
use App\Services\Agent\BriefingPack;
use App\Services\Agent\PackWriter;
use App\Services\Agent\ResponseParser;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * The manual briefing loop.
 *
 * Sprint 6 will send the same payload to an API automatically. This is that
 * loop with a person carrying the file instead: export a pack, hand it to any
 * analyst or model, bring the answer back. No API key, no per-run cost, and the
 * owner reads every word before it is stored.
 *
 * It is the same reasoning that settled the Google Ads question: export a file,
 * decide outside, bring the decision back, rather than handing software direct
 * control before it has earned trust.
 */
class Brief extends Command
{
    protected $signature = 'wgh:brief
        {--from= : Period start YYYY-MM-DD. Defaults to 30 days ago.}
        {--to= : Period end YYYY-MM-DD. Defaults to today.}
        {--export : Write the briefing pack to send out}
        {--import= : Read a briefing response back in}
        {--show : Show the most recent stored briefing}
        {--dir= : Where to write the pack. Defaults to storage/app/briefings.}';

    protected $description = 'Export the consolidated picture for analysis, and import the answer';

    public function handle(): int
    {
        if ($this->option('show')) {
            return $this->show();
        }

        if ($this->option('import')) {
            return $this->import((string) $this->option('import'));
        }

        return $this->export();
    }

    private function export(): int
    {
        $to = $this->option('to') ?: CarbonImmutable::now('UTC')->toDateString();
        $from = $this->option('from') ?: CarbonImmutable::parse($to)->subDays(30)->toDateString();
        $dir = $this->option('dir') ?: storage_path('app/briefings');

        try {
            $pack = (new BriefingPack)->build($from, $to);
            $files = (new PackWriter)->write($pack, $dir);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $t = $pack['totals'];

        $this->info("Briefing pack for {$from} to {$to}");
        $this->table(['', ''], [
            ['Spend (USD)', $t['spend_usd']],
            ['Revenue (GHS)', $t['revenue_ghs']],
            ['Sales', $t['orders']],
            ['Keywords included', count($pack['keywords'])],
            ['Channels included', count($pack['channels'])],
            ['Unmatched spend (USD)', $t['unmatched_spend_usd']],
        ]);

        $this->newLine();
        $this->line('  <options=bold>Send this one:</>  '.$files['md']);
        $this->line('  For a spreadsheet: '.$files['csv']);
        $this->newLine();
        $this->line('The markdown file explains itself. It carries the goal, the constraints, the');
        $this->line('numbers and the exact reply format, so it can be handed to anyone without a');
        $this->line('covering note. When the answer comes back:');
        $this->newLine();
        $this->line('  php artisan wgh:brief --import=path/to/response.md');

        if ((float) $t['spend_usd'] === 0.0) {
            $this->newLine();
            $this->line('  <fg=yellow>No spend in this period</>, so there is little to advise on yet.');
            $this->line('  Import an ad export first: php artisan wgh:import file.csv --judge');
        }

        return self::SUCCESS;
    }

    private function import(string $path): int
    {
        try {
            $parsed = (new ResponseParser)->parse($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $briefing = AgentBriefing::create([
            'trigger' => 'manual',
            'created_at' => CarbonImmutable::now('UTC'),
            // Recorded honestly as a human-carried briefing rather than dressed
            // up as an automated one, so the track record stays auditable.
            'model_used' => 'manual (file import)',
            'period_covered' => $parsed['period'] ?? 'unstated',
            'summary_md' => $parsed['summary_md'],
            'top_action' => $parsed['top_action'],
            'evidence_json' => $parsed['sections'],
            'tokens_cost' => 0,
        ]);

        $this->info('Briefing stored, id '.$briefing->id.'.');
        $this->line('  Period: '.($parsed['period'] ?? 'not stated in the file'));
        $this->line('  Sections read: '.implode(', ', array_keys($parsed['sections'])));
        $this->newLine();
        $this->line('  <options=bold>The one move</>');

        foreach (explode("\n", $parsed['top_action']) as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }

    private function show(): int
    {
        $briefing = AgentBriefing::orderByDesc('created_at')->first();

        if (! $briefing) {
            $this->line('No briefing stored yet. Run: php artisan wgh:brief --export');

            return self::SUCCESS;
        }

        $this->info("Briefing {$briefing->id}, {$briefing->period_covered}, via {$briefing->model_used}");
        $this->newLine();
        $this->line($briefing->summary_md);

        return self::SUCCESS;
    }
}
