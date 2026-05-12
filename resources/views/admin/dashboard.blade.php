<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MBLISTTDA e-DTR System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="navbar-left">
                <a href="{{ route('dtr.index') }}" class="btn btn-outline btn-sm">&larr; e-DTR Home</a>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button class="btn btn-outline btn-sm">Logout</button>
            </form>
        </div>

        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p>Office &amp; Section Management</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="stats-row">
            <div class="stat-card">
                <h3>{{ $officeCount }}</h3>
                <p>Offices</p>
            </div>
            <div class="stat-card">
                <h3>{{ $sectionCount }}</h3>
                <p>Sections</p>
            </div>
            <div class="stat-card">
                <h3>{{ $employeeCount }}</h3>
                <p>Employees</p>
            </div>
            <div class="stat-card">
                <h3>{{ $unassignedCount }}</h3>
                <p>Unassigned</p>
            </div>
        </div>

        <div class="stats-row">
            <a href="{{ route('admin.offices') }}" class="card card-link" style="flex:1;">
                <h2>Manage Divisions</h2>
                <p>Add, edit, or remove offices</p>
            </a>
            <a href="{{ route('admin.sections') }}" class="card card-link" style="flex:1;">
                <h2>Manage Sections</h2>
                <p>Add, edit, or remove sections under each office</p>
            </a>
            <a href="{{ route('admin.employees') }}" class="card card-link" style="flex:1;">
                <h2>Assign Employees</h2>
                <p>Assign employees to offices and sections</p>
            </a>
            <a href="{{ route('admin.settings') }}" class="card card-link" style="flex:1;">
                <h2>Settings</h2>
                <p>Manage system configuration</p>
            </a>
        </div>
    </div>
</body>
</html>
