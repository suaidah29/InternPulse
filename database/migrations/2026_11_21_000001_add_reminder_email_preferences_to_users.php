<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People opt out per reminder type. Both default to on: the reminders exist
     * to stop a day slipping by unnoticed, and an intern who never reads the bell
     * would otherwise miss them entirely.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_logbook_reminders')->default(true)->after('is_active');
            $table->boolean('email_task_reminders')->default(true)->after('email_logbook_reminders');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_logbook_reminders', 'email_task_reminders']);
        });
    }
};
