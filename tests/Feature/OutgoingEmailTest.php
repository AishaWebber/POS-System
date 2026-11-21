<?php

namespace Tests\Feature;

use App\Mail\SalesReceiptMail;
use App\Mail\StaffMessageMail;
use App\Models\MailSetting;
use App\Models\OutgoingEmail;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\PosMailService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Mail\SentMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class OutgoingEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::create(['username' => 'Gabriel', 'full_name' => 'Gabriel', 'password' => 'Admin123!']);
    }

    private function configure(): void
    {
        MailSetting::forceCreate(['id' => 1, 'mailer' => 'smtp', 'from_name' => 'POS-System', 'from_address' => 'sender@example.com', 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'encryption' => 'tls']);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['recipient' => 'customer@example.com', 'subject' => 'Order collection details', 'message_body' => 'Your order is ready for collection.', 'request_key' => bin2hex(random_bytes(32))], $overrides);
    }

    private function mockSmtp(int $count = 1, bool $fail = false): void
    {
        $email = (new Email)->from('sender@example.com')->to('customer@example.com')->subject('Order collection details')->text('Your order is ready.');
        $sent = new SentMessage(new SymfonySentMessage($email, Envelope::create($email)));
        $pending = \Mockery::mock(PendingMail::class);
        $expect = $pending->shouldReceive('send')->times($count);
        if ($fail) {
            $expect->andThrow(new \RuntimeException('secret-provider-password'));
        } else {
            $expect->andReturn($sent);
        }
        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('to')->times($count)->andReturn($pending);
        $this->partialMock(PosMailService::class, function ($mock) use ($mailer, $count) {
            $mock->shouldReceive('mailer')->times($count)->andReturn($mailer);
        });
    }

    private function sale(): Sale
    {
        $product = Product::create(['name' => 'Classic Notebook', 'price' => '12.35', 'stock_quantity' => 5]);

        return app(SaleService::class)->record($product->id, null, $this->staff->id, 3, bin2hex(random_bytes(32)));
    }

    public function test_sending_routes_require_authentication(): void
    {
        $this->get('/emails')->assertRedirect('/login');
        $this->get('/emails/create')->assertRedirect('/login');
        $this->post('/emails', $this->data())->assertRedirect('/login');
        $this->post('/sales/1/email', $this->data())->assertRedirect('/login');
    }

    public function test_configuration_is_required_and_log_never_reports_success(): void
    {
        MailSetting::forceCreate(['id' => 1, 'mailer' => 'log', 'from_name' => 'POS-System', 'from_address' => 'sender@example.com', 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'encryption' => 'tls']);
        $this->actingAs($this->staff)->from('/emails/create')->post('/emails', $this->data())->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertDatabaseCount('outgoing_emails', 0);
    }

    public function test_email_pages_and_receipt_email_form_render(): void
    {
        $this->configure();
        $this->actingAs($this->staff);
        $sale = $this->sale();
        $this->get('/emails')->assertOk()->assertSee('Email history');
        $this->get('/emails/create')->assertOk()->assertSee('Compose email')->assertSee('Send email');
        $this->get('/sales/'.$sale->id)->assertOk()->assertSee('Send receipt email');
    }

    public function test_composed_email_is_sent_through_smtp_and_audited(): void
    {
        $this->configure();
        $this->mockSmtp();
        $this->actingAs($this->staff)->post('/emails', $this->data())->assertRedirect('/emails')->assertSessionHas('success');
        $record = OutgoingEmail::firstOrFail();
        $this->assertSame('accepted', $record->status);
        $this->assertSame('message', $record->category);
        $this->assertSame('customer@example.com', $record->recipient);
        $this->assertNotNull($record->message_id);
        $this->assertNotNull($record->accepted_at);
    }

    public function test_receipt_email_uses_saved_sale_and_does_not_alter_stock(): void
    {
        $this->configure();
        $sale = $this->sale();
        $this->mockSmtp();
        $this->actingAs($this->staff)->post('/sales/'.$sale->id.'/email', ['recipient' => 'customer@example.com', 'request_key' => bin2hex(random_bytes(32))])->assertRedirect('/sales/'.$sale->id)->assertSessionHas('success');
        $record = OutgoingEmail::firstOrFail();
        $this->assertSame('receipt', $record->category);
        $this->assertSame($sale->id, $record->sale_id);
        $this->assertSame('accepted', $record->status);
        $this->assertSame(2, $sale->product->stock_quantity);
        $this->assertSame(1, Sale::count());
    }

    public function test_repeated_request_does_not_send_twice(): void
    {
        $this->configure();
        $this->mockSmtp(1);
        $this->actingAs($this->staff);
        $data = $this->data();
        $this->post('/emails', $data)->assertSessionHas('success');
        $this->post('/emails', $data)->assertSessionHas('success');
        $this->assertDatabaseCount('outgoing_emails', 1);
    }

    public function test_duplicate_key_with_changed_content_is_rejected(): void
    {
        $this->configure();
        $this->mockSmtp(1);
        $this->actingAs($this->staff);
        $data = $this->data();
        $this->post('/emails', $data);
        $data['message_body'] = 'Different body';
        $this->post('/emails', $data)->assertSessionHas('error');
        $this->assertDatabaseCount('outgoing_emails', 1);
    }

    public function test_transport_failure_is_unconfirmed_and_has_no_false_success(): void
    {
        $this->configure();
        $this->mockSmtp(1, true);
        $this->actingAs($this->staff)->from('/emails/create')->post('/emails', $this->data())->assertSessionHas('error')->assertSessionMissing('success');
        $entry = OutgoingEmail::firstOrFail();
        $this->assertSame('unconfirmed', $entry->status);
        $this->assertNotNull($entry->error_reference);
        $this->assertNull($entry->accepted_at);
        $this->assertStringNotContainsString('secret-provider-password', session('error'));
    }

    public function test_validation_blocks_invalid_recipients_and_header_injection(): void
    {
        $this->actingAs($this->staff)->post('/emails', $this->data(['recipient' => 'bad', 'subject' => "Hello\r\nBcc: injected@example.com", 'message_body' => '', 'request_key' => 'invalid']))->assertSessionHasErrors(['recipient', 'subject', 'message_body', 'request_key']);
        $this->assertDatabaseCount('outgoing_emails', 0);
    }

    public function test_receipt_recipient_is_validated(): void
    {
        $sale = $this->sale();
        $this->actingAs($this->staff)->post('/sales/'.$sale->id.'/email', ['recipient' => 'bad', 'request_key' => 'invalid'])->assertSessionHasErrors(['recipient', 'request_key']);
        $this->assertDatabaseCount('outgoing_emails', 0);
    }

    public function test_sending_is_rate_limited(): void
    {
        $this->configure();
        $this->mockSmtp(5);
        $this->actingAs($this->staff);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/emails', $this->data())->assertRedirect('/emails');
        }$this->post('/emails', $this->data())->assertStatus(429);
    }

    public function test_actual_mail_templates_have_real_content_and_escape_html(): void
    {
        $message = new StaffMessageMail('Collection details', '<script>alert(1)</script>');
        $message->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $message->assertDontSeeInHtml('<script>alert(1)</script>', false);
        $sale = $this->sale();
        $receipt = new SalesReceiptMail($sale);
        $receipt->assertSeeInHtml('Classic Notebook');
        $receipt->assertSeeInHtml('37.05');
        $receipt->assertDontSeeInHtml('configuration test');
    }
}
