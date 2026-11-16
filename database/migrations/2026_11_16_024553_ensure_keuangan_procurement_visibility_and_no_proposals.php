<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Services\AccessRights;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        RolePermission::query()
            ->where('role', UserRole::Keuangan->value)
            ->whereIn('permission', [
                Permission::MenuProposals->value,
                Permission::ProposeWork->value,
                Permission::ApproveProposal->value,
            ])
            ->delete();

        RolePermission::query()->firstOrCreate([
            'role' => UserRole::Keuangan->value,
            'permission' => Permission::ViewAllProcurements->value,
        ]);

        AccessRights::forget();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
