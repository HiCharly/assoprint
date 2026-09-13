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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->unsignedInteger('copies')->default(1);
            $table->enum('duplex', ['none', 'long-edge', 'short-edge'])->default('none');
            $table->enum('color_mode', ['color', 'bw']);
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('pages_printed')->nullable();
            $table->enum('status', ['pending', 'printing', 'printed', 'error'])->default('pending');
            $table->text('error_message')->nullable();
            $table->string('cups_job_id')->nullable();
            $table->foreignId('duplicated_from_id')->nullable()->constrained('print_jobs')->nullOnDelete();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            // Le compteur de pages d'un membre et sa liste de tâches lisent
            // toujours par utilisateur, du plus récent au plus ancien.
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
