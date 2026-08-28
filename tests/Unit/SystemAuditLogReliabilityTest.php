<?php

use App\Models\SystemAuditLog;

it('bounds legacy audit strings without losing their full context', function () {
    $longUrl = 'https://africathinktankplatform.africa/'.str_repeat('procurement-workflow/', 20);
    $longActionMessage = str_repeat('Technical proposal round created. ', 12);
    $audit = (new SystemAuditLog)->forceFill([
        'action' => 'model_created',
        'action_message' => $longActionMessage,
        'url' => $longUrl,
        'payload' => ['model' => 'App\\Models\\EoiTechnicalProposalRound'],
    ]);

    $normalize = new ReflectionMethod($audit, 'normalizeBoundedContext');
    $normalize->invoke($audit);

    expect(mb_strlen((string) $audit->url))->toBe(255)
        ->and(mb_strlen((string) $audit->action_message))->toBe(255)
        ->and(data_get($audit->payload, '_unabridged_context.url'))->toBe($longUrl)
        ->and(data_get($audit->payload, '_unabridged_context.action_message'))->toBe($longActionMessage)
        ->and(data_get($audit->payload, 'model'))->toBe('App\\Models\\EoiTechnicalProposalRound');
});

it('leaves audit values within their database limits unchanged', function () {
    $audit = (new SystemAuditLog)->forceFill([
        'action' => 'model_created',
        'action_message' => 'Technical proposal round created.',
        'url' => 'https://africathinktankplatform.africa/reports/evaluations/eoi/example',
        'payload' => ['round_id' => 'round-1'],
    ]);
    $originalAttributes = $audit->getAttributes();

    $normalize = new ReflectionMethod($audit, 'normalizeBoundedContext');
    $normalize->invoke($audit);

    expect($audit->getAttributes())->toBe($originalAttributes)
        ->and(data_get($audit->payload, '_unabridged_context'))->toBeNull();
});
