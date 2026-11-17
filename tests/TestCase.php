<?php

namespace Tests;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The archive download caps its own run at 300 seconds. Within the test
     * process that cap would carry on to every later test (on Windows it
     * counts wall-clock time), so it is lifted after each test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        set_time_limit(0);
    }

    /**
     * Give a procurement its Penyerahan and Pembayaran steps, as the PIC
     * would by typing them: the steps neither the contract nor the Data
     * Master adds by itself any more. The template's steps for its contract
     * are used, so the letters and uploads tied to them can be tested.
     */
    protected function addHandoverAndPaymentSteps(Procurement $procurement): void
    {
        $present = $procurement->checklists()->pluck('checklist_item_id');

        ChecklistItem::query()
            ->shared()
            ->active()
            ->whereNotNull('section')
            ->forProcurementMethod($procurement->procurement_method_id)
            ->forContractNumberFormat($procurement->contract_number_format_id)
            ->forWorkCategory($procurement->work_category)
            ->ordered()
            ->get()
            ->reject(fn (ChecklistItem $item): bool => $present->contains($item->id))
            ->each(fn (ChecklistItem $item) => $procurement->checklists()->create([
                'checklist_item_id' => $item->id,
                'stage' => ProcurementStage::Keuangan,
                'is_completed' => false,
            ]));

        $procurement->unsetRelation('checklists');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
