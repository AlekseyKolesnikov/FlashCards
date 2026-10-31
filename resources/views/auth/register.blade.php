@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <form method="POST" action="{{ route('register') }}" class="card form narrow">
        @csrf
        <h1>Create a learner account</h1>

        @include('auth._account-fields')

        <button type="submit" class="button">Register</button>

        <p class="muted">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </form>
@endsection
