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
            $table->string('first_name', 255)->nullable()->change();
        });

        DB::table('users')->whereNull('first_name')->whereNotNull('name')
            ->update(['first_name' => DB::raw('name')]);

        $nameConstraint = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'users')
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->where('CONSTRAINT_NAME', 'chk_users_name')
            ->exists();

        if ($nameConstraint) {
            DB::statement('ALTER TABLE users DROP CHECK chk_users_name');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable();
        });

        DB::table('users')->update([
            'name' => DB::raw("NULLIF(CONCAT_WS(' ', first_name, last_name), '')"),
        ]);

        DB::table('users')->whereNull('last_name')->update(['first_name' => null]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable()->change();
        });
    }
};
