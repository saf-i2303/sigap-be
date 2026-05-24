<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('comments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('complaint_id')->constrained('complaints')->onDelete('cascade');
    $table->foreignUuid('admin_id')->constrained('users')->onDelete('cascade');
    $table->enum('status_after_response', ['diverifikasi', 'diproses', 'selesai', 'ditolak']);
    $table->text('message');
    $table->timestamps();
});
    }   

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};