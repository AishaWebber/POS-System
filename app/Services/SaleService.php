<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function record(int $productId, ?int $customerId, int $staffId, int $quantity, string $requestKey): Sale
    {
        if ($quantity < 1 || $quantity > 1000000 || ! preg_match('/^[a-f0-9]{64}$/D', $requestKey)) {
            throw new InvalidArgumentException('Enter a valid quantity from 1 to 1,000,000 and submit the sale form again.');
        }
        try {
            return DB::transaction(function () use ($productId, $customerId, $staffId, $quantity, $requestKey) {
                $existing = Sale::where('request_key', $requestKey)->first();
                if ($existing) {
                    if ((int) $existing->sold_by !== $staffId) {
                        throw new InvalidArgumentException('This sale reference belongs to another session.');
                    }

                    return $existing;
                }
                // The conditional decrement is atomic; insufficient stock changes no row.
                $changed = Product::whereKey($productId)->where('is_archived', false)->where('stock_quantity', '>=', $quantity)->decrement('stock_quantity', $quantity);
                if ($changed !== 1) {
                    throw new InvalidArgumentException('Sale rejected: the product is unavailable or the quantity exceeds available stock.');
                }
                $product = Product::findOrFail($productId);
                $staff = User::find($staffId);
                $customer = $customerId ? Customer::find($customerId) : null;
                if (! $staff || ($customerId && ! $customer)) {
                    throw new InvalidArgumentException('The selected customer or staff account no longer exists.');
                }
                $cents = (int) str_replace('.', '', $product->price);
                $total = $cents * $quantity;
                if ($total > 9999999999) {
                    throw new InvalidArgumentException('Sale total exceeds the database limit. Reduce the quantity.');
                }

                return Sale::create([
                    'product_id' => $productId, 'customer_id' => $customerId, 'sold_by' => $staffId, 'quantity' => $quantity,
                    'total_price' => number_format($total / 100, 2, '.', ''), 'product_name' => $product->name,
                    'customer_name' => $customer?->full_name ?? 'Walk-in customer', 'staff_name' => $staff->full_name, 'request_key' => $requestKey,
                ]);
            });
        } catch (QueryException $e) {
            // A concurrent duplicate request can reach the unique-key guard.
            $existing = Sale::where('request_key', $requestKey)->where('sold_by', $staffId)->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }
    }
}
