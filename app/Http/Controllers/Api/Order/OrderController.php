<?php

namespace App\Http\Controllers\Api\Order;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\DecrementOrderRequest;
use App\Http\Resources\ProductResource;
use App\Interfaces\ProductRepository;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderController extends Controller
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function decrement(DecrementOrderRequest $request, string $id): JsonResource
    {
        try {
            $product = $this->products->decrementStock($id, $request->integer('quantity'));
        } catch (ProductNotFoundException) {
            abort(404, 'Product not found.');
        } catch (InsufficientStockException) {
            abort(409, 'Insufficient stock.');
        }

        return ProductResource::make($product);
    }
}
