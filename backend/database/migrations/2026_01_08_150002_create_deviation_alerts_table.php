<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deviation_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_realization_id')->nullable()->constrained('monthly_realizations')->onDelete('cascade');
            $table->foreignId('budget_item_id')->constrained()->onDelete('cascade');
            $table->integer('month');
            $table->integer('year');
            $table->enum('alert_type', [
                'UNDER_REALIZATION',   // < 70% dari rencana
                'OVER_REALIZATION',    // > 110% dari rencana
                'DEADLINE_APPROACHING', // H-7 deadline
                'DEADLINE_PASSED',      // Lewat deadline
                'NOT_REALIZED',         // Bulan sudah lewat tapi belum direalisasi
            ]);
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM');
            $table->decimal('planned_amount', 15, 2)->default(0);
            $table->decimal('realized_amount', 15, 2)->default(0);
            $table->decimal('deviation_percentage', 8, 2)->default(0);
            $table->text('message');
            $table->enum('status', ['ACTIVE', 'ACKNOWLEDGED', 'RESOLVED', 'DISMISSED'])->default('ACTIVE');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['budget_item_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deviation_alerts');
    }
};
