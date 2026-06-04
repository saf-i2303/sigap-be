<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // 1. Tambahkan self-referencing foreign key untuk Parent ID
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('categories')
                  ->onDelete('cascade'); 
            
            $table->string('name');
            $table->string('slug'); // Tambahan untuk standardisasi URL/FE
            $table->text('description')->nullable(); // Penjelas kategori
            $table->boolean('is_active')->default(true); // Untuk menonaktifkan jika perlu
            $table->timestamps();

            // 2. Cegah adanya slug ganda di dalam tingkat parent yang sama
            $table->unique(['slug', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};