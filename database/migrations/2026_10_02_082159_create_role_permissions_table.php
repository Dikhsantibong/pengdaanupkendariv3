<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which role holds which right, as set on the "Hak Akses" screen.
 *
 * A row means the role holds the right. The table is filled with the defaults
 * straight away, so every account keeps exactly the access it had before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->timestamps();

            $table->unique(['role', 'permission']);
        });

        foreach (Permission::cases() as $permission) {
            foreach ($permission->defaultRoles() as $role) {
                DB::table('role_permissions')->insert([
                    'role' => $role->value,
                    'permission' => $permission->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
