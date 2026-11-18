<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas_tramite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitante_id')->constrained('guia_visitantes')->cascadeOnDelete();
            $table->string('tramite', 40);
            $table->text('consulta');
            $table->timestamps();
            $table->index(['tramite', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas_tramite');
    }
};
