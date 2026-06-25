<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache the storefront side of each mapping (name/price/category) so the setup
 * wizard can render the product list without re-querying the merchant's own
 * product table on every page load. These mirror the cached pos_name/pos_price
 * columns and are display-only — resolution still keys on local_product_id.
 *
 * Named (not anonymous) class for compatibility with Laravel 7's migrator.
 */
class AddLocalColumnsToBiteslotProductMapTable extends Migration
{
    public function up(): void
    {
        Schema::table('biteslot_product_map', function (Blueprint $table) {
            if (! Schema::hasColumn('biteslot_product_map', 'local_name')) {
                $table->string('local_name')->nullable()->after('local_sku');
            }
            if (! Schema::hasColumn('biteslot_product_map', 'local_price')) {
                $table->decimal('local_price', 12, 2)->nullable()->after('local_name');
            }
            if (! Schema::hasColumn('biteslot_product_map', 'local_category')) {
                $table->string('local_category')->nullable()->after('local_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biteslot_product_map', function (Blueprint $table) {
            foreach (['local_name', 'local_price', 'local_category'] as $column) {
                if (Schema::hasColumn('biteslot_product_map', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
