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
        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('goals')->nullable();
            $table->string('target_audience', 500)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_until')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('season_name', 100)->nullable();
            $table->string('sequencing_mode', 40)->default('open');
            $table->string('outline_style', 20)->default('subjects');
            $table->string('pricing', 10)->default('free');
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->unsignedBigInteger('discounted_price_amount')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'kind', 'id']);
            $table->index(['is_featured', 'status']);
        });

        Schema::create('offering_instructors', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->primary(['offering_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offering_instructors');
        Schema::dropIfExists('offerings');
    }
};
