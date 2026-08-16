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
        Schema::create('platform_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_role_permission', function (Blueprint $table) {
            $table->foreignId('platform_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['platform_role_id', 'platform_permission_id']);
        });

        Schema::create('platform_role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'platform_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_role_user');
        Schema::dropIfExists('platform_role_permission');
        Schema::dropIfExists('platform_roles');
        Schema::dropIfExists('platform_permissions');
    }
};
