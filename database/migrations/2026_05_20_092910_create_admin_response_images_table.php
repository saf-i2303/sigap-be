<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_response_images', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('comment_id')->nullable()->constrained('comments')->onDelete('set null'); 
    $table->text('image_url');
    $table->decimal('latitude', 10, 6)->nullable();
    $table->decimal('longitude', 10, 6)->nullable();
    $table->boolean('is_validated')->default(false);
    $table->timestamp('taken_at')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_response_images');
    }
};