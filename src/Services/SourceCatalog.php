<?php

namespace Biteslot\Connector\Services;

use Biteslot\Connector\Models\SourceSetting;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Reads the *merchant's own* database so the setup wizard can let them pick the
 * table that holds their products and preview those products.
 *
 * Security: a table or column name is never taken on trust. Every identifier is
 * validated against the live schema (the table must appear in tables(), every
 * column must appear in columns()) before it reaches a query, so a crafted
 * table/column name from the browser cannot be used for injection. Reads are
 * SELECT-only and bounded by a row limit.
 */
class SourceCatalog
{
    /** @var DatabaseManager */
    private $db;

    /** @var Config */
    private $config;

    public function __construct(DatabaseManager $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Tables on the source connection the merchant can choose from. The
     * connector's own bookkeeping tables and the usual framework tables are
     * hidden to keep the list focused on real product data.
     *
     * @return array<int,string>
     */
    public function tables(?string $connection = null): array
    {
        $conn = $this->db->connection($this->resolveConnection($connection));
        $driver = $conn->getDriverName();

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $rows = $conn->select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = database() ORDER BY table_name');
                break;
            case 'pgsql':
                $rows = $conn->select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
                break;
            case 'sqlite':
                $rows = $conn->select("SELECT name AS name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
                break;
            default:
                $rows = array_map(static fn ($t) => (object) ['name' => $t], $conn->getDoctrineSchemaManager()->listTableNames());
        }

        $names = array_map(static fn ($r) => $r->name, $rows);

        return array_values(array_filter($names, fn ($name) => ! $this->isHiddenTable($name)));
    }

    /**
     * Column names of a table on the source connection. Throws if the table is
     * not one the merchant is allowed to read (see {@see tables()}).
     *
     * @return array<int,string>
     */
    public function columns(string $table, ?string $connection = null): array
    {
        $connectionName = $this->resolveConnection($connection);
        $this->assertTable($table, $connectionName);

        return $this->db->connection($connectionName)
            ->getSchemaBuilder()
            ->getColumnListing($table);
    }

    /**
     * Suggest a column for each role (id/sku/name/price/category) by matching
     * common names — a head start for the wizard the merchant can override.
     *
     * @return array<string,string|null>
     */
    public function guessColumns(string $table, ?string $connection = null): array
    {
        $columns = $this->columns($table, $connection);
        $lower = array_combine(array_map('strtolower', $columns), $columns);

        $pick = static function (array $candidates) use ($lower) {
            foreach ($candidates as $c) {
                if (isset($lower[$c])) {
                    return $lower[$c];
                }
            }

            return null;
        };

        return [
            'id' => $pick(['id', 'product_id', 'uuid']),
            'sku' => $pick(['sku', 'code', 'barcode', 'product_code']),
            'name' => $pick(['name', 'title', 'product_name', 'label']),
            'price' => $pick(['price', 'sale_price', 'amount', 'unit_price']),
            'category' => $pick(['category', 'category_name', 'type', 'group']),
        ];
    }

    /**
     * Preview / page through the merchant's products as normalised rows.
     *
     * @return array<int,array{local_product_id:string, local_sku:?string, local_name:?string, local_price:?float, local_category:?string}>
     */
    public function products(SourceSetting $source, ?string $search = null, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->query($source, $search)
            ->limit(max(1, $limit))
            ->offset(max(0, $offset))
            ->get();

        $map = $source->columnMap();

        return $rows->map(static function ($row) use ($map) {
            $get = static fn (?string $col) => ($col !== null && isset($row->{$col})) ? $row->{$col} : null;

            return [
                'local_product_id' => (string) $get($map['id']),
                'local_sku' => $get($map['sku'] ?? null) !== null ? (string) $get($map['sku']) : null,
                'local_name' => $get($map['name'] ?? null) !== null ? (string) $get($map['name']) : null,
                'local_price' => $get($map['price'] ?? null) !== null ? (float) $get($map['price']) : null,
                'local_category' => $get($map['category'] ?? null) !== null ? (string) $get($map['category']) : null,
            ];
        })->all();
    }

    /** Total number of products in the configured source table. */
    public function count(SourceSetting $source, ?string $search = null): int
    {
        return $this->query($source, $search)->count();
    }

    /**
     * A validated SELECT query over the configured source table. Every column
     * named here has already been checked against the live schema.
     */
    public function query(SourceSetting $source, ?string $search = null): Builder
    {
        if (! $source->isConfigured()) {
            throw new InvalidArgumentException('Product source is not configured yet.');
        }

        $connectionName = $this->resolveConnection($source->connection);
        $table = $source->source_table;
        $this->assertTable($table, $connectionName);

        $available = $this->columns($table, $connectionName);
        $map = $source->columnMap();

        $select = [];
        foreach ($map as $column) {
            if (! in_array($column, $available, true)) {
                throw new InvalidArgumentException("Column [{$column}] no longer exists on table [{$table}].");
            }
            $select[] = $column;
        }

        $query = $this->db->connection($connectionName)->table($table)->select(array_values(array_unique($select)));

        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $query->where(function (Builder $q) use ($map, $term) {
                foreach (['name', 'sku', 'id'] as $role) {
                    if (! empty($map[$role])) {
                        $q->orWhere($map[$role], 'like', $term);
                    }
                }
            });
        }

        return $query->orderBy($map['id']);
    }

    /** Validate that a table is a real, selectable table on the connection. */
    private function assertTable(string $table, ?string $connectionName): void
    {
        if (! in_array($table, $this->tables($connectionName), true)) {
            throw new InvalidArgumentException("Table [{$table}] is not available on the source connection.");
        }
    }

    private function resolveConnection(?string $connection): ?string
    {
        if ($connection !== null && $connection !== '') {
            return $connection;
        }

        $configured = $this->config->get('biteslot-connector.source.connection');

        return ($configured !== null && $configured !== '') ? $configured : null;
    }

    private function isHiddenTable(string $name): bool
    {
        if (strpos($name, 'biteslot_') === 0) {
            return true;
        }

        $framework = [
            'migrations', 'password_resets', 'password_reset_tokens', 'failed_jobs',
            'jobs', 'job_batches', 'sessions', 'cache', 'cache_locks',
            'personal_access_tokens', 'telescope_entries', 'telescope_entries_tags',
            'telescope_monitoring',
        ];

        return in_array($name, $framework, true);
    }
}
