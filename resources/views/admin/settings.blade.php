<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin - MBLISTTDA e-DTR System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="navbar-left">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-sm">&larr; Admin Dashboard</a>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button class="btn btn-outline btn-sm">Logout</button>
            </form>
        </div>

        <div class="page-header">
            <h1>System Settings</h1>
            <p>Manage DTR system configuration</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <table class="table" style="max-width:600px;">
                <thead>
                    <tr>
                        <th style="width:250px;">Setting</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($settings as $setting)
                        <tr>
                            <td>
                                <label for="{{ $setting->setting_key }}" style="font-weight:600; font-size:13px;">
                                    {{ ucwords(str_replace('_', ' ', $setting->setting_key)) }}
                                </label>
                            </td>
                            <td>
                                @if ($setting->setting_key === 'agency_head_user_id')
                                    <select id="{{ $setting->setting_key }}"
                                            name="{{ $setting->setting_key }}"
                                            class="form-control" style="width:100%;">
                                        <option value="">-- Select User --</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" {{ $setting->setting_value == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }} ({{ $user->emp_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" id="{{ $setting->setting_key }}"
                                           name="{{ $setting->setting_key }}"
                                           value="{{ $setting->setting_value }}"
                                           class="form-control" style="width:100%;">
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary" style="margin-top:16px;">Save Settings</button>
        </form>
    </div>
</body>
</html>
