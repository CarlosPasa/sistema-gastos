<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('presupuesto_vigencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuesto')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('effective_month');
            $table->timestamps();
            $table->unique(['presupuesto_id', 'effective_month'], 'presupuesto_vigencia_unique');
            $table->index(['presupuesto_id', 'effective_month']);
        });

        // Backfill without changing existing budgets. Start each version at the
        // budget creation month. Prior historical changes cannot be reconstructed.
        DB::table('presupuesto')->orderBy('id')->chunkById(200, function ($budgets) {
            $now = now();
            $rows = [];
            foreach ($budgets as $budget) {
                $rows[] = [
                    'presupuesto_id' => $budget->id,
                    'amount' => $budget->amount,
                    'effective_month' => $budget->created_at
                        ? \Carbon\Carbon::parse($budget->created_at)->startOfMonth()->toDateString()
                        : now()->startOfMonth()->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows) DB::table('presupuesto_vigencia')->insert($rows);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_vigencia');
    }
};
