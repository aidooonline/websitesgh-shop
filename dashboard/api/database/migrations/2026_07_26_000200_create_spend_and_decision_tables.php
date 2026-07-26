<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad spend, the keyword registry, the decision log and the audit trail.
 *
 * These belong to sprints 2, 3 and 5. They are created now, in sprint 1,
 * because the spec is explicit about it: "all tables created now, so later
 * sprints never migrate under pressure." A migration written while a keyword
 * is haemorrhaging budget is a migration written badly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date')->unique();
            $table->decimal('ghs_per_usd', 12, 6);
            $table->string('source', 60)->default('manual');
            $table->timestampTz('created_at');
        });

        Schema::create('ad_spend', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 12)->index();          // google|meta|tiktok
            $table->date('period_start');
            $table->date('period_end');
            $table->string('campaign', 191);
            $table->string('ad_group', 191)->default('');
            $table->string('keyword', 191)->default('');      // '' for non-search
            $table->string('match_type', 16)->default('');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('spend_usd', 14, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('source_file', 191)->nullable();
            $table->timestampTz('imported_at');

            // Re-importing the same export must not double count spend. This
            // is the whole defence, and sprint 2's acceptance test is exactly
            // "import the file twice, assert total spend unchanged".
            $table->unique(
                ['platform', 'campaign', 'ad_group', 'keyword', 'period_start', 'period_end'],
                'ad_spend_natural_key'
            );
            $table->index(['platform', 'period_start']);
        });

        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 191);
            $table->string('match_type', 16)->default('');
            $table->string('ad_group', 191)->default('');
            $table->string('campaign', 191)->default('');
            $table->string('landing_url', 255)->nullable();

            $table->timestampTz('first_seen')->nullable();
            $table->timestampTz('last_seen')->nullable();

            $table->decimal('lifetime_spend_usd', 14, 2)->default(0);
            $table->unsignedBigInteger('lifetime_clicks')->default(0);
            $table->unsignedBigInteger('lifetime_taps')->default(0);
            $table->unsignedBigInteger('lifetime_orders')->default(0);
            $table->decimal('lifetime_revenue_ghs', 14, 2)->default(0);

            $table->string('current_verdict', 8)->nullable()->index();  // keep|watch|fix|kill
            $table->text('verdict_reason')->nullable();
            $table->timestampTz('verdict_at')->nullable();

            $table->string('owner_decision', 8)->nullable();            // keep|hold|kill
            $table->timestampTz('owner_decision_at')->nullable();

            $table->unique(['keyword', 'match_type', 'ad_group', 'campaign'], 'keywords_natural_key');
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->string('dimension', 12)->index();        // keyword|product|channel|creative
            $table->string('entity_ref', 191)->index();
            $table->string('verdict', 12);
            $table->text('reason');
            $table->text('suggested_action')->nullable();

            // The engine refuses to write a verdict without evidence. Not
            // nullable, deliberately: a verdict you cannot audit is an opinion.
            $table->json('evidence_json');

            $table->string('source', 8)->index();            // engine|owner|agent
            $table->timestampTz('created_at')->index();
        });

        Schema::create('manual_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('field', 60);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestampTz('entered_at')->index();
        });

        Schema::create('agent_briefings', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 12);                   // import|manual
            $table->timestampTz('created_at')->index();
            $table->string('model_used', 60)->nullable();
            $table->string('period_covered', 60)->nullable();
            $table->text('summary_md')->nullable();
            $table->text('top_action')->nullable();
            $table->json('evidence_json')->nullable();
            $table->decimal('tokens_cost', 10, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_briefings');
        Schema::dropIfExists('manual_entries');
        Schema::dropIfExists('decisions');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('ad_spend');
        Schema::dropIfExists('fx_rates');
    }
};
