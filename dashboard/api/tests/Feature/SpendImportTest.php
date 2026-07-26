<?php

namespace Tests\Feature;

use App\Models\AdSpend;
use App\Models\Keyword;
use App\Services\Ads\SpendImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Sprint 2's acceptance test for the parsers and the import.
 *
 * The headline requirement from the spec: total spend in the dashboard equals
 * the total in the CSV to the cent, and re-importing changes nothing.
 */
class SpendImportTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $name, string $body): string
    {
        $path = sys_get_temp_dir().'/wgh-test-'.$name;
        file_put_contents($path, $body);

        return $path;
    }

    private function googleExport(): string
    {
        // A real Google export shape: a preamble, the "Impr." abbreviation,
        // currency symbols, thousands separators, mixed casing, and the Total
        // row Google appends.
        return $this->file('google.csv', <<<'CSV'
        Keyword report (Jul 1, 2026-Jul 28, 2026)
        Jul 1, 2026-Jul 28, 2026

        Campaign,Ad group,Keyword,Match type,Impr.,Clicks,Cost
        "WGH - Search",Blenders,"blender price accra",Exact,"1,204",96,"$41.28"
        "WGH - Search",Blenders,"Binatone Blender",Phrase,890,64,"$22.10"
        "WGH - Search",Blenders,"blender",Broad,"3,410",188,"$61.55"
        Total: campaign,,,,"5,504",348,"$124.93"
        CSV);
    }

    public function test_total_spend_matches_the_file_to_the_cent(): void
    {
        $result = (new SpendImporter)->import($this->googleExport());

        $this->assertSame('124.93', $result['file_total']);
        $this->assertSame('124.93', $result['stored_total']);
        $this->assertSame('124.93', number_format((float) AdSpend::sum('spend_usd'), 2, '.', ''));
    }

    public function test_the_google_total_row_is_not_imported_as_spend(): void
    {
        (new SpendImporter)->import($this->googleExport());

        // The Total row carries the same 124.93. Importing it would double the
        // spend to 249.86, and the number would still look plausible.
        $this->assertSame(3, AdSpend::count());
        $this->assertSame('124.93', number_format((float) AdSpend::sum('spend_usd'), 2, '.', ''));
    }

    public function test_re_importing_the_same_file_changes_nothing(): void
    {
        $file = $this->googleExport();
        $importer = new SpendImporter;

        $first = $importer->import($file);
        $second = $importer->import($file);

        $this->assertSame(3, $first['inserted']);
        $this->assertSame(0, $second['inserted'], 'a re-import must not add rows');
        $this->assertSame(0, $second['updated'], 'a re-import must not rewrite unchanged rows');
        $this->assertSame(3, $second['unchanged']);
        $this->assertSame($first['stored_total'], $second['stored_total'], 'spend must not move');
    }

    public function test_a_restated_figure_overwrites_rather_than_accumulates(): void
    {
        $importer = new SpendImporter;
        $importer->import($this->googleExport());

        // Google revises recent days for invalid clicks. The same period
        // arriving with a lower cost is a correction, not extra spend.
        $revised = $this->file('google-revised.csv', <<<'CSV'
        Keyword report (Jul 1, 2026-Jul 28, 2026)
        Jul 1, 2026-Jul 28, 2026

        Campaign,Ad group,Keyword,Match type,Impr.,Clicks,Cost
        "WGH - Search",Blenders,"blender price accra",Exact,"1,204",90,"$38.10"
        CSV);

        $result = $importer->import($revised);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(3, AdSpend::count(), 'a restatement must not create a fourth row');
        $this->assertSame('38.10', (string) AdSpend::where('keyword', 'blender price accra')->first()->spend_usd);
    }

    public function test_keyword_text_is_normalised_so_one_keyword_is_one_row(): void
    {
        (new SpendImporter)->import($this->googleExport());

        // "Binatone Blender" in the file must join against "binatone blender"
        // in the attribution table. Two casings would be two half-as-profitable
        // keywords, and neither would ever cross a judging threshold.
        $this->assertNotNull(AdSpend::where('keyword', 'binatone blender')->first());
        $this->assertNull(AdSpend::where('keyword', 'Binatone Blender')->first());
    }

    public function test_currency_symbols_and_thousands_separators_parse(): void
    {
        (new SpendImporter)->import($this->googleExport());

        $this->assertSame('61.55', (string) AdSpend::where('keyword', 'blender')->first()->spend_usd);
        $this->assertSame(3410, (int) AdSpend::where('keyword', 'blender')->first()->impressions);
    }

    public function test_a_european_decimal_comma_is_not_read_as_thousands(): void
    {
        // "18,50" is eighteen dollars fifty, not one thousand eight hundred
        // and fifty. Getting this wrong is a hundredfold error in the spend
        // column and it would look like a catastrophic campaign.
        $file = $this->file('tiktok.csv', <<<'CSV'
        Campaign name,Ad group name,Cost,Impressions,Clicks (destination),Date
        Promote-blender,default,"18,50",8400,142,2026-07-14
        CSV);

        (new SpendImporter)->import($file);

        $this->assertSame('18.50', (string) AdSpend::where('platform', 'tiktok')->first()->spend_usd);
    }

    public function test_the_platform_is_detected_from_the_header_not_the_filename(): void
    {
        $file = $this->file('mystery.csv', <<<'CSV'
        Campaign name,Ad set name,Ad name,Amount spent (USD),Impressions,Link clicks,Reporting starts,Reporting ends
        CTWA-Blenders,Accra,Video-A,"78.40","24,110",612,2026-07-01,2026-07-28
        CSV);

        $result = (new SpendImporter)->import($file);

        $this->assertSame('meta', $result['platform']);
    }

    public function test_meta_produces_no_keyword_rows_and_says_so(): void
    {
        $file = $this->file('meta.csv', <<<'CSV'
        Campaign name,Ad set name,Amount spent (USD),Impressions,Link clicks,Reporting starts,Reporting ends
        CTWA-Blenders,Accra,"78.40","24,110",612,2026-07-01,2026-07-28
        CSV);

        $result = (new SpendImporter)->import($file);

        $this->assertSame('', AdSpend::where('platform', 'meta')->first()->keyword);
        $this->assertNotEmpty($result['notes'], 'the owner must be told why no keyword verdicts came from this file');
        $this->assertSame(0, Keyword::count(), 'Meta must never create keyword registry rows');
    }

    public function test_a_file_with_no_recognisable_header_fails_loudly(): void
    {
        $file = $this->file('junk.csv', "some,random,columns\n1,2,3\n");

        $this->expectException(RuntimeException::class);

        (new SpendImporter)->import($file, 'google');
    }

    public function test_an_export_with_no_date_range_refuses_rather_than_guessing(): void
    {
        // Spend attributed to the wrong period silently moves money between
        // months. Better to stop and ask than to assume today.
        $file = $this->file('nodate.csv', <<<'CSV'
        Campaign,Ad group,Keyword,Match type,Impr.,Clicks,Cost
        "WGH - Search",Blenders,"blender price accra",Exact,1204,96,41.28
        CSV);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/--from/');

        (new SpendImporter)->import($file, 'google');
    }
}
