@extends('layouts.app')

@section('title', 'Edit language')

@section('content')
    <form method="POST" action="{{ route('admin.languages.update', $language) }}" class="card form narrow">
        @csrf
        @method('PUT')
        <h1>Edit language</h1>

        <label for="code">Code</label>
        <input id="code" name="code" value="{{ old('code', $language->code) }}" required maxlength="10">
        <p class="hint">Used in CSV files. Changing it does not affect existing cards.</p>
        @error('code') <p class="error">{{ $message }}</p> @enderror

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name', $language->name) }}" required maxlength="64">
        @error('name') <p class="error">{{ $message }}</p> @enderror

        <div class="buttons">
            <button type="submit" class="button">Save</button>
            <a href="{{ route('admin.languages.index') }}">Cancel</a>
        </div>
    </form>
@endsection
