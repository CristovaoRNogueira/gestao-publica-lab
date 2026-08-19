<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('permissions')->where('slug', 'secretarias.view')->update(['slug' => 'organization_units.view']);
        DB::table('permissions')->where('slug', 'secretarias.create')->update(['slug' => 'organization_units.create']);
        DB::table('permissions')->where('slug', 'secretarias.update')->update(['slug' => 'organization_units.update']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('slug', 'organization_units.view')->update(['slug' => 'secretarias.view']);
        DB::table('permissions')->where('slug', 'organization_units.create')->update(['slug' => 'secretarias.create']);
        DB::table('permissions')->where('slug', 'organization_units.update')->update(['slug' => 'secretarias.update']);
    }
};
