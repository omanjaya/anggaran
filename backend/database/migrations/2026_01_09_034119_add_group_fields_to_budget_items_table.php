<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('name');
            $table->string('sumber_dana')->nullable()->after('group_name');
            $table->integer('level')->default(1)->after('sumber_dana');
            $table->boolean('is_detail_code')->default(false)->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'sumber_dana', 'level', 'is_detail_code']);
        });
    }
};
