<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\MocksRegistrationSmtp;

    private function account(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'New Team Member',
            'email' => 'member@example.com',
            'username' => 'New.Staff-1',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ], $overrides);
    }

    public function test_guests_can_open_registration_from_login(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('register'), false);
        $this->get('/register')->assertOk()->assertSee('Create a staff account')
            ->assertSee('name="_token"', false)->assertSee(route('login'), false);
    }

    public function test_registration_hashes_password_signs_in_and_allows_later_login(): void
    {
        $this->configureRegistrationSmtp();
        $this->captureRegistrationSmtp();
        $this->post('/register', $this->account(['avatar' => 'arbitrary.jpg', 'id' => 999, 'email_verified_at' => now()]))
            ->assertSessionHasNoErrors()->assertRedirect('/email/verify');
        $user = User::sole();
        $this->assertSame('New.Staff-1', $user->username);
        $this->assertSame('New Team Member', $user->full_name);
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
        $this->assertNotSame('StrongPass123!', $user->password);
        $this->assertNull($user->avatar);
        $this->assertNotSame(999, $user->id);
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('member@example.com', $user->email);
        $this->get('/')->assertRedirect('/email/verify');
        $this->get('/staff')->assertRedirect('/email/verify');
        $this->assertCount(1, $this->verificationMessages);
        $this->get($this->verificationMessages[0]->verificationUrl)->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get('/staff')->assertOk()->assertSee('New Team Member');
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['username' => 'new.staff-1', 'password' => 'StrongPass123!'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_case_variant_of_existing_username_is_rejected(): void
    {
        User::create($this->account(['username' => 'Gabriel']));
        $this->post('/register', $this->account(['username' => 'GABRIEL']))
            ->assertSessionHasErrors('username');
        $this->assertSame(1, User::count());
        $this->assertGuest();
    }

    public function test_invalid_account_fields_are_rejected_without_flashing_passwords(): void
    {
        $this->from('/register')->post('/register', $this->account([
            'full_name' => '', 'username' => 'invalid username',
            'password' => 'short', 'password_confirmation' => 'different',
        ]))->assertRedirect('/register')->assertSessionHasErrors(['full_name', 'username', 'password'])
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_password_confirmation_is_required_and_must_match(): void
    {
        $this->post('/register', $this->account(['password_confirmation' => 'DifferentPass123!']))
            ->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_multibyte_password_over_72_bytes_is_rejected(): void
    {
        $password = str_repeat('é', 40);
        $this->post('/register', $this->account(['password' => $password, 'password_confirmation' => $password]))
            ->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
    }

    public function test_invalid_form_preserves_and_escapes_non_secret_input(): void
    {
        $this->from('/register')->post('/register', $this->account([
            'full_name' => '<script>alert(1)</script>', 'password_confirmation' => 'mismatch',
        ]))->assertRedirect('/register');
        $this->get('/register')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('New.Staff-1')->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('StrongPass123!');
    }

    public function test_authenticated_staff_cannot_create_accounts_through_guest_registration(): void
    {
        $user = User::create($this->account());
        $this->actingAs($user)->get('/register')->assertRedirect('/');
        $this->post('/register', $this->account(['username' => 'AnotherUser', 'email' => 'another@example.com']))->assertRedirect('/');
        $this->assertSame(1, User::count());
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', [])->assertSessionHasErrors();
        }
        $this->post('/register', $this->account())->assertStatus(429);
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }
}
