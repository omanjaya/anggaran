<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_realizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_plan_id')->constrained()->onDelete('cascade');
            $table->decimal('realized_volume', 12, 2)->default(0);
            $table->decimal('realized_amount', 18, 2)->default(0);
            $table->decimal('deviation_volume', 12, 2)->default(0);
            $table->decimal('deviation_amount', 18, 2)->default(0);
            $table->decimal('deviation_percentage', 8, 2)->default(0);
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['submitted_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_realizations');
    }
};
