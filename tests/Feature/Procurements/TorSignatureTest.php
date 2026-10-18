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
 * bottom; who drafted and checked it is typed in by hand.
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

        $this->assertStringContainsString('Ir. Budi Santoso', $approval);
        // The other signatories are left blank for typing.
        $this->assertStringNotContainsString('Perencana Uji', $approval);
        $this->assertStringNotContainsString($procurement->workDirector->name, $approval);
        $this->assertStringContainsString('( ......................................... )', $approval);
    }
}
