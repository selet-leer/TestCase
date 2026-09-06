<?php

namespace Tests\Feature;

use Tests\TestCase;

class OrderApiTest extends TestCase
{
    private string $dataFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataFile = storage_path('framework/testing/products.json');
        config(['products.storage_path' => $this->dataFile]);
        $this->cleanFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanFiles();

        parent::tearDown();
    }

    private function cleanFiles(): void
    {
        @unlink($this->dataFile);
        @unlink($this->dataFile.'.lock');
        @unlink($this->dataFile.'.tmp');
    }

    private function createProduct(int $stock): string
    {
        return $this->postJson('/api/products', [
            'name' => 'Chair',
            'price' => 1,
            'stock' => $stock,
        ])->assertCreated()->json('data.id');
    }

    public function test_it_places_an_order_and_decrements_stock(): void
    {
        $id = $this->createProduct(stock: 10);

        $this->postJson("/api/products/{$id}/orders", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.stock', 7)
            ->assertJsonPath('data.version', 2);

        // The decrement is persisted, not just reflected in the response.
        $this->getJson("/api/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.stock', 7);
    }

    public function test_it_allows_ordering_the_exact_remaining_stock(): void
    {
        $id = $this->createProduct(stock: 4);

        $this->postJson("/api/products/{$id}/orders", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.stock', 0);
    }

    public function test_it_rejects_an_order_that_exceeds_stock(): void
    {
        $id = $this->createProduct(stock: 2);

        $this->postJson("/api/products/{$id}/orders", ['quantity' => 3])
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        // A rejected order must leave the product completely untouched —
        // stock unchanged and, crucially, no version bump.
        $this->getJson("/api/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.stock', 2)
            ->assertJsonPath('data.version', 1);
    }

    public function test_it_returns_404_for_an_unknown_product(): void
    {
        $this->postJson('/api/products/does-not-exist/orders', ['quantity' => 1])
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_it_rejects_a_missing_quantity(): void
    {
        $id = $this->createProduct(stock: 10);

        $this->postJson("/api/products/{$id}/orders", [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['quantity']]);
    }

    public function test_it_rejects_a_non_positive_quantity(): void
    {
        $id = $this->createProduct(stock: 10);

        foreach ([0, -1] as $quantity) {
            $this->postJson("/api/products/{$id}/orders", ['quantity' => $quantity])
                ->assertStatus(422)
                ->assertJsonStructure(['message', 'errors' => ['quantity']]);
        }
    }

    public function test_it_rejects_a_non_integer_quantity(): void
    {
        $id = $this->createProduct(stock: 10);

        $this->postJson("/api/products/{$id}/orders", ['quantity' => 'two'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['quantity']]);
    }
}
