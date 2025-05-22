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
        Schema::table('reports', function (Blueprint $table) {
            $table->year('year')->nullable()->comment('4-digit year of the vehicle');
            $table->string('make')->nullable()->comment('Make/manufacturer of the vehicle');
            $table->string('model')->nullable()->comment('Model of the vehicle');
            $table->unsignedInteger('mileage')->nullable()->comment('Vehicle mileage, max 1,000,000');
            $table->uuid('session_uuid')->nullable()->comment('UUID of the anonymous session');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'year',
                'make',
                'model',
                'mileage',
                'session_uuid',
                'user_id'
            ]);
        });
    }
};