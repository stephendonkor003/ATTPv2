<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class UserEmailMutationLock
{
    public function run(string $email, callable $callback): mixed
    {
        $normalized = mb_strtolower(trim($email));
        $store = (string) config('think_tank_portal.email_lock_store', config('cache.default'));

        if (app()->environment('production') && in_array($store, ['array', 'file', 'null'], true)) {
            throw new ServiceUnavailableHttpException(
                null,
                'Account creation requires a shared production lock store.',
            );
        }

        $lock = Cache::store($store)->lock(
            'think-tank-user-email:'.hash('sha256', $normalized),
            (int) config('think_tank_portal.email_lock_seconds', 30),
        );

        try {
            return $lock->block(
                (int) config('think_tank_portal.email_lock_wait_seconds', 5),
                $callback,
            );
        } catch (LockTimeoutException) {
            throw new ConflictHttpException(
                'This email identity is being changed by another request. Please retry.',
            );
        }
    }
}
