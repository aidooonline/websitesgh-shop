<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keyword-level campaign detail on attribution events and orders.
 *
 * WHY THIS IS NOT OPTIONAL
 * A gclid says "a Google click happened". It does not say which keyword, ad
 * group or ad produced it, and there is no way to ask Google afterwards: the
 * click id only resolves inside Google's own reporting. Whatever ValueTrack
 * writes onto the landing URL at click time is the only chance to capture it,
 * ever. Sprint 2 has to answer "what did this keyword cost and what did it
 * earn", and without these columns the strongest half of that join, the exact
 * one, would not exist. Adding them now costs a migration; adding them later
 * costs every click in between.
 *
 * WHAT utm_term ACTUALLY HOLDS
 * The keyword bid on, not the search query the customer typed. Google's
 * {keyword} returns the matching keyword from the account. The actual query is
 * only in the search terms report, so sprint 2 must treat utm_term as the
 * bid keyword and never label it "what people searched".
 *
 * IDS RATHER THAN NAMES
 * campaign_id, adgroup_id and creative_id are numeric because that is what
 * ValueTrack gives, and because ids survive a rename. The CSV import in sprint
 * 2 carries both id and name, so the dashboard resolves them for display and
 * joins on the id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribution_events', function (Blueprint $table) {
            $table->string('utm_term', 191)->nullable()->index();     // The BID keyword.
            $table->string('utm_content', 191)->nullable();
            $table->string('utm_id', 64)->nullable();
            $table->string('match_type', 4)->nullable();              // e|p|b|a
            $table->string('campaign_id', 32)->nullable()->index();
            $table->string('adgroup_id', 32)->nullable()->index();
            $table->string('creative_id', 32)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->string('network', 8)->nullable();                 // g|s|d|ytv|vp|gtv|x|e
            $table->string('device', 4)->nullable();                  // m|t|c
            $table->string('ad_placement', 191)->nullable();

            // The basket behind a cart tap, as "productId:qty:lineTotal"
            // repeated. The shop is cart first, so this is what lets revenue be
            // attributed to a product at all on the main order path.
            $table->text('cart_items')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('utm_term', 191)->nullable()->index();
            $table->string('utm_content', 191)->nullable();
            $table->string('utm_id', 64)->nullable();
            $table->string('match_type', 4)->nullable();
            $table->string('campaign_id', 32)->nullable()->index();
            $table->string('adgroup_id', 32)->nullable()->index();
            $table->string('creative_id', 32)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->string('network', 8)->nullable();
            $table->string('device', 4)->nullable();
            $table->string('ad_placement', 191)->nullable();
        });

        Schema::table('keywords', function (Blueprint $table) {
            // Populated from the CSV import, so a renamed campaign does not
            // orphan its own history.
            $table->string('campaign_id', 32)->nullable()->index();
            $table->string('adgroup_id', 32)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropColumn(['campaign_id', 'adgroup_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'utm_term', 'utm_content', 'utm_id', 'match_type', 'campaign_id',
                'adgroup_id', 'creative_id', 'target_id', 'network', 'device', 'ad_placement',
            ]);
        });

        Schema::table('attribution_events', function (Blueprint $table) {
            $table->dropColumn([
                'utm_term', 'utm_content', 'utm_id', 'match_type', 'campaign_id',
                'adgroup_id', 'creative_id', 'target_id', 'network', 'device',
                'ad_placement', 'cart_items',
            ]);
        });
    }
};
