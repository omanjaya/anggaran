<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_activity_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['capaian_kegiatan', 'masukan', 'keluaran', 'hasil']);
            $table->text('tolak_ukur')->nullable();
            $table->string('target')->nullable();
            $table->timestamps();

            $table->unique(['sub_activity_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_indicators');
    }
};
