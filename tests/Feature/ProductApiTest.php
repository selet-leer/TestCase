<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductApiTest extends TestCase
{
    private string $dataFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Point tests at an isolated file so the suite never touches the
        // real production data (config/products.php makes this swappable).
        $this->dataFile = storage_path('framework/testing/products.json');
        config(['products.storage_path' => $this->dataFile]);
        @unlink($this->dataFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->dataFile);
        @unlink($this->dataFile.'.lock');

        parent::tearDown();
    }

    public function test_index_returns_empty_list_when_no_products_exist(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_it_creates_a_product(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Widget',
            'price' => 1999,
            'stock' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Widget')
            ->assertJsonPath('data.price', 1999)
            ->assertJsonPath('data.stock', 10)
            ->assertJsonPath('data.version', 1)
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'stock', 'version', 'created_at', 'updated_at']]);
    }

    public function test_create_validates_input(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => '',
            'price' => 19.99,
            'stock' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name', 'price', 'stock']]);
    }

    public function test_it_shows_a_product(): void
    {
        $id = $this->postJson('/api/products', [
            'name' => 'Widget',
            'price' => 1999,
            'stock' => 10,
        ])->json('data.id');

        $this->getJson("/api/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_show_returns_404_for_missing_product(): void
    {
        $this->getJson('/api/products/does-not-exist')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_it_updates_a_product_and_bumps_version(): void
    {
        $id = $this->postJson('/api/products', [
            'name' => 'Widget',
            'price' => 1999,
            'stock' => 10,
        ])->json('data.id');

        $this->putJson("/api/products/{$id}", [
            'name' => 'Widget v2',
            'price' => 2499,
            'stock' => 5,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Widget v2')
            ->assertJsonPath('data.price', 2499)
            ->assertJsonPath('data.version', 2);
    }

    public function test_update_returns_404_for_missing_product(): void
    {
        $this->putJson('/api/products/does-not-exist', [
            'name' => 'Widget',
            'price' => 1999,
            'stock' => 10,
        ])->assertStatus(404);
    }

    public function test_it_deletes_a_product(): void
    {
        $id = $this->postJson('/api/products', [
            'name' => 'Widget',
            'price' => 1999,
            'stock' => 10,
        ])->json('data.id');

        $this->deleteJson("/api/products/{$id}")->assertStatus(204);
        $this->getJson("/api/products/{$id}")->assertStatus(404);
    }

    public function test_delete_returns_404_for_missing_product(): void
    {
        $this->deleteJson('/api/products/does-not-exist')
            ->assertStatus(404);
    }
}
