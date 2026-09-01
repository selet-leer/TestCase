<?php

namespace App\Repositories;

use App\Models\Product;
use App\Storage\ProductFileStorage;
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
    }

    public function update(string $id, array $attributes): ?Product
    {
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
    }

    public function delete(string $id): bool
    {
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
    }

    /**
     * @param  Product[]  $products
     */
    private function write(array $products): void
    {
        $rows = [];

        foreach ($products as $product) {
            $rows[] = $product->toArray();
        }

        $this->storage->write($rows);
    }
}
