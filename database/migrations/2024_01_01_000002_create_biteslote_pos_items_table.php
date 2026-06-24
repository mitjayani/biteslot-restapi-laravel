<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A local snapshot of the POS catalog, refreshed by `biteslote:sync-catalog`.
 *
 * Powers a mapping UI (pick the POS item for each storefront product) and the
 * SKU auto-match step. This is a cache: it is safe to truncate and re-sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biteslote_pos_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_item_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('sku', 191)->nullable();
            $table->string('name');
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('available')->default(true);
            $table->string('category')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['pos_item_id', 'branch_id']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biteslote_pos_items');
    }
};
