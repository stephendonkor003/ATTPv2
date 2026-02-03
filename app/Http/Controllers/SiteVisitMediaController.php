<?php

namespace App\Http\Controllers;

use App\Models\{SiteVisit, SiteVisitMedia};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteVisitMediaController extends Controller
{
    public function store(Request $request, SiteVisit $siteVisit)
    {
        $user = auth()->user();

        abort_unless($user && ($user->can('site_visits.observe') || $user->can('site_visits.approve')), 403);

        // Only allow uploads while the visit is being worked on.
        abort_if($siteVisit->status !== 'draft', 400, 'Media can only be uploaded while the site visit is in draft status.');

        // Admin / approver allowed; otherwise only the assigned user or group leader.
        if (! $user->can('site_visits.approve')) {
            if (
                $siteVisit->assignment_type === 'individual' &&
                $siteVisit->assignment?->user_id !== $user->id
            ) {
                abort(403, 'You are not assigned to this site visit.');
            }

            if ($siteVisit->assignment_type === 'group') {
                $isLeader = $siteVisit->group
                    ->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'leader')
                    ->exists();

                abort_unless($isLeader, 403, 'Only the group leader can upload media.');
            }
        }

        $request->validate([
            'file'           => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            'observation_id' => 'nullable|exists:site_visit_observations,id',
        ]);

        $path = $request->file('file')->store('site-visits');

        $media = SiteVisitMedia::create([
            'site_visit_id'  => $siteVisit->id,
            'observation_id' => $request->observation_id,
            'file_path'      => $path,
            'file_type'      => $request->file('file')->getClientMimeType(),
            'uploaded_by'    => auth()->id(),
        ]);

        return response()->json($media, 201);
    }

    public function download(Request $request, SiteVisit $siteVisit, SiteVisitMedia $media)
    {
        $user = auth()->user();
        abort_unless($user, 403, 'Unauthenticated.');

        abort_unless($media->site_visit_id === $siteVisit->id, 404);

        // Same access rule as the SiteVisit "show" page.
        $isAssigned =
            $siteVisit->assignment?->user_id === $user->id ||
            $siteVisit->group?->members()->where('user_id', $user->id)->exists();

        abort_unless($user->can('site_visits.approve') || $isAssigned, 403);

        $path = (string) ($media->file_path ?? '');
        abort_if($path === '', 404, 'File not found.');

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            // Best-effort migration from public -> private.
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'File missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }
}
