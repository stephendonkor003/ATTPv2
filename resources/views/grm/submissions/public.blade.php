@extends('layouts.public')

@section('title', 'Log a Grievance | ATTP')
@section('description', 'Submit a public or anonymous grievance to the African Think Tank Platform.')

@section('content')
    @include('grm.submissions._form', [
        'submissionAction' => route('public.grievances.store'),
        'isPublicSubmission' => true,
    ])
@endsection
