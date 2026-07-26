<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The milestone ladder and the sync cursor store.
 *
 * milestones is the threshold ladder from the spec. Sprint 3 evaluates it,
 * sprint 4 renders the Journey strip, sprint 6 leads a briefing with it. The
 * table and its seeded ladder land in sprint 1 so nothing later has to guess
 * at the shape.
 *
 * sync_state is the cursor. One row per stream, holding the position and the
 * outcome of the last run. The cursor is only advanced after a run completes
 * in full, so a network drop mid-sync re-pulls cleanly next time instead of
 * skipping the rows it never received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->string('gate_code', 8)->unique();
            $table->string('gate_label', 191);
            $table->json('threshold_json');
            $table->timestampTz('reached_at')->nullable();
            $table->text('decision_text');
            $table->boolean('decision_taken')->default(false);
            $table->timestampTz('decision_taken_at')->nullable();
            $table->json('evidence_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_guardrail')->default(false);
        });

        Schema::create('sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('stream', 32)->unique();          // orders|attribution
            $table->timestampTz('cursor_at')->nullable();
            $table->timestampTz('last_run_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->string('last_status', 12)->default('never');   // never|ok|failed
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('rows_seen')->default(0);
            $table->unsignedBigInteger('rows_written')->default(0);
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('started_at')->index();
            $table->timestampTz('finished_at')->nullable();
            $table->string('status', 12)->default('running');      // running|ok|failed
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('orders_seen')->default(0);
            $table->unsignedInteger('orders_written')->default(0);
            $table->unsignedInteger('items_written')->default(0);
            $table->unsignedInteger('attr_seen')->default(0);
            $table->unsignedInteger('attr_written')->default(0);
            $table->unsignedInteger('shop_orders_total')->default(0);
            $table->unsignedInteger('shop_attr_total')->default(0);
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('sync_state');
        Schema::dropIfExists('milestones');
    }
};
