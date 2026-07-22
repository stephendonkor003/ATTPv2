<x-think-tank.partials.shell :member="$member" title="Grievance Redress Mechanism">
    @include('grm.submissions._form', [
        'submissionAction' => route('think-tank.grievances.store'),
        'isPublicSubmission' => false,
    ])
</x-think-tank.partials.shell>
