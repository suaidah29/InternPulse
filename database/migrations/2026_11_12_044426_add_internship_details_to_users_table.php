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
        Schema::table('users', function (Blueprint $table) {
            $table->string('university')->nullable();
            $table->string('course')->nullable();
            $table->date('internship_start_date')->nullable();
            $table->date('internship_end_date')->nullable();
            $table->string('internship_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['university', 'course', 'internship_start_date', 'internship_end_date', 'internship_status']);
        });
    }
};
