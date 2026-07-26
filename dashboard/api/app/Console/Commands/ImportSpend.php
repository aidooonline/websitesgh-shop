<?php

namespace App\Console\Commands;

use App\Services\Ads\SpendImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportSpend extends Command
{
    protected $signature = 'wgh:import
        {file : Path to the platform export CSV}
        {--platform= : google, meta or tiktok. Detected from the file if omitted.}
        {--from= : Period start YYYY-MM-DD, if the export states none}
        {--to= : Period end YYYY-MM-DD, if the export states none}
        {--judge : Run the join and the decision engine straight after}';

    protected $description = 'Import an ad platform spend export';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        try {
            $result = (new SpendImporter)->import(
                $file,
                $this->option('platform') ?: null,
                $this->option('from') ?: null,
                $this->option('to') ?: null,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Imported '.$result['rows'].' rows from '.$result['platform'].'.');

        $this->table(['Metric', 'Value'], [
            ['New rows', $result['inserted']],
            ['Restated rows', $result['updated']],
            ['Already identical', $result['unchanged']],
            ['Skipped (no campaign)', $result['skipped']],
            ['Total in the FILE (USD)', $result['file_total']],
            ['Total now stored for '.$result['platform'].' (USD)', $result['stored_total']],
        ]);

        foreach ($result['notes'] as $note) {
            $this->line('  <fg=yellow>note</> '.$note);
        }

        if ($result['unchanged'] === $result['rows']) {
            $this->line('  <fg=green>This file had already been imported. Nothing changed, and the total did not move.</>');
        }

        if ($this->option('judge')) {
            $this->newLine();

            return $this->call('wgh:judge');
        }

        $this->newLine();
        $this->line('Run <options=bold>php artisan wgh:judge</> to join this against sales and produce verdicts.');

        return self::SUCCESS;
    }
}
