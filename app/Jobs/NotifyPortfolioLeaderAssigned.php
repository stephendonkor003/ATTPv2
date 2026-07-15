<?php

namespace App\Jobs;

use App\Mail\PortfolioLeaderAssignedMail;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyPortfolioLeaderAssigned implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $portfolioId,
        public string $userId,
        public string $roleName,
        public ?string $plainPassword = null
    ) {
    }

    public function handle(): void
    {
        $portfolio = Sector::with('governanceNode.level')->find($this->portfolioId);
        $user = User::with('role')->find($this->userId);

        if (! $portfolio || ! $user) {
            Log::warning('Portfolio leader assignment email skipped; portfolio or user not found.', [
                'portfolio_id' => $this->portfolioId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        if (! $this->canReceiveEmail($user)) {
            Log::warning('Portfolio leader assignment email skipped; user cannot receive email.', [
                'portfolio_id' => $portfolio->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return;
        }

        try {
            Mail::to($user->email, $user->name)
                ->send(new PortfolioLeaderAssignedMail(
                    user: $user,
                    portfolio: $portfolio,
                    roleName: $this->roleName,
                    plainPassword: $this->plainPassword,
                    loginUrl: route('login'),
                    portfolioUrl: route('budget.portfolios.show', $portfolio)
                ));

            Log::info('Portfolio leader assignment email sent.', [
                'portfolio_id' => $portfolio->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Portfolio leader assignment email failed.', [
                'portfolio_id' => $portfolio->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function canReceiveEmail(User $user): bool
    {
        if (! filled($user->email)) {
            return false;
        }

        if ((bool) $user->is_disabled || (bool) $user->is_blacklisted) {
            return false;
        }

        return true;
    }
}
