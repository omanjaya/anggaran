<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_realization_id')->constrained()->onDelete('cascade');
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('action', 50);
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('created_at');

            $table->index(['monthly_realization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
    }
};
