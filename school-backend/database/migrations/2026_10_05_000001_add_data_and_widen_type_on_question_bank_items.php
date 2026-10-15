<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Iterasi 1: dukung tipe soal baru (isian berkunci, menjodohkan, benar_salah).
     * - Kolom `type` dilebarkan dari ENUM ke VARCHAR agar tipe baru bisa ditambah tanpa migrasi lagi.
     * - Kolom `data` (JSON) menyimpan struktur khusus tipe: accepted_answers, pairs, statements.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE question_bank_items MODIFY type VARCHAR(30) NOT NULL DEFAULT 'pg'");
        }

        Schema::table('question_bank_items', function (Blueprint $table) {
            if (!Schema::hasColumn('question_bank_items', 'data')) {
                $table->json('data')->nullable()->after('options');
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_bank_items', function (Blueprint $table) {
            if (Schema::hasColumn('question_bank_items', 'data')) {
                $table->dropColumn('data');
            }
        });
    }
};
