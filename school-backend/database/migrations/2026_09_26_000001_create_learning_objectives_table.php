<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('phase', 10)->default('F'); // Fase E (Kelas 10), Fase F (Kelas 11-12)
            $table->string('grade_level', 10); // '10', '11', '12'
            $table->string('code', 50); // Contoh: 'MAT-F.01'
            $table->string('title');
            $table->text('description');
            $table->integer('target_hours')->default(0); // Alokasi JP (Jam Pelajaran)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_objectives');
    }
};
