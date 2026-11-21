<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Models\User;
use App\Services\PosMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::create(['username' => 'Gabriel', 'full_name' => 'Gabriel', 'password' => 'Admin123!']);
    }

    private function data(array $changes = []): array
    {
        return array_merge(['mailer' => 'smtp', 'from_name' => 'POS-System', 'from_address' => 'sender@example.com', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'encryption' => 'tls', 'smtp_username' => 'sender@example.com', 'smtp_password' => 'FixtureSecret123!'], $changes);
    }

    public function test_email_configuration_routes_require_authentication(): void
    {
        $this->get('/settings/email')->assertRedirect('/login');
        $this->put('/settings/email', $this->data())->assertRedirect('/login');
    }

    public function test_staff_can_open_smtp_settings_without_a_test_email_screen(): void
    {
        $this->actingAs($this->staff())->get('/settings/email')->assertOk()->assertSee('Email configuration')->assertSee('Compose email')->assertDontSee('Send a test email')->assertDontSee('Log (local testing)');
        $this->post('/settings/email/test')->assertNotFound();
    }

    public function test_saved_password_is_encrypted_and_never_rendered(): void
    {
        $this->actingAs($this->staff())->put('/settings/email', $this->data())->assertRedirect('/settings/email')->assertSessionHasNoErrors();
        $settings = MailSetting::findOrFail(1);
        $raw = DB::table('mail_settings')->where('id', 1)->value('smtp_password');
        $this->assertSame('FixtureSecret123!', $settings->smtp_password);
        $this->assertNotSame('FixtureSecret123!', $raw);
        $this->assertStringNotContainsString('FixtureSecret123!', $settings->toJson());
        $this->get('/settings/email')->assertOk()->assertDontSee('FixtureSecret123!')->assertDontSee($raw, false);
    }

    public function test_blank_password_is_preserved_and_can_be_cleared(): void
    {
        $this->actingAs($this->staff());
        $this->put('/settings/email', $this->data())->assertSessionHasNoErrors();
        $this->put('/settings/email', $this->data(['smtp_password' => '', 'from_name' => 'Changed']))->assertSessionHasNoErrors();
        $this->assertSame('FixtureSecret123!', MailSetting::find(1)->smtp_password);
        $this->put('/settings/email', $this->data(['smtp_password' => '', 'clear_password' => 1]))->assertSessionHasNoErrors();
        $this->assertNull(MailSetting::find(1)->smtp_password);
    }

    public function test_invalid_configuration_does_not_flash_password(): void
    {
        $this->actingAs($this->staff())->from('/settings/email')->put('/settings/email', $this->data(['from_address' => 'bad', 'smtp_host' => 'https://bad-host/path', 'smtp_port' => 70000, 'encryption' => 'invalid']))->assertSessionHasErrors(['from_address', 'smtp_host', 'smtp_port', 'encryption'])->assertSessionMissing('_old_input.smtp_password');
        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_host_and_port_are_required(): void
    {
        $this->actingAs($this->staff())->put('/settings/email', $this->data(['smtp_host' => '', 'smtp_port' => '']))->assertSessionHasErrors(['smtp_host', 'smtp_port']);
    }

    public function test_log_mode_cannot_be_configured_for_actual_email(): void
    {
        $this->actingAs($this->staff())->put('/settings/email', $this->data(['mailer' => 'log']))->assertSessionHasErrors('mailer');
        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_legacy_log_settings_are_not_ready_for_sending(): void
    {
        MailSetting::forceCreate(['id' => 1] + $this->data(['mailer' => 'log']));
        $this->assertFalse(app(PosMailService::class)->ready());
    }

    public function test_gmail_requires_a_full_username_and_normalizes_app_password_spaces(): void
    {
        $this->actingAs($this->staff());
        $data = $this->data(['smtp_host' => 'SMTP.GMAIL.COM', 'smtp_username' => 'not-an-email']);
        $this->put('/settings/email', $data)->assertSessionHasErrors('smtp_username');
        $data = $this->data(['smtp_host' => 'SMTP.GMAIL.COM', 'smtp_username' => 'store@gmail.com', 'from_address' => 'store@gmail.com', 'smtp_password' => 'abcd efgh ijkl mnop']);
        $this->put('/settings/email', $data)->assertSessionHasNoErrors();
        $settings = MailSetting::find(1);
        $this->assertSame('smtp.gmail.com', $settings->smtp_host);
        $this->assertSame('abcdefghijklmnop', $settings->smtp_password);
        $this->assertTrue(app(PosMailService::class)->ready());
    }

    public function test_gmail_sending_remains_disabled_without_an_app_password(): void
    {
        MailSetting::forceCreate(['id' => 1] + $this->data(['smtp_host' => 'smtp.gmail.com', 'smtp_username' => 'store@gmail.com', 'smtp_password' => null]));
        $this->assertFalse(app(PosMailService::class)->ready());
    }
}
