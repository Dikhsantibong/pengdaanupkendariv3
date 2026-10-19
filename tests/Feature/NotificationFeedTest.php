<?php

namespace Tests\Feature;

use App\Models\Procurement;
use App\Models\User;
use App\Notifications\ProcurementMilestone;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Notifications reach the user while the app is open (the feed it polls)
 * and wait on the Dashboard until each is opened or marked read.
 */
class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);

        $this->user = User::factory()->planner()->create();
        $this->procurement = Procurement::factory()->plannedBy($this->user)->create();
    }

    public function test_the_feed_lists_the_unread_ones_with_where_they_lead(): void
    {
        $this->user->notify(new ProcurementMilestone($this->procurement, 'Perencanaan disetujui', 'Lanjut ke pelaksanaan.'));
        $this->user->notify(new ProcurementMilestone($this->procurement, 'Amandemen menunggu persetujuan', 'Amandemen I.', 'amandemen'));
        $this->user->notifications()->first()?->markAsRead();

        $this->actingAs($this->user)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'Amandemen menunggu persetujuan')
            // An amendment's notice opens the job on its Amandemen tab.
            ->assertJsonPath('items.0.url', route('procurements.show', [$this->procurement, 'tab' => 'amandemen']))
            ->assertJsonPath('items.0.procurement_number', $this->procurement->number);
    }

    public function test_the_dashboard_keeps_showing_them_until_read(): void
    {
        $this->user->notify(new ProcurementMilestone($this->procurement, 'Penugasan PIC', 'Anda ditunjuk.'));
        $id = $this->user->unreadNotifications()->value('id');

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.total', 1)
                ->where('notifications.items.0.id', $id)
                ->where('notifications.items.0.url', route('procurements.show', $this->procurement)));

        // Still there on the next visit: opening the app does not read it.
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.total', 1));

        $this->actingAs($this->user)
            ->putJson(route('notifications.read', $id))
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.total', 0)->where('notifications.items', []));
    }

    public function test_only_ones_own_notification_can_be_marked_read(): void
    {
        $other = User::factory()->planner()->create();
        $other->notify(new ProcurementMilestone($this->procurement, 'Lain', 'Milik orang lain.'));

        $this->actingAs($this->user)
            ->putJson(route('notifications.read', $other->notifications()->value('id')))
            ->assertNotFound();

        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_the_inbox_rows_lead_to_the_right_tab(): void
    {
        $this->user->notify(new ProcurementMilestone($this->procurement, 'Delivery Order terbit', 'DO ke-1.', 'do'));

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.url', route('procurements.show', [$this->procurement, 'tab' => 'do']))
                ->where('notifications.data.0.read_at', null));
    }

    public function test_a_visitor_has_no_feed(): void
    {
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }
}
