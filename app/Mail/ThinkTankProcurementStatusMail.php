<?php

namespace App\Mail;

use App\Models\ThinkTankProcurementStatusNotification;
use App\Support\PdfBranding;
use App\Support\PdfPageNumbering;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ThinkTankProcurementStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $actionUrl;

    public string $fromStatusLabel;

    public string $toStatusLabel;

    public function __construct(public ThinkTankProcurementStatusNotification $notification)
    {
        $this->notification->loadMissing([
            'event.plan.member:id,name',
            'event.item:id,plan_id,item_code,title',
            'event.actor:id,name',
        ]);

        $event = $this->notification->event;
        if ($this->notification->recipient_scope === 'think_tank') {
            $metadata = (array) $event->metadata;
            $procurementId = (string) ($metadata['procurement_id'] ?? '');
            $executionActions = [
                'item_execution_created',
                'item_publication_recalled',
                'item_publication_republished',
                'item_publication_closed',
            ];
            $path = in_array($event->action, $executionActions, true) && Str::isUuid($procurementId)
                ? '/procurement/executions/'.$procurementId
                : '/procurement/plans/'.$event->plan_id;
            $this->actionUrl = rtrim((string) config('think_tank_portal.frontend_url'), '/').$path;
        } else {
            $this->actionUrl = $event->item_id && Route::has('think-tank-procurement.worksheet.show')
                ? route('think-tank-procurement.worksheet.show', [$event->plan_id, $event->item_id])
                : route('think-tank-procurement.show', $event->plan_id);
        }
        $this->fromStatusLabel = $this->statusLabel($event->from_status);
        $this->toStatusLabel = $this->statusLabel($event->to_status);
    }

    public function build(): self
    {
        $event = $this->notification->event;
        $metadata = (array) $event->metadata;
        $reference = (string) ($metadata['item_code'] ?? $metadata['plan_code'] ?? $event->plan?->plan_code ?? 'procurement-status');
        $safeReference = Str::slug($reference) ?: 'procurement-status';
        $pdf = Pdf::loadView('emails.think-tank.procurement-status-pdf', array_merge([
            'notification' => $this->notification,
            'event' => $event,
            'metadata' => $metadata,
            'fromStatusLabel' => $this->fromStatusLabel,
            'toStatusLabel' => $this->toStatusLabel,
        ], PdfBranding::viewData()))->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
        $pdf = PdfPageNumbering::stamp($pdf);

        return $this->subject($this->notification->heading.' - '.$reference)
            ->view('emails.think-tank.procurement-status', [
                'notification' => $this->notification,
                'event' => $event,
                'metadata' => $metadata,
                'actionUrl' => $this->actionUrl,
                'fromStatusLabel' => $this->fromStatusLabel,
                'toStatusLabel' => $this->toStatusLabel,
            ])
            ->attachData($pdf->output(), 'ATTP-procurement-'.$safeReference.'-'.$event->id.'.pdf', [
                'mime' => 'application/pdf',
            ]);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'submitted' => 'Sent to AUC-ATTP Secretariat',
            'approved' => 'Pending World Bank no-objection',
            'no_objection_obtained' => 'No-objection received — ready to execute',
            'revision_requested' => 'Revision requested',
            'rejected' => 'Rejected',
            'published' => 'In execution / published',
            'closed' => 'Application window closed',
            'draft' => 'Draft',
            default => Str::headline((string) ($status ?: 'Not recorded')),
        };
    }
}
