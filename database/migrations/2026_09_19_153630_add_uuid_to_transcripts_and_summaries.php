<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public, unguessable ids for URLs. The numeric id stays as the primary key.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['transcripts', 'summaries'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable()->after('id');
            });

            DB::table($table)->whereNull('uuid')->orderBy('id')->each(
                fn ($row) => DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]),
            );

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable(false)->unique()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['transcripts', 'summaries'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['uuid']);
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
