<?php

namespace App\Storage;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductFileStorage
{
    private const SCHEMA_VERSION = 1;

    private readonly string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('app/data/products.json');
    }

    /**
     * @return Product[]
     */
    public function all(): array
    {
        return $this->read();
    }

    public function find(string $id): ?Product
    {
        foreach ($this->read() as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        return null;
    }

    public function create(array $attributes): Product
    {
        $products = $this->read();
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
        $products = $this->read();

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
        $products = $this->read();
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
     * @return Product[]
     */
    private function read(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($this->path), true);

        $products = [];

        foreach ($decoded['products'] ?? [] as $data) {
            $products[] = Product::fromArray($data);
        }

        return $products;
    }

    /**
     * @param  Product[]  $products
     */
    private function write(array $products): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $productsData = [];

        foreach ($products as $product) {
            $productsData[] = $product->toArray();
        }

        $payload = json_encode([
            '_schema_version' => self::SCHEMA_VERSION,
            'products' => $productsData,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->path, $payload);
    }
}
