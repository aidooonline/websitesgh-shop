<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who the visitor was: a customer, a crawler, or the shop owner testing.
 *
 * WHY
 * The funnel read 87 add-to-cart against 8 WhatsApp messages and nothing in the
 * system could say whether that was a closing problem or a bot problem. One
 * reading says fix the cart page, the other says ignore it, and they lead to
 * opposite work. A number that cannot tell those apart is not a measurement.
 *
 * WooCommerce accepts add-to-cart as a plain GET, and those links sit on every
 * category page, so a crawler walking the shop fills the basket dozens of times
 * with no human intent behind any of it.
 *
 * DEFAULT IS 'human', DELIBERATELY
 * Rows that already exist were recorded before anything was classified. Marking
 * them 'unknown' would be more literally true but would exclude the entire
 * history from every funnel at once, replacing a wrong number with no number.
 * They stay countable, and the report says how much of the period predates
 * classification so the reader can weigh it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribution_events', function (Blueprint $table) {
            $table->string('visitor', 8)->default('human')->index();
        });
    }

    public function down(): void
    {
        Schema::table('attribution_events', function (Blueprint $table) {
            $table->dropColumn('visitor');
        });
    }
};
