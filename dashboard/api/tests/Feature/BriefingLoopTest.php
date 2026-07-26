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
}
