<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('categoria', function (Blueprint $t) {
            $t->id(); $t->string('description',100); $t->boolean('estado')->default(true);
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete(); $t->timestamps();
        });
        Schema::create('gasto', function (Blueprint $t) {
            $t->id(); $t->string('description',100); $t->decimal('amount',14,2); $t->date('date');
            $t->boolean('save')->default(false); $t->foreignId('id_category')->constrained('categoria')->restrictOnDelete();
            $t->boolean('estado')->default(true); $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps(); $t->index(['user_id','date','estado']);
        });
        Schema::create('ingreso', function (Blueprint $t) {
            $t->id(); $t->string('description',100); $t->decimal('amount',14,2);
            $t->boolean('estado')->default(true); $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps(); $t->index(['user_id','created_at','estado']);
        });
        Schema::create('presupuesto', function (Blueprint $t) {
            $t->id(); $t->string('description',100); $t->decimal('amount',14,2);
            $t->foreignId('id_category')->constrained('categoria')->restrictOnDelete();
            $t->boolean('estado')->default(true); $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('presupuesto'); Schema::dropIfExists('ingreso');
        Schema::dropIfExists('gasto'); Schema::dropIfExists('categoria');
    }
};
