<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MBLISTTDA e-DTR System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body>
    <div class="layout-sidebar">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
                <p>MBLISTTDA e-DTR System</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('admin.dashboard') }}" class="active"><span>Dashboard</span></a>
                <a href="{{ route('admin.offices') }}"><span>Manage Divisions</span></a>
                <a href="{{ route('admin.sections') }}"><span>Manage Sections</span></a>
                <a href="{{ route('admin.employees') }}"><span>Assign Employees</span></a>
                <a href="{{ route('admin.settings') }}"><span>Settings</span></a>
            </nav>
            <div class="sidebar-footer">
                <a href="{{ route('dtr.index') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;">&larr; e-DTR Home</a>
            </div>
        </div>
        <div class="main-content">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Dashboard</h1>
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="stats-row">
                <div class="stat-card">
                    <h3>{{ $officeCount }}</h3>
                    <p>Divisions</p>
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
    </div>
</body>
</html>
