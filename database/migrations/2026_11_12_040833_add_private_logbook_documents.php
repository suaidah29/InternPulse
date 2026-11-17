<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_logs', function (Blueprint $table): void {
            $table->string('photo_path')->nullable();
        });
        Schema::create('logbook_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('supervisor_id')->constrained('users');
            $table->string('title', 180);
            $table->string('path');
            $table->string('original_name');
            $table->string('sha256', 64);
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->string('reviewer_name')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_documents');
        Schema::table('daily_logs', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }
};
