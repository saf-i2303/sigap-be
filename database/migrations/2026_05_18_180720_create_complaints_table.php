<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_id')->unique(); // format SGP-XXXXXX
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['pending', 'diverifikasi', 'diproses', 'selesai', 'ditolak'])->default('pending');
            $table->enum('admin_priority', ['rendah', 'sedang', 'tinggi', 'darurat'])->nullable(); // diset admin saat review
            $table->string('wilayah'); // otomatis dari reverse geocoding
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->text('address')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};