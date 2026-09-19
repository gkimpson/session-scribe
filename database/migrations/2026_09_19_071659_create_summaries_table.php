<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->string('level');
            $table->string('model_id');
            $table->unsignedSmallInteger('prompt_version');
            $table->string('state')->index();
            $table->json('sections')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['transcript_id', 'level', 'prompt_version', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
