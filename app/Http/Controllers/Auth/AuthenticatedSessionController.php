<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\PendingLoginService;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankProductionSecurityService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Honeypot: legitimate users never fill this hidden field; bots do.
        if ($request->filled('website')) {
            return redirect()->route('login')
                ->withErrors(['email' => trans('auth.failed')]);
        }

        $user = $request->credentialUser();
        $request->session()->regenerate();
        $request->session()->forget([
            'otp_verified',
            'otp_verified_at',
            'otp_verified_user_id',
        ]);

        if ($user->user_type === 'vendor') {
            if ($user->is_blacklisted) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your vendor account has been blacklisted. Please contact the administrator.']);
            }
        }

        if ($user->is_disabled) {
            // Auto-release temporary blocks that already expired.
            if ($user->disabled_until && $user->disabled_until->isPast()) {
                $user->update([
                    'is_disabled' => false,
                    'disabled_at' => null,
                    'disabled_until' => null,
                    'disabled_reason' => null,
                ]);
                $user->refresh();
            } else {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $until = optional($user->disabled_until)->format('d M Y H:i');
                $message = $until
                    ? 'Your account is temporarily blocked until '.$until.'. Please contact the administrator.'
                    : 'Your account has been blocked. Please contact the administrator.';

                return redirect()->route('login')
                    ->withErrors(['email' => $message]);
            }
        }

        if ($user->user_type === 'think_tank') {
            try {
                $sessions = app(ThinkTankSessionService::class);
                $sessions->assertProductionSecurityStores();
                app(ThinkTankProductionSecurityService::class)->assertRuntimeConfiguration();
                app(ThinkTankAccountAccessService::class)->membership($user);
            } catch (ThinkTankApiException) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'This Think Tank portal account is not currently available.']);
            }
        }

        // Every production account, including administrators and funding
        // partners, must complete the session-bound email OTP challenge before
        // Laravel creates an authenticated session or fires a login event.
        if ($user->isThinkTankUser() || $user->requiresOtpVerification()) {
            $challenge = $this->sendLoginOtp($request, $user);
            if ($challenge === null) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'The verification code could not be delivered. No session was created; please try again.']);
            }

            app(PendingLoginService::class)->begin(
                $request,
                $user,
                $request->boolean('remember'),
                $challenge['expires_at'],
                PendingLoginService::PURPOSE_WEB,
            );

            return redirect()->route('security.otp.show')
                ->with('otpSent', true);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        if ($user->isThinkTankUser()) {
            app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
        }

        // Local development can explicitly disable OTP. Password changes still
        // happen only after the local authenticated session is established.
        if ($user->mustChangePassword() || $user->isPasswordExpired()) {
            return redirect()->route('security.password.change');
        }

        // Redirect funding partners to their portal
        if ($user->user_type === 'funding_partner' || $user->isFundingPartner()) {
            return redirect()->intended(route('partner.dashboard', absolute: false));
        }

        // Redirect vendors to their portal
        if ($user->user_type === 'vendor') {
            return redirect()->intended(route('vendor.dashboard', absolute: false));
        }

        // Redirect member states to their dedicated portal dashboard
        if ($user->user_type === 'member_state') {
            return redirect()->intended(route('member-state.dashboard', absolute: false));
        }

        if ($user->user_type === 'think_tank') {
            return redirect()->intended(route('think-tank.dashboard', absolute: false));
        }

        if ($user->user_type === 'ttl') {
            return redirect()->intended(route('ttl.dashboard', absolute: false));
        }

        // Default redirect to admin dashboard for all other users
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Generate and send OTP to the user's email.
     */
    protected function sendLoginOtp(Request $request, $user): ?array
    {
        try {
            return app(ThinkTankMfaService::class)->send($request, $user, true);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Clear OTP verification status from session
        $request->session()->forget('otp_verified');
        $request->session()->forget('otp_verified_at');
        $request->session()->forget('otp_verified_user_id');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
