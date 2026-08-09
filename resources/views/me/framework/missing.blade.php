@extends('layouts.app')

@section('title', 'ATTP MEL Framework Setup Required')
@section('lean_admin_scripts', '1')

@push('styles')
<style>
    .mel-missing {
        max-width: 860px;
        margin: 4rem auto;
        padding: 2rem;
        border: 1px solid #d8e3e8;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 18px 45px rgba(21, 66, 84, .09);
    }
    .mel-missing__icon {
        display: grid;
        width: 54px;
        height: 54px;
        margin-bottom: 1.25rem;
        place-items: center;
        border-radius: 14px;
        color: #fff;
        background: #0b7189;
        font-size: 1.45rem;
    }
    .mel-missing h1 { margin-bottom: .75rem; color: #173f4d; font-size: 1.7rem; }
    .mel-missing p { color: #59727c; line-height: 1.7; }
    .mel-missing code {
        display: block;
        margin: 1.25rem 0;
        padding: 1rem 1.15rem;
        border-radius: 10px;
        color: #e6fbff;
        background: #123744;
        white-space: normal;
        word-break: break-word;
    }
</style>
@endpush

@section('content')
<section class="mel-missing" role="status">
    <span class="mel-missing__icon"><i class="feather-database" aria-hidden="true"></i></span>
    <h1>ATTP MEL framework setup is required</h1>
    <p>
        This page is available, but the controlled ATTP Results Framework has not yet been loaded into this database.
        A server administrator must run the dedicated clean installer after applying the latest migrations.
    </p>
    <code>php artisan db:seed --class=AttpMelCleanSeeder --force</code>
    <p class="mb-0">
        The command replaces the existing ATTP MEL framework and reporting workflow records. Back up the production
        database before running it if existing MEL submissions must be retained.
    </p>
</section>
@endsection
