<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Interfaces\ProductRepository;
use App\Queries\ProductQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function index(IndexProductRequest $request): JsonResource
    {
        $products = $this->products->paginate(ProductQuery::fromRequest($request));

        return ProductResource::collection($products);
    }

    public function show(string $id): JsonResource
    {
        $product = $this->products->find($id);

        abort_if($product === null, 404, 'Product not found.');

        return ProductResource::make($product);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated());

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateProductRequest $request, string $id): JsonResource
    {
        $product = $this->products->update($id, $request->validated());

        abort_if($product === null, 404, 'Product not found.');

        return ProductResource::make($product);
    }

    public function destroy(string $id): Response
    {
        $deleted = $this->products->delete($id);

        abort_if(! $deleted, 404, 'Product not found.');

        return response()->noContent();
    }
}
