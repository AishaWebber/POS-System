<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        return view('customers.index', ['title' => 'Customers', 'records' => Customer::latest('id')->paginate(10)]);
    }

    public function create()
    {
        return view('customers.form', ['title' => 'Add customer', 'record' => []]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', ['title' => 'Edit customer', 'record' => $customer]);
    }

    public function store(Request $request)
    {
        Customer::create($this->data($request));

        return redirect()->route('customers.index')->with('success', 'Customer added.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->data($request));

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+() .-]+$/D'],
        ]);
    }

    public function destroy(Customer $customer)
    {
        if ($customer->sales()->exists()) {
            return back()->with('error', 'This customer has recorded sales and cannot be deleted. You can still edit their details.');
        }
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted.');
    }
}
