<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->info('Demo seeding is disabled in production.');

            return;
        }
        if (User::exists()) {
            $this->command?->info('Existing installation found; sample data was not duplicated.');

            return;
        }
        File::ensureDirectoryExists(storage_path('app/private/uploads'));
        foreach (glob(public_path('assets/samples/*.jpg')) as $file) {
            File::copy($file, storage_path('app/private/uploads/'.basename($file)));
        }
        DB::transaction(function () {
            $products = [];
            $users = [];
            $customers = [];
            foreach ([
                ['Classic Notebook', '85.00', 50], ['Gel Pen Set', '125.50', 100], ['Ceramic Coffee Mug', '199.00', 24],
                ['Everyday Canvas Tote', '249.75', 20], ['Minimal Desk Lamp', '649.00', 10], ['Insulated Water Bottle', '399.00', 18],
                ['Pastel Sticky Notes', '49.50', 5], ['Everyday Pencil Pouch', '159.00', 0],
            ] as $i => [$name,$price,$stock]) {
                $products[] = Product::create(['name' => $name, 'price' => $price, 'stock_quantity' => $stock, 'image' => 'sample-product-'.($i + 1).'.jpg']);
            }
            foreach ([['Gabriel', 'Gabriel', 'Admin123!'], ['Kenneth', 'Kenneth', 'Cashier123!'], ['Mendoza', 'Mendoza', 'Trainee123!'], ['Catangay', 'Catangay', 'Catangay123!']] as $i => [$username,$name,$password]) {
                $users[] = User::create(['username' => $username, 'full_name' => $name, 'password' => $password, 'avatar' => 'sample-staff-'.($i + 1).'.jpg']);
            }
            foreach (['Morgan Garcia', 'Riley Torres', 'Casey Mendoza', 'Jordan Lim', 'Taylor Ramos', 'Avery Flores', 'Charlie Tan', 'Quinn Rivera', 'Robin Diaz', 'Sky Lopez'] as $i => $name) {
                $customers[] = Customer::create(['full_name' => $name, 'email' => 'customer'.($i + 1).'@example.com', 'phone' => '0917'.str_pad((string) ($i + 1), 7, '0', STR_PAD_LEFT)]);
            }
            foreach ([[0, 0, 0, 2], [1, 1, 1, 4], [2, null, 1, 1], [3, 2, 0, 2], [4, 3, 1, 1], [5, null, 0, 2], [0, 4, 1, 1], [6, 5, 0, 2]] as $i => [$product,$customer,$staff,$quantity]) {
                $sale = app(SaleService::class)->record($products[$product]->id, $customer === null ? null : $customers[$customer]->id, $users[$staff]->id, $quantity, hash('sha256', 'sample-sale-'.$i));
                $sale->created_at = now()->subDays(7 - $i)->subMinutes($i * 37);
                $sale->save();
            }
        });
        $this->command?->info('Loaded 8 products, 10 customers, 4 staff accounts, and 8 sample sales.');
    }
}
