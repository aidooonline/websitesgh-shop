<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The value side of the equation.
 *
 * WHY THIS IS THE MOST IMPORTANT MIGRATION SO FAR
 * Every verdict the engine produces compares cost per order against profit per
 * order. Cost is measured to the cent. Profit was a constant typed into a
 * config file, so half of every judgement in the system rested on a guess.
 *
 * Worse, the guess is the side with leverage. The shop's own settled decisions
 * cap what can be spent: Google Search can absorb about $9.55 a month and
 * TikTok Ads Manager is out of reach. Cost per order cannot be squeezed much
 * further. Profit per order has no ceiling, and it is a multiplier on every
 * keyword at once: lift it from $8.75 to $13 and every keyword in the account
 * becomes 49 percent more affordable the same day, with no bid change at all.
 *
 * product_costs makes it measurable. customers makes it improvable, because
 * the cheapest sale in the business is the second one to someone who already
 * bought, and nothing in the system could see whether that was happening.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_product_id')->unique();
            $table->string('product_name', 191);

            $table->decimal('sell_price_ghs', 14, 2)->nullable();
            $table->decimal('dealer_cost_ghs', 14, 2)->nullable();

            // Per unit, because delivery on a kettle and on a fridge are not
            // the same number and averaging them hides the products that are
            // quietly unprofitable once the rider is paid.
            $table->decimal('delivery_cost_ghs', 14, 2)->nullable();

            $table->string('supplier', 120)->nullable();
            $table->boolean('is_estimate')->default(true);
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('updated_at');

            $table->index('is_estimate');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            /*
             * Keyed on the SHA-256 of the E.164 phone, never the raw number.
             * The raw number stays on the shop; this database only ever needs
             * to know that two orders came from the same person, and a hash
             * answers that without holding anything worth stealing.
             */
            $table->char('phone_sha256', 64)->unique();

            $table->string('display_name', 120)->nullable();
            $table->string('area', 120)->nullable()->index();

            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('first_order_at')->nullable();
            $table->timestampTz('last_order_at')->nullable();

            $table->unsignedInteger('orders_count')->default(0);

            /*
             * Distinct calendar days on which this person ordered, which is the
             * honest count of shopping trips. Two orders on one day are a
             * forgotten item or a split delivery, not a customer who came back,
             * and counting them as a return reports a reorder gap of zero days.
             */
            $table->unsignedInteger('order_days_count')->default(0);

            $table->unsignedInteger('taps_count')->default(0);
            $table->decimal('lifetime_revenue_ghs', 14, 2)->default(0);
            $table->decimal('lifetime_profit_ghs', 14, 2)->nullable();
            $table->decimal('average_order_ghs', 14, 2)->default(0);

            // Days from the first order to the second. The single most useful
            // number for deciding when a follow-up message is timely rather
            // than annoying.
            $table->integer('days_to_second_order')->nullable();

            $table->string('first_source', 60)->nullable();
            $table->string('first_campaign', 191)->nullable();

            $table->timestampTz('computed_at');
            $table->index('orders_count');
        });

        Schema::create('product_pairs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_a');
            $table->unsignedBigInteger('product_b');
            $table->string('name_a', 191);
            $table->string('name_b', 191);

            $table->unsignedInteger('baskets_together')->default(0);
            $table->unsignedInteger('baskets_a')->default(0);
            $table->unsignedInteger('baskets_b')->default(0);

            /*
             * Lift, not raw co-occurrence. Two popular products appear together
             * often simply because both are popular; that is not a finding and
             * bundling on it wastes the offer. Lift above 1 means they appear
             * together MORE than their individual popularity predicts, which is
             * the only version of this worth acting on.
             */
            $table->decimal('lift', 8, 3)->default(0);
            $table->decimal('combined_revenue_ghs', 14, 2)->default(0);
            $table->timestampTz('computed_at');

            $table->unique(['product_a', 'product_b']);
            $table->index('lift');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Populated from product_costs, so a period closed today keeps the
            // margin that was true today even if a supplier price moves later.
            $table->decimal('estimated_cost_ghs', 14, 2)->nullable();
            $table->boolean('cost_is_estimated')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['estimated_cost_ghs', 'cost_is_estimated']);
        });

        Schema::dropIfExists('product_pairs');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_costs');
    }
};
