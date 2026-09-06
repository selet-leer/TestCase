<?php

namespace App\Repositories;

use App\Exceptions\ProductNotFoundException;
use App\Interfaces\ProductRepository;
use App\Models\Product;
use App\Queries\ProductQuery;
use App\Storage\ProductFileStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

readonly class JsonProductRepository implements ProductRepository
{
    public function __construct(private ProductFileStorage $storage)
    {
    }

    public function all(): array
    {
        $products = [];

        foreach ($this->storage->read() as $data) {
            $products[] = Product::fromArray($data);
        }

        return $products;
    }

    public function paginate(ProductQuery $query): LengthAwarePaginatorContract
    {
        $products = $this->applySorts(
            $this->applyFilters($this->all(), $query->filters),
            $query->sorts,
        );

        $page = array_slice($products, ($query->page - 1) * $query->perPage, $query->perPage);

        return new LengthAwarePaginator(
            $page,
            count($products),
            $query->perPage,
            $query->page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    public function find(string $id): ?Product
    {
        foreach ($this->all() as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        return null;
    }

    public function create(array $attributes): Product
    {
        $this->storage->lock();

        try {
            $products = $this->all();
            $now = now()->toISOString();

            $product = new Product(
                id: (string) Str::uuid(),
                name: $attributes['name'],
                price: $attributes['price'],
                stock: $attributes['stock'],
                version: 1,
                createdAt: $now,
                updatedAt: $now,
            );

            $products[] = $product;
            $this->write($products);

            return $product;
        } finally {
            $this->storage->unlock();
        }
    }

    public function update(string $id, array $attributes): ?Product
    {
        $this->storage->lock();

        try {
            $products = $this->all();

            foreach ($products as $index => $product) {
                if ($product->id !== $id) {
                    continue;
                }

                $updated = new Product(
                    id: $product->id,
                    name: $attributes['name'],
                    price: $attributes['price'],
                    stock: $attributes['stock'],
                    version: $product->version + 1,
                    createdAt: $product->createdAt,
                    updatedAt: now()->toISOString(),
                );

                $products[$index] = $updated;
                $this->write($products);

                return $updated;
            }

            return null;
        } finally {
            $this->storage->unlock();
        }
    }

    public function delete(string $id): bool
    {
        $this->storage->lock();

        try {
            $products = $this->all();
            $remaining = [];
            $found = false;

            foreach ($products as $product) {
                if ($product->id === $id) {
                    $found = true;
                    continue;
                }

                $remaining[] = $product;
            }

            if (! $found) {
                return false;
            }

            $this->write($remaining);

            return true;
        } finally {
            $this->storage->unlock();
        }
    }

    public function decrementStock(string $id, int $quantity): Product
    {
        $this->storage->lock();

        try {
            $products = $this->all();

            foreach ($products as $index => $product) {
                if ($product->id !== $id) {
                    continue;
                }

                $updated = $product->withOrder($quantity);

                $products[$index] = $updated;
                $this->write($products);

                return $updated;
            }

            throw new ProductNotFoundException();
        } finally {
            $this->storage->unlock();
        }
    }

    /**
     * @param  array<Product>  $products
     */
    private function write(array $products): void
    {
        $rows = [];

        foreach ($products as $product) {
            $rows[] = $product->toArray();
        }

        $this->storage->write($rows);
    }

    /**
     * @param  array<Product>  $products
     * @param  array<array<string, string>>  $filters
     * @return array<Product>
     */
    private function applyFilters(array $products, array $filters): array
    {
        foreach ($filters as $filter) {
            $matching = [];

            foreach ($products as $product) {
                $actual = $product->{$filter['field']};

                if ($this->matches($actual, $filter['op'], $filter['value'])) {
                    $matching[] = $product;
                }
            }

            $products = $matching;
        }

        return $products;
    }

    private function matches(int|string $actual, string $op, string $value): bool
    {
        $typed = is_int($actual) ? (int) $value : $value;

        return match ($op) {
            'eq' => $actual == $typed,
            'ne' => $actual != $typed,
            'lt' => $actual < $typed,
            'lte' => $actual <= $typed,
            'gt' => $actual > $typed,
            'gte' => $actual >= $typed,
            'like' => stripos((string) $actual, $value) !== false,
        };
    }

    /**
     * @param  array<Product>  $products
     * @param  array<array<string, string>>  $sorts
     * @return array<Product>
     */
    private function applySorts(array $products, array $sorts): array
    {
        if (empty($sorts)) {
            return $products;
        }

        usort($products, function (Product $a, Product $b) use ($sorts): int {
            foreach ($sorts as $sort) {
                $comparison = $a->{$sort['field']} <=> $b->{$sort['field']};

                if ($comparison !== 0) {
                    return $sort['direction'] === 'desc' ? -$comparison : $comparison;
                }
            }

            return 0;
        });

        return $products;
    }
}
