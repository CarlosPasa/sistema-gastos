<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importación de extractos bancarios en segundo plano.
 *
 * - statement_imports: cada archivo subido y su resultado.
 * - import_fingerprint en gasto e ingreso: huella de cada movimiento
 *   importado. El índice único (user_id, import_fingerprint) hace que
 *   subir dos veces el mismo extracto no duplique movimientos, incluso si
 *   dos importaciones corren al mismo tiempo. Los registros creados a mano
 *   la dejan en NULL (PostgreSQL permite varios NULL en un índice único).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categoria')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('status', 20)->default('pending');
            // Contenido del CSV mientras espera en la cola. Se guarda en la base
            // (no en disco) porque el disco de Render se borra en cada deploy.
            // Se vacía al terminar: no conservamos el extracto.
            $table->text('content')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_expenses')->default(0);
            $table->unsignedInteger('created_incomes')->default(0);
            $table->unsignedInteger('skipped_duplicates')->default(0);
            $table->json('row_errors')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        foreach (['gasto', 'ingreso'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('import_fingerprint', 64)->nullable();
                $table->unique(['user_id', 'import_fingerprint']);
            });
        }
    }

    public function down(): void
    {
        foreach (['gasto', 'ingreso'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['user_id', 'import_fingerprint']);
                $table->dropColumn('import_fingerprint');
            });
        }

        Schema::dropIfExists('statement_imports');
    }
};
