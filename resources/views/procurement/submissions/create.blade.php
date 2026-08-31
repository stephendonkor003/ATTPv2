@extends('layouts.app')

@section('content')
    <div class="container">
        <h4>{{ $form->name }}</h4>

        <form method="POST" enctype="multipart/form-data" action="{{ route('submissions.store', $form) }}">
            @csrf

            @foreach ($form->fields as $field)
                <div class="mb-3">
                    <label>{{ $field->label }}</label>

                    @if ($field->field_type === 'textarea')
                        <textarea name="{{ $field->field_key }}" class="form-control"></textarea>
                    @elseif($field->field_type === 'file')
                        <input type="file" name="{{ $field->field_key }}" class="form-control">
                    @else
                        <input type="{{ $field->field_type }}" name="{{ $field->field_key }}" class="form-control">
                    @endif
                </div>
            @endforeach

            <button class="btn btn-success">Submit</button>
        </form>
    </div>
@endsection
