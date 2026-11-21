@extends('layouts.app')
@section('content')
<div class="page-heading"><div><p class="eyebrow">OUTGOING MAIL</p><h1>Compose email</h1><p class="muted">Send a message directly through your configured SMTP account.</p></div><a href="{{ route('emails.index') }}" class="text-link">Email history →</a></div>
@if(!$mailReady)<div role="alert" class="notice error">Configure SMTP in <a class="text-link" href="{{ route('email.edit') }}">Email settings</a> before sending.</div>@endif
<form method="POST" action="{{ route('emails.store') }}" class="panel form-panel stack">@csrf<input type="hidden" name="request_key" value="{{ $requestKey }}">
<p class="field-help">From: {{ $sender }} · <a href="{{ route('email.edit') }}" class="text-link">Change email settings</a></p>
<div><label for="recipient">Recipient email</label><input id="recipient" name="recipient" type="email" maxlength="254" required value="{{ old('recipient') }}"></div>
<div><label for="subject">Subject</label><input id="subject" name="subject" maxlength="200" required value="{{ old('subject') }}"></div>
<div><label for="message_body">Message</label><textarea id="message_body" name="message_body" maxlength="10000" required rows="9">{{ old('message_body') }}</textarea></div>
<p class="field-help">Send only when the recipient and message are ready. Delivery history records SMTP acceptance; final inbox placement depends on the receiving mail service.</p>
<div class="form-footer"><button type="submit" class="button primary" @disabled(!$mailReady)>Send email</button><a href="{{ route('emails.index') }}" class="button subtle">Cancel</a></div></form>
@endsection
