<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('satisfaction_responses', 'first_name')) {
            Schema::table('satisfaction_responses', function (Blueprint $table) {
                $table->string('first_name', 60)->nullable()->after('rating');
            });
        }

        if (! Schema::hasColumn('satisfaction_responses', 'age')) {
            Schema::table('satisfaction_responses', function (Blueprint $table) {
                $table->unsignedSmallInteger('age')->nullable()->after('first_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('satisfaction_responses', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'age']);
        });
    }
};
