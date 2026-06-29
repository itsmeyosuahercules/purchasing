<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

class TableQuery
{
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    /**
     * Terapkan pencarian & pengurutan dari query string ke builder.
     *
     * @param  array{searchable?: list<string>, sortable?: list<string>, default?: array{0:string,1:string}}  $config
     */
    public static function apply(Builder $query, array $config): Builder
    {
        static::applySearch($query, $config['searchable'] ?? []);
        static::applySort($query, $config['sortable'] ?? [], $config['default'] ?? ['created_at', 'desc']);

        return $query;
    }

    /**
     * @param  array{searchable?: list<string>, sortable?: list<string>, default?: array{0:string,1:string}}  $config
     */
    public static function paginate(Builder $query, array $config): LengthAwarePaginator
    {
        return static::apply($query, $config)
            ->paginate(static::perPage($config['perPage'] ?? 15))
            ->withQueryString();
    }

    public static function perPage(int $default = 15): int
    {
        $value = (int) request('per_page', $default);

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : $default;
    }

    /**
     * @param  list<string>  $columns  Kolom langsung ('name') atau relasi ('supplier.alias_name').
     */
    protected static function applySearch(Builder $query, array $columns): void
    {
        $term = trim((string) request('search', ''));

        if ($term === '' || $columns === []) {
            return;
        }

        $query->where(function (Builder $q) use ($columns, $term) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $relColumn] = explode('.', $column, 2);
                    $q->orWhereHas($relation, fn (Builder $r) => $r->where($relColumn, 'like', "%{$term}%"));
                } else {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            }
        });
    }

    /**
     * @param  list<string>  $sortable
     * @param  array{0:string,1:string}  $default
     */
    protected static function applySort(Builder $query, array $sortable, array $default): void
    {
        $sort = (string) request('sort', '');
        $direction = request('direction') === 'asc' ? 'asc' : 'desc';

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $direction);

            return;
        }

        $query->orderBy($default[0], $default[1] ?? 'desc');
    }
}
