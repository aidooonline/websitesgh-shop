<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders, order items and the attribution mirror.
 *
 * MONEY
 * Every money column is decimal(14,2) and sits beside an explicit currency.
 * Decimal, not float, because a float cannot hold 0.1 and a month of rounding
 * drift in a profit column is a silent lie. Decimal rather than integer minor
 * units because both are exact and decimal keeps the spec's column names
 * readable for the five sprints that follow. Nothing in this system ever
 * compares a GHS column to a USD column without a dated fx_rates row.
 *
 * TIME
 * Every timestamp is UTC. created_at on orders is the WooCommerce order time,
 * not an Eloquent housekeeping column, which is why the models turn Eloquent
 * timestamps off. woo_modified_at is what the delta cursor rides on.
 *
 * payload_hash
 * A sha256 of the exact payload the shop sent for this row. The sync compares
 * it before writing, so re-running a sync over unchanged data performs zero
 * writes rather than a no-op UPDATE that still bumps synced_at. That is what
 * makes the acceptance test literal: run it twice, nothing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_order_id')->unique();

            $table->timestampTz('created_at')->index();          // Order placed, UTC.
            $table->timestampTz('woo_modified_at')->nullable()->index();
            $table->string('status', 32)->index();

            $table->decimal('revenue_ghs', 14, 2)->default(0);
            $table->string('currency', 3)->default('GHS');

            $table->string('customer_ref', 16)->nullable()->index();   // WG-XXXX
            $table->string('click_id', 191)->nullable()->index();
            $table->string('click_type', 10)->nullable();
            $table->string('utm_source', 60)->nullable();
            $table->string('utm_medium', 60)->nullable();
            $table->string('utm_campaign', 120)->nullable()->index();
            $table->string('placement', 60)->nullable();

            // Owner-entered truth. Null means unknown, which is not the same
            // as false and must never be rendered as one.
            $table->boolean('delivered')->nullable();
            $table->boolean('delivery_failed')->default(false);
            $table->decimal('dealer_cost_ghs', 14, 2)->nullable();
            $table->decimal('delivery_cost_ghs', 14, 2)->nullable();
            $table->boolean('momo_received')->default(false);

            // Stays null until BOTH costs are known. A half-costed order shows
            // "awaiting costs", never a wrong number.
            $table->decimal('profit_ghs', 14, 2)->nullable();

            // Raw phone never leaves this server. Only the hash is exported.
            $table->string('customer_name', 191)->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->char('customer_phone_sha256', 64)->nullable()->index();
            $table->string('customer_area', 120)->nullable();

            $table->char('payload_hash', 64);
            $table->timestampTz('synced_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('woo_item_id');
            $table->unsignedBigInteger('woo_product_id')->index();
            $table->string('product_name', 191);
            $table->integer('qty')->default(1);
            $table->decimal('unit_price_ghs', 14, 2)->default(0);
            $table->char('payload_hash', 64);

            // The WooCommerce line item id is the only stable identity for a
            // line. Keying on product id instead would collapse two lines of
            // the same product and silently halve a basket.
            $table->unique('woo_item_id');
        });

        Schema::create('attribution_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_attr_id')->unique();

            $table->timestampTz('created_at')->index();
            $table->timestampTz('updated_at')->nullable()->index();  // Rides the cursor.

            $table->string('click_id', 191)->nullable()->index();
            $table->string('click_type', 10)->nullable();
            $table->unsignedBigInteger('product_id')->default(0)->index();
            $table->string('product_name', 191)->nullable();
            $table->decimal('price_ghs', 14, 2)->default(0);
            $table->string('placement', 60)->nullable()->index();
            $table->string('utm_source', 60)->nullable();
            $table->string('utm_medium', 60)->nullable();
            $table->string('utm_campaign', 120)->nullable()->index();

            // 'cart' is the add-to-cart funnel stage, so the middle of the
            // funnel is measurable: view -> cart -> WhatsApp tap -> sale.
            $table->string('status', 12)->default('pending')->index();
            $table->timestampTz('converted_at')->nullable();
            $table->decimal('conv_value_ghs', 14, 2)->default(0);
            $table->unsignedBigInteger('order_id')->default(0)->index();
            $table->boolean('exported')->default(false);
            $table->string('ref', 16)->nullable()->index();

            $table->string('cust_name', 120)->nullable();
            $table->string('cust_phone', 32)->nullable();
            $table->char('cust_phone_sha256', 64)->nullable();
            $table->string('cust_area', 120)->nullable();

            $table->char('payload_hash', 64);
            $table->timestampTz('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribution_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
