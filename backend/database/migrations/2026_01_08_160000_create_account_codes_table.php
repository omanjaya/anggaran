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
        Schema::create('account_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description', 500);
            $table->integer('level')->comment('1=Kelompok, 2=Jenis, 3=Objek, 4=Rincian Objek, 5=Sub Rincian Objek');
            $table->string('parent_code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('level');
            $table->index('parent_code');
            $table->index('is_active');
        });

        // Add foreign key for hierarchical structure
        Schema::table('account_codes', function (Blueprint $table) {
            $table->foreign('parent_code')->references('code')->on('account_codes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_codes');
    }
};
