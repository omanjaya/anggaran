<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_item_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_item_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->decimal('volume', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 20, 2)->default(0);
            $table->decimal('amount', 20, 2)->default(0);
            $table->timestamps();

            $table->index('budget_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_item_details');
    }
};
