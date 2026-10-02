<?php

namespace App\Services;

use App\Models\DynamicForm;
use App\Models\Procurement;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DynamicProcurementFormResolver
{
    public function activeFor(Procurement $procurement, bool $required = false): ?DynamicForm
    {
        $forms = DynamicForm::approved()
            ->where('procurement_id', $procurement->id)
            ->with('fields')
            ->limit(2)
            ->get();

        if ($forms->count() > 1) {
            throw new ConflictHttpException(
                'This procurement has conflicting active application forms. Applications are paused until support resolves the conflict.',
            );
        }

        $form = $forms->first();
        if (! $form && $required) {
            throw new NotFoundHttpException('An active application form is not available.');
        }

        return $form;
    }
}
