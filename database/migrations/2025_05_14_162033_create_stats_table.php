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
        Schema::create('stats', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index()->comment('Unique identifier for the statistic (e.g., average_complaints_per_vehicle)');
            $table->integer('value')->comment('The integer value of the statistic');
            $table->string('category')->nullable()->index()->comment('Optional category for grouping related statistics');
            $table->string('description')->nullable()->comment('Human-readable description of what this statistic represents');
            $table->timestamp('last_updated')->useCurrent()->comment('When this statistic was last calculated');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats');
    }
};
