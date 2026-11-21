<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_emails', function (Blueprint $t) {
            $t->id();
            $t->string('request_key', 64)->unique();
            $t->string('payload_hash', 64);
            $t->string('category', 20);
            $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('recipient', 254);
            $t->string('subject', 200);
            $t->string('from_address', 254);
            $t->string('status', 20)->index();
            $t->string('message_id', 255)->nullable();
            $t->string('error_reference', 36)->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_emails');
    }
};
