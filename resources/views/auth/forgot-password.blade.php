@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
    <form method="POST" action="{{ route('password.request.store') }}">
        @csrf
        <p style="text-align:center;font-size:13px;color:var(--gray-600);margin-bottom:16px;">
            Enter your username and an admin will reset your password to the default.
        </p>
        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus class="form-control">
            @error('username')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Submit Request</button>
        <div class="auth-link">
            <a href="{{ route('login') }}">&larr; Back to Login</a>
        </div>
    </form>
@endsection
