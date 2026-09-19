<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('s3_key');
            $table->string('mime_type');
            $table->string('extension', 8);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('duration_seconds');
            $table->string('state')->index();
            $table->string('provider_job_id')->nullable();
            $table->timestamp('consent_confirmed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
