@extends('layouts.app')

@section('title', 'Request Submitted')

@section('content')
    <div style="text-align:center;padding:12px 0;">
        <p style="font-size:14px;color:var(--gray-700);margin-bottom:20px;">
            If that username exists in our system, an admin will review your request and reset your password to the default.
        </p>
        <a href="{{ route('login') }}" class="btn btn-primary" style="display:inline-block;padding:10px 24px;">Back to Login</a>
    </div>
@endsection
