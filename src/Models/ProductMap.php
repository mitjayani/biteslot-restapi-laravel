<?php

namespace Biteslot\Connector\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string      $local_product_id
 * @property string|null $local_sku
 * @property string|null $local_name
 * @property float|null  $local_price
 * @property string|null $local_category
 * @property int|null    $pos_item_id
 * @property int|null    $pos_branch_id
 * @property string|null $pos_name
 * @property float|null  $pos_price
 * @property bool        $is_active
 */
class ProductMap extends Model
{
    protected $table = 'biteslot_product_map';

    protected $guarded = [];

    protected $casts = [
        'local_price' => 'float',
        'pos_item_id' => 'integer',
        'pos_branch_id' => 'integer',
        'pos_price' => 'float',
        'is_active' => 'boolean',
    ];

    /** Rows that resolve to a POS item and are enabled. */
    public function scopeMapped(Builder $query): Builder
    {
        return $query->whereNotNull('pos_item_id')->where('is_active', true);
    }

    /** Rows still waiting to be linked to a POS item. */
    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->whereNull('pos_item_id');
    }

    /**
     * Create or update the link for a storefront product.
     *
     * @param int|string $localProductId
     */
    public static function link($localProductId, int $posItemId, ?int $branchId = null, array $extra = []): self
    {
        return static::updateOrCreate(
            ['local_product_id' => (string) $localProductId],
            array_merge(['pos_item_id' => $posItemId, 'pos_branch_id' => $branchId], $extra)
        );
    }
}
