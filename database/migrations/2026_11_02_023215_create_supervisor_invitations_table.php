<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('department', 100)->nullable();
        });
        Schema::create('supervisor_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('department', 100);
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('delivery_status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
        Schema::create('management_activity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action');
            $table->string('subject_email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_activity');
        Schema::dropIfExists('supervisor_invitations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('department'));
    }
};
