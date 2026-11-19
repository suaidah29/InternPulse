<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderEmailTest extends TestCase
{
    use RefreshDatabase;

    private function intern(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'intern', 'email' => 'intern@example.test'], $overrides));
    }

    private function supervisor(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'supervisor', 'email' => 'supervisor@example.test'], $overrides));
    }

    private function task(User $assignee, array $overrides = []): int
    {
        return DB::table('work_tasks')->insertGetId(array_merge([
            'title' => 'Homepage copy',
            'assigned_to' => $assignee->id,
            'created_by' => $assignee->id,
            'priority' => 'normal',
            'status' => 'in_progress',
            'due_date' => Carbon::today()->subDays(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function bell(User $user): array
    {
        return DB::table('workspace_notifications')->where('user_id', $user->id)->pluck('message')->all();
    }

    public function test_a_missing_logbook_entry_sends_the_email_once_per_day(): void
    {
        Mail::fake();
        $intern = $this->intern();

        $this->artisan('logbook:remind')->assertSuccessful();
        $this->artisan('logbook:remind')->assertSuccessful();

        Mail::assertQueued(ReminderMail::class, 1);
        Mail::assertQueued(ReminderMail::class, function (ReminderMail $mail) use ($intern) {
            return $mail->hasTo($intern->email)
                && str_contains($mail->heading, Carbon::today()->format('j M Y'))
                && str_ends_with($mail->actionUrl, '/workspace/logbook');
        });
        $this->assertCount(1, $this->bell($intern));
    }

    public function test_the_logbook_reminder_can_be_switched_off_without_losing_the_bell_notice(): void
    {
        Mail::fake();
        $intern = $this->intern(['email_logbook_reminders' => false]);

        $this->artisan('logbook:remind')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertCount(1, $this->bell($intern), 'the in-app reminder must still arrive');
    }

    public function test_the_overdue_task_email_reaches_the_assignee_and_the_supervisor_digest_separately(): void
    {
        Mail::fake();
        $supervisor = $this->supervisor();
        $intern = $this->intern(['supervisor_id' => $supervisor->id]);
        $this->task($intern);

        $this->artisan('tasks:overdue')->assertSuccessful();

        Mail::assertQueued(ReminderMail::class, 2);
        Mail::assertQueued(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo($intern->email)
            && str_contains($mail->subjectLine, 'Overdue task')
            && str_contains($mail->heading, 'Homepage copy'));
        Mail::assertQueued(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo($supervisor->email)
            && str_contains($mail->subjectLine, 'Team digest')
            && str_ends_with($mail->actionUrl, '/workspace/team'));
    }

    public function test_the_digest_lists_the_overdue_titles_and_stays_short(): void
    {
        Mail::fake();
        $supervisor = $this->supervisor();
        $intern = $this->intern(['supervisor_id' => $supervisor->id]);
        for ($i = 1; $i <= 7; $i++) {
            $this->task($intern, ['title' => 'Task '.$i]);
        }

        $this->artisan('tasks:overdue')->assertSuccessful();

        Mail::assertQueued(ReminderMail::class, function (ReminderMail $mail) use ($supervisor) {
            $body = implode(' ', $mail->lines);

            return $mail->hasTo($supervisor->email)
                && str_contains($body, 'Task 1')
                && str_contains($body, '+2 more')
                && ! str_contains($body, 'Task 7');
        });
    }

    public function test_switching_off_task_reminders_stops_the_digest_but_not_the_logbook_email(): void
    {
        Mail::fake();
        $supervisor = $this->supervisor(['email_task_reminders' => false]);
        $intern = $this->intern(['supervisor_id' => $supervisor->id]);
        $this->task($intern);

        $this->artisan('tasks:overdue')->assertSuccessful();
        $this->artisan('logbook:remind')->assertSuccessful();

        Mail::assertNotQueued(ReminderMail::class, fn (ReminderMail $mail) => str_contains($mail->subjectLine, 'Team digest'));
        Mail::assertQueued(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo($intern->email) && str_contains($mail->subjectLine, 'Logbook reminder'));
        $this->assertCount(1, $this->bell($supervisor), 'the digest still reaches the bell');
    }

    public function test_an_inactive_account_keeps_the_bell_notice_but_gets_no_email(): void
    {
        Mail::fake();
        $intern = $this->intern(['is_active' => false]);
        $this->task($intern);

        $this->artisan('tasks:overdue')->assertSuccessful();

        Mail::assertNotQueued(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo($intern->email));
        $this->assertCount(1, $this->bell($intern));
    }

    public function test_the_profile_page_offers_the_reminder_choices_in_both_languages(): void
    {
        $intern = $this->intern();
        $this->actingAs($intern)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Reminder emails')
            ->assertSee('E-mel peringatan')
            ->assertSee('Logbook reminder')
            ->assertSee('Overdue task reminder');
    }

    public function test_an_intern_can_turn_the_logbook_email_off_and_keep_the_task_one(): void
    {
        $intern = $this->intern();
        $this->actingAs($intern)->post(route('profile.reminders'), ['email_task_reminders' => '1'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $fresh = $intern->fresh();
        $this->assertFalse($fresh->email_logbook_reminders);
        $this->assertTrue($fresh->email_task_reminders);
    }

    public function test_a_supervisor_can_opt_out_of_the_digest_and_cannot_touch_the_logbook_setting(): void
    {
        $supervisor = $this->supervisor(['email_logbook_reminders' => false]);
        $this->actingAs($supervisor)->get(route('profile.edit'))->assertOk()->assertDontSee('Logbook reminder');

        $this->actingAs($supervisor)->post(route('profile.reminders'), ['email_logbook_reminders' => '1'])
            ->assertRedirect(route('profile.edit'));

        $fresh = $supervisor->fresh();
        $this->assertFalse($fresh->email_task_reminders);
        $this->assertFalse($fresh->email_logbook_reminders, 'a supervisor cannot switch on a reminder they never receive');
    }

    public function test_guests_cannot_change_reminder_settings(): void
    {
        $this->post(route('profile.reminders'), ['email_task_reminders' => '1'])->assertRedirect(route('login'));
    }
}
