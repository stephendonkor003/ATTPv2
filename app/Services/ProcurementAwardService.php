<?php

namespace App\Services;

use App\Mail\VendorProcurementAwarded;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementContractNegotiation;
use App\Notifications\VendorProcurementAwardedNotification;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Mail;

class ProcurementAwardService
{
    public function award(Procurement $procurement): ProcurementContractNegotiation
    {
        if ($procurement->status !== 'closed') {
            throw new Exception('Only closed procurements can be awarded.');
        }

        $negotiation = $procurement->contractNegotiations()
            ->where('status', 'agreed')
            ->orderByDesc('agreed_at')
            ->first();

        if (!$negotiation) {
            throw new Exception('No approved contract negotiation found for this procurement.');
        }

        $vendor = $this->resolveVendor($negotiation);
        if (!$vendor) {
            throw new Exception('Awarded vendor record is missing.');
        }
        if (empty($vendor->email)) {
            throw new Exception('Awarded vendor email is missing.');
        }

        $procurement->update([
            'status' => 'awarded',
            'awarded_submission_id' => $negotiation->submission_id,
            'awarded_vendor_id' => $vendor->id,
            'awarded_at' => now(),
        ]);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Awarded procurement',
            'procurement_id' => $procurement->id,
            'submission_id' => $negotiation->submission_id,
            'metadata' => [
                'negotiation_id' => $negotiation->id,
                'vendor_id' => $vendor->id,
            ],
            'created_at' => now(),
        ]);

        $mail = new VendorProcurementAwarded($procurement, $negotiation, $vendor);

        try {
            Mail::to($vendor->email)->send($mail);
        } catch (\Throwable $exception) {
            logger()->error('Award email failed.', [
                'procurement_id' => $procurement->id,
                'vendor_id' => $vendor->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $vendor->notify(new VendorProcurementAwardedNotification($procurement));
        } catch (\Throwable $exception) {
            logger()->error('Award notification failed.', [
                'procurement_id' => $procurement->id,
                'vendor_id' => $vendor->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $negotiation;
    }

    private function resolveVendor(ProcurementContractNegotiation $negotiation): ?User
    {
        if ($negotiation->vendor) {
            return $negotiation->vendor;
        }

        if ($negotiation->submission) {
            return $negotiation->submission->submitter;
        }

        if ($negotiation->submission_id) {
            $submission = FormSubmission::with('submitter')->find($negotiation->submission_id);
            return $submission?->submitter;
        }

        return null;
    }
}
