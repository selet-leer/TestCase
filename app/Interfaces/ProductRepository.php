<?php

namespace App\Interfaces;

use App\Models\Product;
use App\Queries\ProductQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepository
{
    /**
     * @return array<Product>
     */
    public function all(): array;

    /**
     * @return LengthAwarePaginator<Product>
     */
    public function paginate(ProductQuery $query): LengthAwarePaginator;

    public function find(string $id): ?Product;

    /**
     * @param array<string|int> $attributes
     */
    public function create(array $attributes): Product;

    /**
     * @param array<string|int> $attributes
     */
    public function update(string $id, array $attributes): ?Product;

    public function delete(string $id): bool;

    public function decrementStock(string $id, int $quantity): Product;
}
