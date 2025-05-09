<?php

namespace App\Repositories;

use App\Models\Product;
use App\Data\ProductData;
use App\Jobs\ImportProductsFromExcelJob;
use App\Http\Requests\ImportProductRequest;
use App\Contracts\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function getProducts(): LengthAwarePaginator
    {
        $filters = request()->only(
            'search',
        );

        return Product::query()
            ->filter($filters)
            ->orderBy('id', 'asc')
            ->paginate(10);
    }

    public function create(ProductData $data): Product
    {
        return Product::create($data->toArray());
    }

    public function update(Product $product, ProductData $data): Product
    {
        $product->update($data->toArray());
        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function import(ImportProductRequest $request): void
    {
        $path = $request->file('file')->store('products');
        dispatch(new ImportProductsFromExcelJob($path));
    }
}
