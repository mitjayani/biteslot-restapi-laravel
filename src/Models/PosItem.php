<?php

namespace Biteslote\Connector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cached snapshot of a POS menu item (see biteslote:sync-catalog).
 *
 * @property int         $pos_item_id
 * @property int|null    $branch_id
 * @property string|null $sku
 * @property string      $name
 * @property float|null  $price
 * @property bool        $available
 */
class PosItem extends Model
{
    protected $table = 'biteslote_pos_items';

    protected $guarded = [];

    protected $casts = [
        'pos_item_id' => 'integer',
        'branch_id' => 'integer',
        'price' => 'float',
        'available' => 'boolean',
        'raw' => 'array',
        'synced_at' => 'datetime',
    ];
}
