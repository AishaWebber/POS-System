<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_four_requested_usernames_can_log_in(): void
    {
        $this->seed();
        $this->assertSame(['Gabriel', 'Kenneth', 'Mendoza', 'Catangay'], User::orderBy('id')->pluck('username')->all());
        foreach (['Gabriel' => 'Admin123!', 'Kenneth' => 'Cashier123!', 'Mendoza' => 'Trainee123!', 'Catangay' => 'Catangay123!'] as $name => $password) {
            $this->post('/login', ['username' => strtolower($name), 'password' => $password])->assertRedirect('/');
            $this->assertSame($name, auth()->user()->username);
            $this->post('/logout');
        }
        $this->assertSame(8, Sale::count());
    }

    public function test_case_variant_duplicate_username_is_rejected(): void
    {
        $user = User::create(['username' => 'Gabriel', 'full_name' => 'Gabriel', 'password' => 'Admin123!']);
        $this->actingAs($user)->post('/staff', ['username' => 'GABRIEL', 'full_name' => 'Duplicate', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertSessionHasErrors('username');
    }

    public function test_existing_demo_upgrade_preserves_ids_and_passwords(): void
    {
        $ids = [];
        foreach ([['admin', 'Alex Santos', 'Admin123!'], ['cashier', 'Jamie Reyes', 'Cashier123!'], ['trainee', 'Sam Cruz', 'Trainee123!']] as [$username,$name,$password]) {
            $ids[] = User::create(['username' => $username, 'full_name' => $name, 'password' => $password])->id;
        }
        $migration = require database_path('migrations/2026_11_20_000002_update_demo_staff_usernames.php');
        $migration->up();
        $this->assertSame('Gabriel', User::find($ids[0])->username);
        $this->assertTrue(Hash::check('Admin123!', User::find($ids[0])->password));
        $this->assertSame('Kenneth', User::find($ids[1])->username);
        $this->assertSame('Mendoza', User::find($ids[2])->username);
        $this->assertTrue(User::where('username', 'Catangay')->exists());
        $this->assertSame(4, User::count());
    }
}
