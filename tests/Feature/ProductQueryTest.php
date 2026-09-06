<?php

namespace Tests\Feature;

use App\Queries\ProductQuery;
use Tests\TestCase;

class ProductQueryTest extends TestCase
{
    private string $dataFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataFile = storage_path('framework/testing/products.json');
        config(['products.storage_path' => $this->dataFile]);
        @unlink($this->dataFile);
        @unlink($this->dataFile.'.lock');

        $this->createProduct('USB cable', 500, 0);
        $this->createProduct('HDMI cable', 1500, 5);
        $this->createProduct('Widget', 1500, 10);
        $this->createProduct('Gadget', 999, 3);
    }

    protected function tearDown(): void
    {
        @unlink($this->dataFile);
        @unlink($this->dataFile.'.lock');
        @unlink($this->dataFile.'.tmp');

        parent::tearDown();
    }

    private function createProduct(string $name, int $price, int $stock): void
    {
        $this->postJson('/api/products', compact('name', 'price', 'stock'))->assertCreated();
    }

    public function test_filters_by_numeric_operator(): void
    {
        $names = $this->getJson('/api/products?filter[stock][gte]=1')->json('data.*.name');

        sort($names);
        $this->assertSame(['Gadget', 'HDMI cable', 'Widget'], $names);
    }

    public function test_filters_by_like_case_insensitively(): void
    {
        $names = $this->getJson('/api/products?filter[name][like]=CABLE')->json('data.*.name');

        sort($names);
        $this->assertSame(['HDMI cable', 'USB cable'], $names);
    }

    public function test_filters_combine_with_and(): void
    {
        $names = $this->getJson('/api/products?filter[stock][gte]=1&filter[name][like]=cable')
            ->json('data.*.name');

        $this->assertSame(['HDMI cable'], $names);
    }

    public function test_sorts_by_multiple_fields_with_direction(): void
    {
        $names = $this->getJson('/api/products?sort=-price,name')->json('data.*.name');

        $this->assertSame(['HDMI cable', 'Widget', 'Gadget', 'USB cable'], $names);
    }

    public function test_paginates_with_metadata(): void
    {
        $response = $this->getJson('/api/products?sort=-price,name&per_page=2&page=2');

        $response->assertOk()
            ->assertJsonPath('data.*.name', ['Gadget', 'USB cable'])
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_caps_per_page_at_the_maximum(): void
    {
        $this->getJson('/api/products?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', ProductQuery::MAX_PER_PAGE)
            ->assertJsonCount(4, 'data');
    }
}
