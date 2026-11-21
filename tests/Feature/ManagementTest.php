<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\ImageStorage;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::create(['username' => 'admin', 'full_name' => 'Test Admin', 'password' => 'Admin123!']);
    }

    public function test_login_is_public_but_management_is_protected(): void
    {
        $this->get('/login')->assertOk();
        foreach (['/', '/products', '/products/create', '/products/1/edit', '/customers', '/customers/create', '/staff', '/staff/create', '/sales', '/sales/create', '/sales/1', '/media/sample-staff-1.jpg'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        foreach (['products', 'customers', 'staff', 'sales'] as $resource) {
            $this->post('/'.$resource)->assertRedirect(route('login'));
        }
        foreach (['products', 'customers', 'staff'] as $resource) {
            $this->put('/'.$resource.'/1')->assertRedirect(route('login'));
            $this->delete('/'.$resource.'/1')->assertRedirect(route('login'));
        }
    }

    public function test_correct_login_and_logout(): void
    {
        $user = $this->staff();
        $this->post('/login', ['username' => 'admin', 'password' => 'Admin123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_bad_login_is_rejected(): void
    {
        $this->staff();
        $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_all_management_screens_render_for_staff(): void
    {
        $user = $this->staff();
        $this->actingAs($user);
        $product = Product::create(['name' => 'Test product', 'price' => '12.35', 'stock_quantity' => 5]);
        $customer = Customer::create(['full_name' => 'Test Customer', 'email' => 'test@example.com']);
        foreach (['/', '/products', '/products/create', "/products/$product->id/edit", '/customers', '/customers/create', "/customers/$customer->id/edit", '/staff', '/staff/create', "/staff/$user->id/edit", '/sales', '/sales/create'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_product_create_update_archive_and_restore(): void
    {
        $this->actingAs($this->staff());
        $this->post('/products', ['name' => 'Test', 'price' => '12.35', 'stock_quantity' => 5])->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->put('/products/'.$product->id, ['name' => 'Updated', 'price' => '20.00', 'stock_quantity' => 8])->assertSessionHasNoErrors();
        $this->assertSame('Updated', $product->fresh()->name);
        $this->delete('/products/'.$product->id)->assertRedirect('/products');
        $this->assertTrue($product->fresh()->is_archived);
        $this->get('/products')->assertDontSee('Updated');
        $this->get('/products?archived=1')->assertSee('Updated');
        $this->delete('/products/'.$product->id);
        $this->assertFalse($product->fresh()->is_archived);
    }

    public function test_customer_crud(): void
    {
        $this->actingAs($this->staff());
        $this->post('/customers', ['full_name' => 'New Customer', 'email' => 'new@example.com', 'phone' => '09171234567'])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->put('/customers/'.$customer->id, ['full_name' => 'Updated', 'email' => 'updated@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('Updated', $customer->fresh()->full_name);
        $this->delete('/customers/'.$customer->id)->assertRedirect('/customers');
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_staff_crud_hashes_and_preserves_passwords(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin);
        $this->post('/staff', ['username' => 'newstaff', 'full_name' => 'New Staff', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertSessionHasNoErrors();
        $user = User::where('username', 'newstaff')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
        $this->put('/staff/'.$user->id, ['username' => 'newstaff', 'full_name' => 'Updated Staff', 'password' => ''])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('StrongPass123!', $user->fresh()->password));
        $this->delete('/staff/'.$user->id)->assertRedirect('/staff');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->delete('/staff/'.$admin->id)->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_form_validation_rejects_invalid_values(): void
    {
        $user = $this->staff();
        $this->actingAs($user);
        $this->post('/products', ['name' => '', 'price' => '-1', 'stock_quantity' => -1])->assertSessionHasErrors(['name', 'price', 'stock_quantity']);
        $this->post('/customers', ['full_name' => '', 'email' => 'bad-email'])->assertSessionHasErrors(['full_name', 'email']);
        $this->post('/staff', ['username' => 'admin', 'full_name' => 'Duplicate', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors(['username', 'password']);
        $this->post('/sales', ['product_id' => 1, 'quantity' => 0, 'request_key' => 'invalid'])->assertSessionHasErrors(['quantity', 'request_key']);
    }

    public function test_reference_protection_preserves_sales(): void
    {
        $user = $this->staff();
        $other = User::create(['username' => 'cashier', 'full_name' => 'Cashier', 'password' => 'Cashier123!']);
        $customer = Customer::create(['full_name' => 'Customer', 'email' => 'test@example.com']);
        $product = Product::create(['name' => 'Product', 'price' => '1.00', 'stock_quantity' => 5]);
        app(SaleService::class)->record($product->id, $customer->id, $other->id, 1, bin2hex(random_bytes(32)));
        $this->actingAs($user);
        $this->delete('/customers/'.$customer->id)->assertSessionHas('error');
        $this->delete('/staff/'.$other->id)->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_sale_endpoint_uses_server_price_and_user(): void
    {
        $user = $this->staff();
        $product = Product::create(['name' => 'Product', 'price' => '12.35', 'stock_quantity' => 5]);
        $this->actingAs($user);
        $this->post('/sales', ['product_id' => $product->id, 'quantity' => 3, 'request_key' => bin2hex(random_bytes(32)), 'sold_by' => 999, 'total_price' => '0.01'])->assertSessionHasNoErrors();
        $sale = Sale::firstOrFail();
        $this->assertSame('37.05', $sale->total_price);
        $this->assertSame($user->id, $sale->sold_by);
        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->get('/sales/'.$sale->id)->assertOk()->assertSee('37.05');
    }

    public function test_disguised_script_upload_is_rejected(): void
    {
        $this->actingAs($this->staff());
        $this->post('/products', ['name' => 'Bad upload', 'price' => '1.00', 'stock_quantity' => 1, 'image' => UploadedFile::fake()->createWithContent('bad.jpg', '<?php echo 1; ?>')])->assertSessionHasErrors('image');
        $this->assertSame(0, Product::count());
    }

    public function test_image_preparation_and_avatar_crop(): void
    {
        $images = app(ImageStorage::class);
        $product = $images->save(UploadedFile::fake()->image('photo.png', 1600, 900));
        $avatar = $images->save(UploadedFile::fake()->image('avatar.png', 600, 400), true);
        try {
            $a = getimagesize(storage_path('app/private/uploads/'.$product));
            $b = getimagesize(storage_path('app/private/uploads/'.$avatar));
            $this->assertSame(800, $a[0]);
            $this->assertSame(450, $a[1]);
            $this->assertSame(IMAGETYPE_JPEG, $a[2]);
            $this->assertSame(256, $b[0]);
            $this->assertSame(256, $b[1]);
        } finally {
            $images->delete($product);
            $images->delete($avatar);
        }
    }

    public function test_names_are_escaped_in_views(): void
    {
        $this->actingAs($this->staff());
        Product::create(['name' => '<script>alert(1)</script>', 'price' => '1.00', 'stock_quantity' => 1]);
        $this->get('/products')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
    }
}
