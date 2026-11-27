<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaction_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prototype_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamp('responded_at')->index();
            $table->timestamps();
            $table->unique(['visitor_session_id', 'prototype_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_responses');
    }
};
