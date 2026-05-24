<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_status_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('complaint_id')->constrained('complaints')->onDelete('cascade');
            $table->enum('status', ['pending', 'diverifikasi', 'diproses', 'selesai', 'ditolak']);
            $table->foreignUuid('changed_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_status_logs');
    }
};