<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The work proposal step in front of planning.
 *
 * A staff member or TL of a division proposes a job; the Direksi Pekerjaan
 * (Asman/TL) of that division reviews and approves it; only then does it reach
 * the planners. Users now belong to a division through the Direksi Pekerjaan
 * they report to, and a proposal can carry supporting files.
 *
 * Everything already in the system counts as approved, so no running
 * procurement changes course.
 */
return new class extends Migration
{
    /**
     * The rights this step introduces.
     *
     * @var array<int, Permission>
     */
    private const NEW_RIGHTS = [
        Permission::ProposeWork,
        Permission::ApproveProposal,
        Permission::MenuProposals,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // The division a user works in, named by its Direksi Pekerjaan.
            $table->foreignId('work_director_id')->nullable()->after('position')->constrained()->nullOnDelete();
        });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->string('proposal_state', 30)->default('disetujui')->after('work_director_id')->index();
            $table->timestamp('proposal_submitted_at')->nullable()->after('proposal_state');
            $table->timestamp('proposal_reviewed_at')->nullable()->after('proposal_submitted_at');
            $table->foreignId('proposal_reviewed_by')->nullable()->after('proposal_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('proposal_review_note')->nullable()->after('proposal_reviewed_by');
        });

        Schema::create('procurement_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('file_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (self::NEW_RIGHTS as $permission) {
            foreach ($permission->defaultRoles() as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $role->value,
                    'permission' => $permission->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Cache::forget('access-rights.grants');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission', array_map(fn (Permission $permission): string => $permission->value, self::NEW_RIGHTS))
            ->delete();

        Schema::dropIfExists('procurement_attachments');

        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proposal_reviewed_by');
            $table->dropIndex(['proposal_state']);
            $table->dropColumn(['proposal_state', 'proposal_submitted_at', 'proposal_reviewed_at', 'proposal_review_note']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_director_id');
        });

        Cache::forget('access-rights.grants');
    }
};
