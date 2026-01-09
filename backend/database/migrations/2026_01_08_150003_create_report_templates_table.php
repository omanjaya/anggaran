<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config'); // Stores columns, filters, grouping, etc.
            $table->boolean('is_public')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('frequency', ['DAILY', 'WEEKLY', 'MONTHLY'])->default('MONTHLY');
            $table->integer('day_of_week')->nullable(); // 1-7 for weekly
            $table->integer('day_of_month')->nullable(); // 1-31 for monthly
            $table->time('time_of_day')->default('08:00:00');
            $table->string('format', 10)->default('pdf'); // pdf, excel, csv
            $table->json('recipients'); // email addresses
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('report_templates');
    }
};
