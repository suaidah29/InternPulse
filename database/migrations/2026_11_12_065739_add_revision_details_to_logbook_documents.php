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
        Schema::table('logbook_documents', function (Blueprint $table) {
            $table->foreignId('previous_document_id')->nullable()->unique()->constrained('logbook_documents');
            $table->text('review_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logbook_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_document_id');
            $table->dropColumn('review_note');
        });
    }
};
