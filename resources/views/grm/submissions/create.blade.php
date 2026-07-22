@extends(auth()->user()?->user_type === 'funding_partner' ? 'layouts.partner' : (auth()->user()?->user_type === 'vendor' ? 'layouts.vendor' : (auth()->user()?->user_type === 'ttl' ? 'layouts.ttl' : 'layouts.app')))

@section('title', 'Grievance Redress Mechanism')
@section('partner_page_title', 'Grievance Redress Mechanism')
@section('partner_page_subtitle', 'Log a program-linked grievance for formal ATTP follow up.')
@section('ttl_page_title', 'Grievance Redress Mechanism')
@section('ttl_page_subtitle', 'Log a program-linked grievance for formal ATTP follow up.')

@section('content')
    @include('grm.submissions._form', [
        'submissionAction' => route('grm.submissions.store'),
        'isPublicSubmission' => false,
    ])
@endsection
