<?php

use App\Models\Procurement;
use App\Services\ProcurementOpportunityExpiryService;
use App\Services\ProcurementPublicationNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;

function bootProcurementOpportunityExpiryApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('expires a published opportunity only after its inclusive closing date', function (): void {
    [$application, $bootedHere] = bootProcurementOpportunityExpiryApplication();

    try {
        $service = $application->make(ProcurementOpportunityExpiryService::class);
        $today = CarbonImmutable::parse('2026-10-01')->startOfDay();
        $procurement = (new Procurement)->forceFill([
            'status' => 'published',
            'application_end_date' => '2026-09-30',
        ]);

        expect($service->isExpired($procurement, $today))->toBeTrue();

        $procurement->application_end_date = '2026-10-01';
        expect($service->isExpired($procurement, $today))->toBeFalse();

        $procurement->application_end_date = '2026-09-30';
        $procurement->status = 'closed';
        expect($service->isExpired($procurement, $today))->toBeFalse();

        $procurement->status = 'published';
        $procurement->deleted_at = '2026-09-29 08:00:00';
        expect($service->isExpired($procurement, $today))->toBeFalse();
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('fails closed when no reviewed applicant closure email exists', function (): void {
    $notifications = new ProcurementPublicationNotificationService;

    expect($notifications->supports('recalled'))->toBeTrue()
        ->and($notifications->supports('republished'))->toBeTrue()
        ->and($notifications->supports('closed'))->toBeFalse()
        ->and(fn () => $notifications->queue(new Procurement, 'closed'))
        ->toThrow(InvalidArgumentException::class);
});

it('uses one transactional and idempotent expiry path for scheduled and lazy closures', function (): void {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ProcurementOpportunityExpiryService.php');
    $command = file_get_contents($root.'/app/Console/Commands/CloseExpiredProcurementOpportunities.php');
    $schedule = file_get_contents($root.'/bootstrap/app.php');
    $model = file_get_contents($root.'/app/Models/Procurement.php');
    $publicController = file_get_contents($root.'/app/Http/Controllers/Procurement/PublicProcurementController.php');
    $vendorController = file_get_contents($root.'/app/Http/Controllers/Vendor/VendorProcurementController.php');
    $statusNotifications = file_get_contents($root.'/app/Services/ThinkTankProcurementStatusNotificationService.php');
    $statusMail = file_get_contents($root.'/app/Mail/ThinkTankProcurementStatusMail.php');

    expect($service)->toContain(
        'DB::transaction',
        'lockForUpdate()',
        "->where('status', 'published')",
        "'item_publication_closed'",
        "'closed_automatically' => true",
        "->supports('closed')",
    )->and($command)->toContain("procurement:close-expired")
        ->and($schedule)->toContain(
            "procurement:close-expired --limit=250",
            '->everyFiveMinutes()',
            '->withoutOverlapping()',
            '->onOneServer()',
        )
        ->and($model)->toContain(
            'ProcurementOpportunityExpiryService::class',
            '->closeOne((string) $this->getKey())',
        )
        ->and($publicController)->not->toContain("whereDate('application_end_date', '<', \$today)")
        ->and($vendorController)->not->toContain("whereDate('application_end_date', '<', \$today)")
        ->and($statusNotifications)->toContain(
            "'item_publication_closed'",
            'Procurement opportunity closed',
        )
        ->and($statusMail)->toContain(
            "'item_publication_closed'",
            "'closed' => 'Application window closed'",
        );
});
