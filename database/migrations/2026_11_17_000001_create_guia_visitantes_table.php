<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_visitantes', function (Blueprint $table) {
            $table->id();
            $table->char('session_token', 64)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->char('dni', 8)->unique();
            $table->unsignedTinyInteger('edad');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_visitantes');
    }
};
