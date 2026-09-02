<?php

namespace App\Repositories;

use App\Models\Product;

interface ProductRepository
{
    /**
     * @return array<Product>
     */
    public function all(): array;

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
