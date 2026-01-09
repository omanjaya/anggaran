<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skpd', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('head_name')->nullable(); // Nama Kepala SKPD
            $table->string('head_title')->nullable(); // Jabatan
            $table->string('nip_head', 20)->nullable(); // NIP Kepala
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add skpd_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('skpd_id')->nullable()->after('role')->constrained('skpd')->onDelete('set null');
        });

        // Add skpd_id to programs table
        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('skpd_id')->nullable()->after('id')->constrained('skpd')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['skpd_id']);
            $table->dropColumn('skpd_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['skpd_id']);
            $table->dropColumn('skpd_id');
        });

        Schema::dropIfExists('skpd');
    }
};
