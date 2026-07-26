<?php

namespace App\Console\Commands;

use App\Models\FxRate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Record the GHS per USD rate for a date.
 *
 * Ad spend is USD, sales are GHS. Every comparison between them goes through a
 * dated row here, never through today's rate applied to last quarter, because
 * the cedi moves enough that doing so rewrites history and makes a month that
 * was profitable look like a loss.
 */
class AddFxRate extends Command
{
    protected $signature = 'wgh:fx
        {rate? : GHS per 1 USD, e.g. 11.85}
        {--date= : The date this rate applies to, YYYY-MM-DD. Defaults to today in Accra.}
        {--source=manual : Where the rate came from, e.g. bog}
        {--list : Show the most recent rates and exit}';

    protected $description = 'Record the GHS per USD rate for a date';

    public function handle(): int
    {
        if ($this->option('list')) {
            $rows = FxRate::orderByDesc('rate_date')->limit(20)->get();

            if ($rows->isEmpty()) {
                $this->warn('No rates recorded yet. Nothing can convert USD spend to GHS until there is one.');

                return self::SUCCESS;
            }

            $this->table(
                ['Date', 'GHS per USD', 'Source'],
                $rows->map(fn ($r) => [$r->rate_date->toDateString(), $r->ghs_per_usd, $r->source])->all()
            );

            return self::SUCCESS;
        }

        $rate = $this->argument('rate') ?? $this->ask('GHS per 1 USD');

        if (! is_numeric($rate) || (float) $rate <= 0) {
            $this->error('The rate must be a positive number, for example 11.85.');

            return self::FAILURE;
        }

        // Ghana is GMT year round, so "today in Accra" and "today in UTC" agree,
        // but naming the zone keeps it correct if this ever runs elsewhere.
        $date = $this->option('date') ?: CarbonImmutable::now(config('wgh.display_timezone'))->toDateString();

        $existing = FxRate::whereDate('rate_date', $date)->first();

        FxRate::updateOrCreate(
            ['rate_date' => $date],
            [
                'ghs_per_usd' => $rate,
                'source' => (string) $this->option('source'),
                'created_at' => CarbonImmutable::now('UTC'),
            ]
        );

        $this->info(($existing ? 'Updated' : 'Recorded').": 1 USD = {$rate} GHS on {$date}.");

        return self::SUCCESS;
    }
}
