<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('urusan_pemerintahan_code')->nullable()->after('code');
            $table->string('urusan_pemerintahan_name')->nullable()->after('urusan_pemerintahan_code');
            $table->string('bidang_urusan_code')->nullable()->after('urusan_pemerintahan_name');
            $table->string('bidang_urusan_name')->nullable()->after('bidang_urusan_code');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'urusan_pemerintahan_code',
                'urusan_pemerintahan_name',
                'bidang_urusan_code',
                'bidang_urusan_name',
            ]);
        });
    }
};
