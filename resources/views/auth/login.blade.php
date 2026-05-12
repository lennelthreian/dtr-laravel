@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus class="form-control">
            @error('username')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required class="form-control">
            @error('password')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
        <div class="auth-link">
            Don't have an account? <a href="{{ route('register') }}">Register</a>
        </div>
    </form>
@endsection
