<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\UnitManager;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The TOR's signature page: the Manager from Data Master approves it at the
 * bottom; the user, their Asman and the Asman Engineering above it are read
 * from the data, with a line to write on while one is not set up.
 */
class TorSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tor_is_approved_by_the_active_manager(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        // The manager kept on Data Master -> Manager.
        UnitManager::query()->update(['is_active' => false]);
        UnitManager::factory()->create(['name' => 'Ir. Budi Santoso', 'is_active' => true, 'sort_order' => 0]);

        $planner = User::factory()->planner()->create(['name' => 'Perencana Uji']);
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $document = app(DocumentGenerator::class)->generate(
            $procurement,
            DocumentType::query()->where('code', 'tor')->firstOrFail(),
            $planner,
        );

        $approval = substr($document->rendered_body, (int) strpos($document->rendered_body, 'LEMBAR PENGESAHAN'));

        $this->assertStringContainsString('IR. BUDI SANTOSO', $approval);
        // The planner does not sign it; the user's Asman checks it under the
        // division's name, and a signatory not set up leaves a line.
        $this->assertStringNotContainsString('PERENCANA UJI', mb_strtoupper($approval));
        $this->assertStringContainsString(mb_strtoupper($procurement->workDirector->name), $approval);
        $this->assertStringContainsString('ASMAN ENGINEERING', $approval);
        $this->assertStringContainsString('.........................................', $approval);
    }
}
