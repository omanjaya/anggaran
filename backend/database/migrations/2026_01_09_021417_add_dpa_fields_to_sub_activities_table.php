<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_activities', function (Blueprint $table) {
            $table->string('nomor_dpa')->nullable()->after('code');
            $table->string('sumber_pendanaan')->nullable()->after('total_budget');
            $table->text('lokasi')->nullable()->after('sumber_pendanaan');
            $table->text('keluaran')->nullable()->after('lokasi');
            $table->string('waktu_pelaksanaan')->nullable()->after('keluaran');
            $table->decimal('alokasi_tahun_minus_1', 20, 2)->default(0)->after('waktu_pelaksanaan');
            $table->decimal('alokasi_tahun_plus_1', 20, 2)->default(0)->after('alokasi_tahun_minus_1');
        });
    }

    public function down(): void
    {
        Schema::table('sub_activities', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_dpa',
                'sumber_pendanaan',
                'lokasi',
                'keluaran',
                'waktu_pelaksanaan',
                'alokasi_tahun_minus_1',
                'alokasi_tahun_plus_1',
            ]);
        });
    }
};
