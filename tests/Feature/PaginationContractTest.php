<?php

namespace Tests\Feature;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The client renders pagination from two different payload shapes, so both are
 * locked down here: a resource collection nests the numbered links under
 * `meta.links`, a plain paginator keeps them in a root level `links` array.
 */
class PaginationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_collections_expose_numbered_links_under_meta(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        Procurement::factory()->count(3)->create();

        $this->actingAs($teamLeader)
            ->get(route('procurements.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $page->has('procurements.data', 3)
                    ->has('procurements.meta.links')
                    ->has('procurements.meta.total')
                    ->has('procurements.links.first');

                $props = $page->toArray()['props'];

                $this->assertIsList($props['procurements']['meta']['links']);
                $this->assertArrayHasKey('url', $props['procurements']['meta']['links'][0]);
                $this->assertArrayHasKey('label', $props['procurements']['meta']['links'][0]);
                $this->assertArrayHasKey('active', $props['procurements']['meta']['links'][0]);
            });
    }

    public function test_plain_paginators_expose_numbered_links_at_the_root(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $page->has('notifications.links')->has('notifications.total');

                $props = $page->toArray()['props'];

                $this->assertIsList($props['notifications']['links']);
            });
    }

    public function test_numbered_lists_learn_where_their_page_starts(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        Procurement::factory()->count(17)->create();
        Procurement::factory()->planningApproved()->count(17)->create();

        // The "No" column of these lists counts on from `meta.from`.
        foreach (['planning.index', 'execution.index', 'pic-assignments.index'] as $name) {
            $this->actingAs($teamLeader)
                ->get(route($name))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->where('procurements.meta.from', 1));

            $this->actingAs($teamLeader)
                ->get(route($name, ['page' => 2]))
                ->assertInertia(fn (AssertableInertia $page) => $page->where('procurements.meta.from', 16));
        }
    }

    public function test_every_paginated_screen_renders_with_data(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        Procurement::factory()->count(3)->create();
        Procurement::factory()->planningApproved()->count(2)->create();
        Procurement::factory()->planningSubmitted()->create();

        $routes = [
            'procurements.index',
            'planning.index',
            'execution.index',
            'approvals.index',
            'monitoring.index',
            'reports.index',
            'documents.index',
            'pic-assignments.index',
            'notifications.index',
        ];

        foreach ($routes as $name) {
            $this->actingAs($teamLeader)
                ->get(route($name))
                ->assertOk();
        }
    }
}
