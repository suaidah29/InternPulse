<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->boolean('is_active')->default(true);
            $t->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('supervisor_invitations', function (Blueprint $t): void {
            $t->string('role')->default('supervisor');
            $t->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('departments', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 100)->unique();
            $t->timestamps();
        });
        foreach (['IT', 'Marketing', 'Graphic Design', 'Human Resources', 'Operations'] as $name) {
            DB::table('departments')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (DB::table('users')->whereNotNull('department')->distinct()->pluck('department') as $name) {
            DB::table('departments')->insertOrIgnore(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('work_tasks', function (Blueprint $t): void {
            $t->id();
            $t->string('title', 180);
            $t->text('description')->nullable();
            $t->foreignId('assigned_to')->constrained('users');
            $t->foreignId('created_by')->constrained('users');
            $t->string('department', 100)->nullable();
            $t->string('category', 100)->nullable();
            $t->string('priority')->default('normal');
            $t->string('status')->default('todo');
            $t->date('due_date')->nullable();
            $t->text('progress_note')->nullable();
            $t->string('work_url', 2000)->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['assigned_to', 'status']);
        });
        Schema::create('work_files', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('task_id')->constrained('work_tasks')->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->string('path');
            $t->string('original_name');
            $t->timestamps();
        });
        Schema::create('due_date_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('task_id')->constrained('work_tasks');
            $t->foreignId('requested_by')->constrained('users');
            $t->date('requested_date');
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('daily_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->date('activity_date');
            $t->text('points');
            $t->text('summary');
            $t->string('status')->default('pending');
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['user_id', 'activity_date']);
        });
        Schema::create('attendance_records', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->date('attendance_date');
            $t->timestamp('checked_in_at');
            $t->string('photo_path');
            $t->string('status')->default('pending');
            $t->text('note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['user_id', 'attendance_date']);
        });
        Schema::create('intern_guides', function (Blueprint $t): void {
            $t->id();
            $t->string('title', 180);
            $t->string('department', 100)->nullable();
            $t->text('body');
            $t->string('file_path')->nullable();
            $t->string('file_name')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['intern_guides', 'attendance_records', 'daily_logs', 'due_date_requests', 'work_files', 'work_tasks', 'departments'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('supervisor_invitations', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('supervisor_id');
            $t->dropColumn('role');
        });
        Schema::table('users', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('supervisor_id');
            $t->dropColumn('is_active');
        });
    }
};
