<?php

namespace App\Mail;

use App\Models\BiAnnualSiteVisitProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BiAnnualSiteVisitCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public BiAnnualSiteVisitProfile $visit,
        public User $recipient,
        public bool $isLeader,
        public string $portfolioName
    ) {
        $this->afterCommit();
    }

    public function build(): self
    {
        $this->visit->loadMissing([
            'siteVisit.group.leader',
            'siteVisit.group.members.user',
            'thinkTank',
            'template',
        ]);

        $group = $this->visit->siteVisit?->group;
        $teamMembers = $group?->members
            ?->sortBy(fn ($member): string => sprintf(
                '%d-%s',
                (string) $member->user_id === (string) $group->leader_id ? 0 : 1,
                mb_strtolower((string) $member->user?->name)
            ))
            ->values() ?? collect();
        $specialisms = (array) data_get($this->visit->settings, 'team_specialisms', []);
        $portfolioName = trim($this->portfolioName) !== ''
            ? trim($this->portfolioName)
            : 'ATTP Portfolio';
        $openUrl = route('biannual-site-visits.show', $this->visit);

        return $this
            ->subject('Bi-Annual Site Visit Assignment: '.$this->visit->reference_number)
            ->view('emails.biannual-site-visits.created')
            ->with([
                'visit' => $this->visit,
                'recipient' => $this->recipient,
                'isLeader' => $this->isLeader,
                'portfolioName' => $portfolioName,
                'openUrl' => $openUrl,
                'group' => $group,
                'teamMembers' => $teamMembers,
                'specialisms' => $specialisms,
            ]);
    }
}
