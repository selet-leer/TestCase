<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function index(): JsonResource
    {
        return ProductResource::collection($this->products->all());
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
