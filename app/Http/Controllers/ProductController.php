<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'archived' => ['nullable', 'in:0,1']]);
        $archived = ($filters['archived'] ?? '0') === '1';
        $q = $filters['q'] ?? '';
        $records = Product::where('is_archived', $archived)->when($q, fn ($query) => $query->where('name', 'like', '%'.$q.'%'))->latest('id')->paginate(8)->withQueryString();

        return view('products.index', compact('records', 'archived', 'q') + ['title' => 'Products']);
    }

    public function create()
    {
        return view('products.form', ['title' => 'Add product', 'record' => []]);
    }

    public function edit(Product $product)
    {
        return view('products.form', ['title' => 'Edit product', 'record' => $product]);
    }

    public function store(Request $request, ImageStorage $images)
    {
        return $this->save($request, $images);
    }

    public function update(Request $request, Product $product, ImageStorage $images)
    {
        return $this->save($request, $images, $product);
    }

    private function save(Request $request, ImageStorage $images, ?Product $product = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'regex:/^\d{1,8}(\.\d{1,2})?$/D'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'image' => ['nullable', 'file', 'max:2048'],
        ]);
        unset($data['image']);
        try {
            $image = $images->save($request->file('image'));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['image' => $e->getMessage()]);
        }
        $old = $product?->image;
        if ($image) {
            $data['image'] = $image;
        }
        try {
            if ($product) {
                $product->update($data);
            } else {
                Product::create($data);
            }
        } catch (\Throwable $e) {
            if ($image) {
                $images->delete($image);
            } throw $e;
        }
        if ($image && $old) {
            $images->delete($old);
        }

        return redirect()->route('products.index')->with('success', $product ? 'Product updated.' : 'Product added.');
    }

    public function destroy(Product $product)
    {
        $wasArchived = $product->is_archived;
        $product->update(['is_archived' => ! $wasArchived]);

        return redirect()->route('products.index')->with('success', $wasArchived ? 'Product restored.' : 'Product archived. Its sales history is preserved.');
    }
}
