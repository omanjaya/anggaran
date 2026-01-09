<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name');
            $table->decimal('total_budget', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['activity_id', 'code']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_activities');
    }
};
