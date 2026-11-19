<?php

namespace App\Jobs;

use App\Mail\SupervisorInvitationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers one supervisor/intern invitation on the queue so a slow or failing
 * mail server cannot hold up (or half-break) the request that created it.
 */
class SendSupervisorInvitation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds to wait between attempts. */
    public array $backoff = [10, 60];

    public function __construct(
        public int $invitationId,
        public string $email,
        public string $name,
        public string $department,
        public string $url,
        public string $role = 'supervisor',
    ) {}

    public function handle(): void
    {
        $invitation = DB::table('supervisor_invitations')->where('id', $this->invitationId)->first();

        // Cancelled or already used while this job sat in the queue: send nothing.
        if (! $invitation || $invitation->revoked_at || $invitation->accepted_at) {
            return;
        }

        Mail::to($this->email)->send(new SupervisorInvitationMail($this->name, $this->department, $this->url, $this->role));

        DB::table('supervisor_invitations')->where('id', $this->invitationId)->update([
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function failed(?\Throwable $error): void
    {
        DB::table('supervisor_invitations')->where('id', $this->invitationId)->update(['delivery_status' => 'failed']);

        logger()->warning('Supervisor invitation mail failed', [
            'invitation_id' => $this->invitationId,
            'exception' => $error ? get_class($error) : null,
        ]);
    }
}
