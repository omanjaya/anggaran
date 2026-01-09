<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_item_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->decimal('planned_volume', 12, 2)->default(0);
            $table->decimal('planned_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['budget_item_id', 'month', 'year']);
            $table->index(['month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_plans');
    }
};
