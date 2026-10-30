<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Membuat Master Tabel Roles
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique(); // Contoh: admin, teacher, student
                $table->string('display_name', 100);  // Contoh: Administrator, Guru, Siswa
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // 2. Memastikan Tabel Users memiliki FK role_id dan username
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'role_id')) {
                    $table->unsignedBigInteger('role_id')->nullable()->default(3);
                }
                if (!Schema::hasColumn('users', 'username')) {
                    $table->string('username')->nullable()->unique();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};