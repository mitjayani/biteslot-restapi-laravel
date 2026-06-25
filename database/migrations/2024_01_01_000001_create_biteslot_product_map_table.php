<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The authoritative link between a storefront product and a POS menu item.
 *
 * Storefront product IDs/names never match POS menu_item IDs, so every web
 * order line is translated through this table before being forwarded. One row
 * per local product.
 *
 * Named (not anonymous) class for compatibility with Laravel 7's migrator.
 */
class CreateBiteslotProductMapTable extends Migration
{
    public function up(): void
    {
        Schema::create('biteslot_product_map', function (Blueprint $table) {
            $table->id();

            // The storefront's own product identifier (int or uuid -> stored as string).
            $table->string('local_product_id', 191);
            $table->string('local_sku', 191)->nullable();

            // The resolved POS side. Null until mapped.
            $table->unsignedBigInteger('pos_item_id')->nullable();
            $table->unsignedBigInteger('pos_branch_id')->nullable();

            // Cached for display in a mapping UI; not used for resolution.
            $table->string('pos_name')->nullable();
            $table->decimal('pos_price', 12, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('local_product_id');
            $table->index('local_sku');
            $table->index('pos_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biteslot_product_map');
    }
}
