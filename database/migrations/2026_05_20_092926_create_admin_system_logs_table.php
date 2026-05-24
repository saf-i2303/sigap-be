<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_system_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', ['LOGIN', 'UPDATE_COMPLAINT', 'DELETE_USER']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_system_logs');
    }
};