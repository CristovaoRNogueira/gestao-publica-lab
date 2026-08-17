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
        // 1. Add status temporarily as nullable
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('status')->nullable()->after('is_active');
            // 7. Add organization_unit_id (we can add it here too)
            $table->foreignId('organization_unit_id')->nullable()->after('status')->constrained('organization_units')->nullOnDelete();
        });

        // 2. Data backfill
        DB::table('memberships')->where('is_active', true)->update(['status' => 'active']);
        DB::table('memberships')->where('is_active', false)->update(['status' => 'inactive']);

        // 3. Ensure no existing rows remain without a status (safety net)
        DB::table('memberships')->whereNull('status')->update(['status' => 'pending']);

        // 4 & 5. Make status NOT NULL and set a default for new records, and add index
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('status')->default('pending')->nullable(false)->change();
            $table->index('status');
        });

        // 6. Remove is_active
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restore is_active as boolean
        Schema::table('memberships', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('status');
        });

        // 2. Data rollback
        DB::table('memberships')->where('status', 'active')->update(['is_active' => true]);
        DB::table('memberships')->whereIn('status', ['inactive', 'pending', 'rejected'])->update(['is_active' => false]);

        // 3 & 4. Remove organization_unit_id and status
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['organization_unit_id']);
            $table->dropColumn('organization_unit_id');
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
