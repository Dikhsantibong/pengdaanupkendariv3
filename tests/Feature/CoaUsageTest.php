<?php

namespace Tests\Feature;

use App\Enums\CoaRequestStatus;
use App\Enums\UserRole;
use App\Models\CoaRequest;
use App\Models\Procurement;
use App\Models\User;
use App\Models\WorkDirector;
use App\Support\CoaUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A COA requested by a Pengusul or a TL reaches Keuangan for activation, and
 * each code shows how often the usulan / pengadaan use it, kept in step when
 * Keuangan corrects an active code.
 */
class CoaUsageTest extends TestCase
{
    use RefreshDatabase;

    /** The segments of AKD213720002320F102/JBJSLN. */
    private const SEGMENTS = [
        'segment_1' => 'A',
        'segment_2' => 'KD',
        'segment_3' => '21',
        'segment_4' => '372',
        'segment_5' => '000',
        'segment_6' => '23',
        'segment_7' => '20',
        'expense_element' => 'F102/JBJSLN',
    ];

    public function test_a_request_activation_from_a_pengusul_or_a_tl_reaches_keuangan(): void
    {
        $division = WorkDirector::factory()->create();
        $keuangan = User::factory()->role(UserRole::Keuangan)->create();

        $requesters = [
            User::factory()->proposer($division)->create(),
            User::factory()->workDirector($division)->create(),
            User::factory()->teamLeaderPengadaan()->create(),
            User::factory()->teamLeaderIcc()->create(),
        ];

        foreach ($requesters as $index => $requester) {
            $this->actingAs($requester)
                ->post(route('coa-requests.store'), [
                    ...self::SEGMENTS,
                    'segment_5' => sprintf('%03d', $index),
                    'description' => 'Keperluan '.$index,
                    'action' => 'request_activation',
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }

        $this->assertSame(4, CoaRequest::query()->where('status', CoaRequestStatus::Pending->value)->count());

        // Keuangan finds every one waiting, on Request COA and the dashboard.
        $this->actingAs($keuangan)
            ->get(route('coa-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.pending', 4)
                ->has('coaRequests.data', 4)
                ->where('coaRequests.data.0.can_activate', true));

        $this->actingAs($keuangan)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('tasks.coaActivations.total', 4));
    }

    public function test_each_code_shows_how_often_the_usulan_use_it(): void
    {
        $division = WorkDirector::factory()->create();
        $pengusul = User::factory()->proposer($division)->create();
        $keuangan = User::factory()->role(UserRole::Keuangan)->create();

        $coa = CoaRequest::factory()->active($keuangan)->create(['requested_by' => $pengusul->id]);
        $unused = CoaRequest::factory()->active($keuangan)->create([
            'segment_5' => '001',
            'coa_code' => 'AKD213720012320F102/JBJSLN',
        ]);

        // Used on its own, in a list, and in another case; not by a lookalike.
        Procurement::factory()->create(['coa_number' => 'AKD213720002320F102/JBJSLN', 'name' => 'Pemeliharaan A']);
        Procurement::factory()->create(['coa_number' => '5110000171, akd213720002320f102/jbjsln', 'name' => 'Pemeliharaan B']);
        Procurement::factory()->create(['coa_number' => 'AKD213720002320F102/JBJSLNX']);

        $this->assertSame(2, CoaUsage::counts()['AKD213720002320F102/JBJSLN']);

        foreach ([$keuangan, $pengusul] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('coa-requests.index', ['status' => 'aktif']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('coaRequests.data', function ($rows) use ($coa, $unused): bool {
                    $rows = collect($rows)->keyBy('id');

                    return $rows[$coa->id]['usage_count'] === 2
                        && collect($rows[$coa->id]['usages'])->pluck('name')->sort()->values()->all() === ['Pemeliharaan A', 'Pemeliharaan B']
                        && $rows[$unused->id]['usage_count'] === 0;
                }));
        }

        // The usulan form offers the code with its count too.
        $this->actingAs($pengusul)
            ->get(route('procurements.create'))
            ->assertInertia(fn (Assert $page) => $page->where('options.activeCoas', fn ($options): bool => collect($options)->firstWhere('value', $coa->coa_code)['usageCount'] === 2
                && collect($options)->firstWhere('value', $unused->coa_code)['usageCount'] === 0));
    }

    public function test_a_code_keuangan_corrects_follows_into_the_usulan_using_it(): void
    {
        $keuangan = User::factory()->role(UserRole::Keuangan)->create();
        $coa = CoaRequest::factory()->active($keuangan)->create();

        $used = Procurement::factory()->create(['coa_number' => '5110000171, AKD213720002320F102/JBJSLN']);
        $other = Procurement::factory()->create(['coa_number' => 'AKD213720002320F102/JBJSLNX']);

        $this->actingAs($keuangan)
            ->put(route('coa-requests.update', $coa), [...self::SEGMENTS, 'segment_5' => '001', 'action' => 'save'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('AKD213720012320F102/JBJSLN', $coa->fresh()->coa_code);
        $this->assertSame('5110000171, AKD213720012320F102/JBJSLN', $used->fresh()->coa_number);
        $this->assertSame('AKD213720002320F102/JBJSLNX', $other->fresh()->coa_number);
    }

    public function test_a_code_another_active_request_still_carries_is_left_on_the_usulan(): void
    {
        $keuangan = User::factory()->role(UserRole::Keuangan)->create();
        $coa = CoaRequest::factory()->active($keuangan)->create();
        CoaRequest::factory()->active($keuangan)->create();

        $used = Procurement::factory()->create(['coa_number' => 'AKD213720002320F102/JBJSLN']);

        $this->actingAs($keuangan)
            ->put(route('coa-requests.update', $coa), [...self::SEGMENTS, 'segment_5' => '001', 'action' => 'save'])
            ->assertRedirect();

        $this->assertSame('AKD213720002320F102/JBJSLN', $used->fresh()->coa_number);
    }
}
