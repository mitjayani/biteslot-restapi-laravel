<?php

namespace Biteslot\Connector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The merchant's chosen product source: which table on their site holds products
 * and which columns map to id / sku / name / price / category. Single row (id = 1),
 * configured in the setup wizard.
 *
 * @property string|null $connection
 * @property string|null $source_table
 * @property string|null $col_id
 * @property string|null $col_sku
 * @property string|null $col_name
 * @property string|null $col_price
 * @property string|null $col_category
 * @property \Illuminate\Support\Carbon|null $imported_at
 */
class SourceSetting extends Model
{
    protected $table = 'biteslot_source_settings';

    protected $guarded = [];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    /** The single settings row, created on first access. */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /** True once a table + id column have been picked. */
    public function isConfigured(): bool
    {
        return ! empty($this->source_table) && ! empty($this->col_id);
    }

    /**
     * The selected columns keyed by role, dropping any that weren't chosen.
     *
     * @return array<string,string>
     */
    public function columnMap(): array
    {
        return array_filter([
            'id' => $this->col_id,
            'sku' => $this->col_sku,
            'name' => $this->col_name,
            'price' => $this->col_price,
            'category' => $this->col_category,
        ], static fn ($v) => ! empty($v));
    }
}
