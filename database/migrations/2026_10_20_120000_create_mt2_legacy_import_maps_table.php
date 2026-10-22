<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mt2_legacy_import_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('entity', 30);
            $table->unsignedInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();
            $table->unique(['user_id', 'entity', 'legacy_id'], 'mt2_import_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mt2_legacy_import_maps');
    }
};
