<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\PosMailService;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index()
    {
        return view('sales.index', ['title' => 'Sales history', 'records' => Sale::latest('id')->paginate(15)]);
    }

    public function create()
    {
        return view('sales.form', [
            'title' => 'Record sale', 'products' => Product::where('is_archived', false)->orderBy('name')->get(),
            'customers' => Customer::orderBy('full_name')->get(), 'requestKey' => bin2hex(random_bytes(32)),
        ]);
    }

    public function store(Request $request, SaleService $sales)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'], 'customer_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'request_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ]);
        try {
            $sale = $sales->record((int) $data['product_id'], isset($data['customer_id']) ? (int) $data['customer_id'] : null, $request->user()->id, (int) $data['quantity'], $data['request_key']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['sale' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['sale' => 'The sale could not be saved. Stock was not deducted. Please try again.']);
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Sale recorded successfully. Inventory updated.');
    }

    public function show(Sale $sale)
    {
        return view('sales.show', ['title' => 'Sale receipt', 'sale' => $sale, 'mailReady' => app(PosMailService::class)->ready(), 'emailRequestKey' => bin2hex(random_bytes(32))]);
    }
}
