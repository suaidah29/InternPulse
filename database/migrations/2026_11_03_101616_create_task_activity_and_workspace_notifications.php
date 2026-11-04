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
        Schema::create('task_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('kind');
            $table->text('body');
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });
        Schema::create('workspace_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('message');
            $table->foreignId('task_id')->nullable()->constrained('work_tasks')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_notifications');
        Schema::dropIfExists('task_activity');
    }
};
