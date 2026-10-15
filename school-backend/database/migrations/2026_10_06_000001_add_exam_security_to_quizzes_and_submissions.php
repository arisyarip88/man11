<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Iterasi 2: Token Ujian, Durasi, Jendela Waktu, dan Tampilan Fokus Ujian Aman.
     */
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('access_token', 10)->nullable()->after('questions_data');
            $table->dateTime('token_expires_at')->nullable()->after('access_token');
            $table->dateTime('available_from')->nullable()->after('token_expires_at');
            $table->dateTime('available_until')->nullable()->after('available_from');
            $table->integer('max_violations')->default(3)->nullable()->after('available_until');
        });

        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->integer('violation_count')->default(0)->after('answers');
            $table->string('finished_reason', 30)->nullable()->after('violation_count'); // 'manual', 'timeout', 'violation'
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'])) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE quiz_submissions MODIFY status VARCHAR(30) NOT NULL DEFAULT 'submitted'");
        }
    }

    public function down(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->dropColumn(['violation_count', 'finished_reason']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn([
                'access_token',
                'token_expires_at',
                'available_from',
                'available_until',
                'max_violations',
            ]);
        });
    }
};
