<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
        });

        DB::table('users')
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->whereRaw("BINARY name = BINARY CONCAT(first_name, ' ', last_name)")
            ->update(['name' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->whereNull('name')
            ->update(['name' => DB::raw("CONCAT(first_name, ' ', last_name)")]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });
    }
};
