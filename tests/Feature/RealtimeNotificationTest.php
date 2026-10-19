<?php

namespace Tests\Feature;

use App\Events\UnreadNotificationsChanged;
use App\Models\Procurement;
use App\Models\User;
use App\Notifications\ProcurementMilestone;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * Notifications are pushed over Reverb to the user's private channel the
 * moment they are stored or read, without ever getting in the way of the
 * work that caused them.
 */
class RealtimeNotificationTest extends TestCase
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

    public function test_a_stored_notification_is_pushed_to_its_users_channel(): void
    {
        Event::fake([UnreadNotificationsChanged::class]);

        $this->user->notify(new ProcurementMilestone($this->procurement, 'Perencanaan disetujui', 'Lanjut.'));
        $stored = $this->user->notifications()->value('id');

        Event::assertDispatched(UnreadNotificationsChanged::class, function (UnreadNotificationsChanged $event) use ($stored): bool {
            return $event->userId === $this->user->id
                && $event->newId === $stored
                && $event->feed['total'] === 1
                && $event->broadcastOn()[0]->name === 'private-App.Models.User.'.$this->user->id
                && $event->broadcastAs() === 'notifications.changed';
        });
    }

    public function test_nothing_is_pushed_for_a_notification_rolled_back(): void
    {
        Event::fake([UnreadNotificationsChanged::class]);

        try {
            DB::transaction(function (): void {
                $this->user->notify(new ProcurementMilestone($this->procurement, 'Batal', 'Tidak jadi.'));

                throw new \RuntimeException('Gagal.');
            });
        } catch (\RuntimeException) {
            // The work failed; its notification went with it.
        }

        Event::assertNotDispatched(UnreadNotificationsChanged::class);
    }

    public function test_reading_one_is_pushed_too_so_other_tabs_follow(): void
    {
        $this->user->notify(new ProcurementMilestone($this->procurement, 'Penugasan PIC', 'Anda ditunjuk.'));
        Event::fake([UnreadNotificationsChanged::class]);

        $this->actingAs($this->user)
            ->putJson(route('notifications.read', $this->user->notifications()->value('id')))
            ->assertOk();

        Event::assertDispatched(UnreadNotificationsChanged::class, fn (UnreadNotificationsChanged $event): bool => $event->feed['total'] === 0 && $event->newId === null);
    }

    public function test_an_unreachable_reverb_server_does_not_stop_the_work(): void
    {
        Broadcast::extend('unreachable', fn () => new class extends NullBroadcaster
        {
            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw new BroadcastException('Reverb tidak dapat dihubungi.');
            }
        });
        config([
            'broadcasting.connections.unreachable' => ['driver' => 'unreachable'],
            'broadcasting.default' => 'unreachable',
        ]);

        Exceptions::fake();

        $this->user->notify(new ProcurementMilestone($this->procurement, 'Tetap tersimpan', 'Walau Reverb mati.'));

        // Stored and kept; the failed push is only logged.
        $this->assertSame(1, $this->user->unreadNotifications()->count());
        Exceptions::assertReported(BroadcastException::class);
    }

    public function test_only_the_user_may_listen_on_their_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'kunci-uji',
            'broadcasting.connections.reverb.secret' => 'rahasia-uji',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');

        $other = User::factory()->planner()->create();

        $this->actingAs($this->user)
            ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$this->user->id])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($this->user)
            ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$other->id])
            ->assertForbidden();
    }
}
