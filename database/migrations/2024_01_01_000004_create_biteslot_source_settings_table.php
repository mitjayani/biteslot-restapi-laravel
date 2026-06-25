<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which of the merchant's own database tables holds their products and
 * which columns map to id / sku / name / price / category. Chosen once in the
 * setup wizard ("Select the table that contains your products") and reused by the
 * product importer. Single row (id = 1).
 *
 * Named (not anonymous) class for compatibility with Laravel 7's migrator.
 */
class CreateBiteslotSourceSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('biteslot_source_settings', function (Blueprint $table) {
            $table->id();

            // The DB connection + table on the merchant's site that holds products.
            $table->string('connection')->nullable();
            $table->string('source_table')->nullable();

            // Which column on that table means what. col_id is required once set.
            $table->string('col_id')->nullable();
            $table->string('col_sku')->nullable();
            $table->string('col_name')->nullable();
            $table->string('col_price')->nullable();
            $table->string('col_category')->nullable();

            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biteslot_source_settings');
    }
}
