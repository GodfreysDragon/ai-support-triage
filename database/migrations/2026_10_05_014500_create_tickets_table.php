<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('customer_email')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('pending')->index();

            // Triage output (filled in by the queued TriageTicket job).
            $table->string('category')->nullable();
            $table->string('priority')->nullable()->index();
            $table->string('sentiment')->nullable();
            $table->text('summary')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->text('error')->nullable();

            // Last streamed draft reply, persisted once the stream completes.
            $table->text('draft_reply')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
