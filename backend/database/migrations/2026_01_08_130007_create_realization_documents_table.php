<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_realization_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->integer('file_size');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index('monthly_realization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realization_documents');
    }
};
