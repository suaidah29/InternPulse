<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_blockers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->foreignId('intern_id')->constrained('users');
            $table->string('category');
            $table->text('description');
            $table->string('status')->default('open');
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['task_id', 'status']);
        });
        Schema::create('intern_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intern_id')->constrained('users');
            $table->foreignId('supervisor_id')->constrained('users');
            $table->string('period');
            $table->unsignedTinyInteger('quality');
            $table->unsignedTinyInteger('communication');
            $table->unsignedTinyInteger('teamwork');
            $table->text('feedback');
            $table->timestamps();
            $table->unique(['intern_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intern_evaluations');
        Schema::dropIfExists('task_blockers');
    }
};
