<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 50);
            $table->integer('fiscal_year');
            $table->decimal('total_budget', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'fiscal_year']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
