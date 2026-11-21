<?php

namespace Tests\Feature;

use App\Mail\AccountVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\MocksRegistrationSmtp;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use MocksRegistrationSmtp, RefreshDatabase;

    private function pending(): User
    {
        return User::create(['username' => 'NewMember', 'full_name' => 'New Member', 'email' => 'member@example.com', 'password' => 'StrongPass123!']);
    }

    private function registration(array $overrides = []): array
    {
        return array_merge(['username' => 'NewMember', 'full_name' => 'New Member', 'email' => 'member@example.com', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'], $overrides);
    }

    private function link(User $user, int $minutes = 60, array $overrides = []): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), array_merge(['id' => $user->id, 'hash' => sha1($user->email)], $overrides));
    }

    public function test_registration_normalizes_email_and_sends_real_confirmation_content(): void
    {
        $this->configureRegistrationSmtp();
        $this->captureRegistrationSmtp();
        $this->post('/register', $this->registration(['email' => ' MEMBER@EXAMPLE.COM ']))
            ->assertRedirect('/email/verify')->assertSessionHas('success');
        $user = User::sole();
        $this->assertSame('member@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        $message = $this->verificationMessages[0];
        $this->assertInstanceOf(AccountVerificationMail::class, $message);
        $message->assertSeeInHtml('Confirm email address');
        $message->assertSeeInHtml('New Member');
        $message->assertSeeInText($message->verificationUrl);
        $this->assertStringContainsString('signature=', $message->verificationUrl);
        $this->assertDatabaseHas('outgoing_emails', ['category' => 'verification', 'recipient' => 'member@example.com', 'status' => 'accepted']);
        $this->get('/email/verify')->assertOk()->assertSee('member@example.com');
    }

    public function test_missing_invalid_and_duplicate_email_cannot_register(): void
    {
        $this->pending();
        foreach (['', 'not-an-email', 'MEMBER@example.com'] as $email) {
            $this->post('/register', $this->registration(['username' => 'OtherMember', 'email' => $email]))->assertSessionHasErrors('email');
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_all_workspace_routes_block_pending_accounts_but_logout_works(): void
    {
        $this->actingAs($this->pending());
        foreach (['/', '/products', '/customers', '/staff', '/sales', '/sales/create', '/settings/email', '/emails', '/media/sample-staff-1.jpg'] as $url) {
            $this->get($url)->assertRedirect('/email/verify');
        }
        foreach (['/products', '/customers', '/staff', '/sales', '/emails', '/sales/1/email'] as $url) {
            $this->post($url, [])->assertRedirect('/email/verify');
        }
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_valid_link_confirms_once_and_unlocks_workspace(): void
    {
        $user = $this->pending();
        $url = $this->link($user);
        $this->actingAs($user)->get($url)->assertRedirect('/');
        $first = $user->fresh()->email_verified_at;
        $this->assertNotNull($first);
        $this->travel(1)->minutes();
        $this->get($url)->assertRedirect('/');
        $this->assertTrue($first->equalTo($user->fresh()->email_verified_at));
        $this->get('/')->assertOk();
    }

    public function test_tampered_expired_wrong_user_and_wrong_email_links_fail(): void
    {
        $user = $this->pending();
        $this->actingAs($user)->get($this->link($user).'x')->assertForbidden();
        $this->get($this->link($user, -1))->assertForbidden();
        $this->get($this->link($user, 60, ['hash' => sha1('other@example.com')]))->assertForbidden();
        $other = User::create(['username' => 'Other', 'full_name' => 'Other', 'email' => 'other@example.com', 'password' => 'StrongPass123!']);
        $this->actingAs($other)->get($this->link($user))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_confirmation_link_survives_login_in_another_browser(): void
    {
        $user = $this->pending();
        $url = $this->link($user);
        $this->get($url)->assertRedirect('/login');
        $this->post('/login', ['username' => $user->username, 'password' => 'StrongPass123!'])->assertRedirect($url);
        $this->get($url)->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_pending_login_returns_to_confirmation_notice(): void
    {
        $user = $this->pending();
        $this->post('/login', ['username' => $user->username, 'password' => 'StrongPass123!'])->assertRedirect('/email/verify');
    }

    public function test_smtp_failure_preserves_pending_account_and_reports_no_false_success(): void
    {
        $this->configureRegistrationSmtp();
        $this->captureRegistrationSmtp(true);
        $this->post('/register', $this->registration())->assertRedirect('/email/verify')
            ->assertSessionHas('error')->assertSessionMissing('success');
        $user = User::sole();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('outgoing_emails', ['category' => 'verification', 'status' => 'unconfirmed']);
        $this->assertStringNotContainsString('private-provider-secret', session('error'));
        $this->get('/')->assertRedirect('/email/verify');
    }

    public function test_no_smtp_configuration_keeps_account_pending_until_resend(): void
    {
        $this->post('/register', $this->registration())->assertRedirect('/email/verify')->assertSessionHas('error');
        $this->assertDatabaseCount('outgoing_emails', 0);
        $this->assertFalse(User::sole()->hasVerifiedEmail());
        $this->configureRegistrationSmtp();
        $this->captureRegistrationSmtp();
        $this->post('/email/verification-notification')->assertRedirect('/email/verify')->assertSessionHas('success');
        $this->get($this->verificationMessages[0]->verificationUrl)->assertRedirect('/');
    }

    public function test_resend_is_rate_limited_and_unavailable_to_guests(): void
    {
        $this->post('/email/verification-notification')->assertRedirect('/login');
        $this->configureRegistrationSmtp();
        $this->captureRegistrationSmtp(false, 2);
        $this->actingAs($this->pending());
        $this->post('/email/verification-notification')->assertRedirect('/email/verify');
        $this->post('/email/verification-notification')->assertRedirect('/email/verify');
        $this->post('/email/verification-notification')->assertStatus(429);
        $this->assertDatabaseCount('outgoing_emails', 2);
    }

    public function test_internal_username_only_staff_continue_to_work(): void
    {
        $user = User::create(['username' => 'Existing', 'full_name' => 'Existing Staff', 'password' => 'StrongPass123!']);
        $this->actingAs($user)->get('/')->assertOk();
        $this->get('/email/verify')->assertRedirect('/');
        $this->post('/email/verification-notification')->assertRedirect('/');
        $this->assertDatabaseCount('outgoing_emails', 0);
    }
}
