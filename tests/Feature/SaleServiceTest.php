<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Customer $customer;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::create(['name' => 'Notebook', 'price' => '19.95', 'stock_quantity' => 10]);
        $this->customer = Customer::create(['full_name' => 'Test Customer', 'email' => 'test@example.com']);
        $this->staff = User::create(['username' => 'tester', 'full_name' => 'Test Staff', 'password' => 'TestPass123!']);
    }

    private function sell(int $quantity = 2, ?int $customer = null, ?string $key = null): Sale
    {
        return app(SaleService::class)->record($this->product->id, $customer, $this->staff->id, $quantity, $key ?? bin2hex(random_bytes(32)));
    }

    public function test_sale_records_relationships_and_deducts_stock(): void
    {
        $sale = $this->sell(3, $this->customer->id);
        $this->assertSame(7, $this->product->fresh()->stock_quantity);
        $this->assertSame('59.85', $sale->total_price);
        $this->assertTrue($sale->customer->is($this->customer));
        $this->assertTrue($sale->staff->is($this->staff));
        $this->assertTrue($sale->product->is($this->product));
    }

    public function test_walk_in_customer_is_optional(): void
    {
        $sale = $this->sell();
        $this->assertNull($sale->customer_id);
        $this->assertSame('Walk-in customer', $sale->customer_name);
    }

    public function test_oversell_changes_neither_stock_nor_sales(): void
    {
        try {
            $this->sell(11);
            $this->fail('Oversell accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('stock', $e->getMessage());
        }
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, Sale::count());
    }

    public function test_can_sell_all_stock_then_no_more(): void
    {
        $this->sell(10);
        $this->assertSame(0, $this->product->fresh()->stock_quantity);
        try {
            $this->sell(1);
            $this->fail('Out of stock sale accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(1, Sale::count());
        }
    }

    public function test_invalid_customer_rolls_back_stock(): void
    {
        try {
            $this->sell(2, 99999);
            $this->fail('Invalid customer accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(10, $this->product->fresh()->stock_quantity);
        }
        $this->assertSame(0, Sale::count());
    }

    public function test_archived_products_cannot_be_sold(): void
    {
        $this->product->update(['is_archived' => true]);
        try {
            $this->sell();
            $this->fail('Archived product sold');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(10, $this->product->fresh()->stock_quantity);
        }
    }

    public function test_duplicate_request_does_not_deduct_twice(): void
    {
        $key = bin2hex(random_bytes(32));
        $a = $this->sell(2, null, $key);
        $b = $this->sell(2, null, $key);
        $this->assertTrue($a->is($b));
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, Sale::count());
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        foreach ([0, -1, 1000001] as $quantity) {
            try {
                $this->sell($quantity);
                $this->fail('Invalid quantity accepted');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(10, $this->product->fresh()->stock_quantity);
            }
        }
    }

    public function test_malformed_request_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->sell(1, null, 'invalid');
    }

    public function test_total_uses_integer_cent_arithmetic(): void
    {
        $this->product->update(['price' => '0.10']);
        $this->assertSame('0.30', $this->sell(3)->total_price);
    }

    public function test_total_overflow_rolls_back_stock(): void
    {
        $this->product->update(['price' => '99999999.99']);
        try {
            $this->sell(2);
            $this->fail('Overflow accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(10, $this->product->fresh()->stock_quantity);
        }
    }

    public function test_historical_totals_and_names_survive_edits(): void
    {
        $sale = $this->sell(1, $this->customer->id);
        $this->product->update(['name' => 'Changed', 'price' => '999.00']);
        $this->customer->update(['full_name' => 'Changed']);
        $this->assertSame('Notebook', $sale->fresh()->product_name);
        $this->assertSame('Test Customer', $sale->fresh()->customer_name);
        $this->assertSame('19.95', $sale->fresh()->total_price);
    }

    public function test_request_keys_cannot_be_reused_by_another_staff_member(): void
    {
        $key = bin2hex(random_bytes(32));
        $this->sell(1, null, $key);
        $other = User::create(['username' => 'other', 'full_name' => 'Other', 'password' => 'TestPass123!']);
        $this->expectException(\InvalidArgumentException::class);
        app(SaleService::class)->record($this->product->id,null,$other->id,1,$key);
    }
}
