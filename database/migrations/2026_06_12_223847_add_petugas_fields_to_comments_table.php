<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignUuid('petugas_id')->nullable()->after('admin_id')->constrained('users')->nullOnDelete();
            $table->string('type')->default('response')->after('petugas_id');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['petugas_id']);
            $table->dropColumn(['petugas_id', 'type']);
        });
    }
};