<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_item_id')->constrained()->onDelete('cascade');
            $table->integer('month');
            $table->integer('year');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('PLANNED');
            $table->decimal('planned_volume', 15, 2)->default(0);
            $table->decimal('planned_amount', 15, 2)->default(0);
            $table->integer('priority')->default(1); // 1=Low, 2=Medium, 3=High
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['budget_item_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_schedules');
    }
};
