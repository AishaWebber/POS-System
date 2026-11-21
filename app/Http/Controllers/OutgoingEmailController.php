<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailDeliveryException;
use App\Models\OutgoingEmail;
use App\Models\Sale;
use App\Services\EmailDeliveryService;
use App\Services\PosMailService;
use Illuminate\Http\Request;

class OutgoingEmailController extends Controller
{
    public function index()
    {
        return view('email.index', ['title' => 'Email history', 'records' => OutgoingEmail::with('staff')->latest('id')->paginate(15)]);
    }

    public function create(PosMailService $mail)
    {
        return view('email.compose', ['title' => 'Compose email', 'mailReady' => $mail->ready(), 'sender' => $mail->settings()->from_address, 'requestKey' => bin2hex(random_bytes(32))]);
    }

    public function store(Request $request, EmailDeliveryService $delivery)
    {
        $data = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:254'], 'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'message_body' => ['required', 'string', 'max:10000'], 'request_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ]);
        try {
            $entry = $delivery->sendMessage($data['recipient'], $data['subject'], $data['message_body'], $request->user()->id, $data['request_key']);
        } catch (\InvalidArgumentException|EmailDeliveryException $e) {
            return back()->withInput($request->only('recipient', 'subject', 'message_body'))->with('error', $e->getMessage());
        }

        return redirect()->route('emails.index')->with('success', 'Email accepted by the SMTP server for '.$entry->recipient.'.');
    }

    public function receipt(Request $request, Sale $sale, EmailDeliveryService $delivery)
    {
        $data = $request->validate(['recipient' => ['required', 'email:rfc', 'max:254'], 'request_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        try {
            $entry = $delivery->sendReceipt($sale, $data['recipient'], $request->user()->id, $data['request_key']);
        } catch (\InvalidArgumentException|EmailDeliveryException $e) {
            return back()->withInput($request->only('recipient'))->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success','Receipt email accepted by the SMTP server for '.$entry->recipient.'.');
    }
}
