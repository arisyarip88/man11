<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Membuat Master Tabel Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // Contoh: admin, teacher, student
            $table->string('display_name', 100);  // Contoh: Administrator, Guru, Siswa
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 2. Membuat Tabel Users dengan FK role_id
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                  ->default(3) // Opsional: Default ID untuk 'student'
                  ->constrained('roles')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};