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
        // 1. Bank Materi Pembelajaran Reusable
        Schema::create('material_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('grade_level', 10); // '10', '11', '12'
            $table->string('title');
            $table->enum('type', ['pdf', 'video', 'document', 'link', 'text'])->default('pdf');
            $table->string('file_path')->nullable();
            $table->text('external_url')->nullable();
            $table->longText('content_text')->nullable();
            $table->boolean('is_public')->default(false); // Bisa diakses guru lain jika true
            $table->timestamps();
        });

        // 2. Paket Bank Soal / Asesmen
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('grade_level', 10);
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        // 3. Butir-butir Soal dalam Bank Soal
        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->enum('type', ['pg', 'pg_kompleks', 'esai', 'isian'])->default('pg');
            $table->longText('question');
            $table->json('options')->nullable(); // [{ "key": "A", "text": "...", "is_correct": true }, ...]
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_bank_items');
        Schema::dropIfExists('question_banks');
        Schema::dropIfExists('material_banks');
    }
};
