<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProcurementDisbursement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class VendorDisbursementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $disbursements = ProcurementDisbursement::with(['purchaseOrder', 'procurement'])
            ->where('vendor_id', $user->id)
            ->orderByDesc('paid_at')
            ->get();

        return view('vendor.payments.index', [
            'disbursements' => $disbursements,
        ]);
    }

    public function show(Request $request, ProcurementDisbursement $disbursement)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($disbursement->vendor_id !== $user->id) {
            abort(403, 'You do not have access to this payment record.');
        }

        $disbursement->load(['purchaseOrder', 'procurement', 'subActivity', 'vendor']);

        return view('vendor.payments.show', [
            'disbursement' => $disbursement,
        ]);
    }

    public function pdf(Request $request, ProcurementDisbursement $disbursement)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($disbursement->vendor_id !== $user->id) {
            abort(403, 'You do not have access to this payment record.');
        }

        $disbursement->load(['purchaseOrder', 'procurement', 'subActivity', 'vendor']);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        return $pdf->stream('receipt-' . ($disbursement->reference_no ?? 'payment') . '.pdf');
    }

    public function download(Request $request, ProcurementDisbursement $disbursement)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($disbursement->vendor_id !== $user->id) {
            abort(403, 'You do not have access to this payment record.');
        }

        $disbursement->load(['purchaseOrder', 'procurement', 'subActivity', 'vendor']);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        return $pdf->download('receipt-' . ($disbursement->reference_no ?? 'payment') . '.pdf');
    }

    private function assertVendor($user): void
    {
        if (!$user || $user->user_type !== 'vendor') {
            abort(403, 'Access denied. Vendor portal only.');
        }

        if ($user->is_blacklisted) {
            abort(403, 'Your vendor account has been blacklisted. Please contact the administrator.');
        }

        if ($user->is_disabled) {
            abort(403, 'Your vendor account has been disabled. Please contact the administrator.');
        }
    }
}
