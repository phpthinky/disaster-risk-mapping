@php
    $user = auth()->user();
    $initial = strtoupper(substr($user->username, 0, 1));
    $displayName = $user->name ?? $user->username;
    $roleLabel = ucwords(str_replace('_', ' ', $user->role));

    $activeAlerts = \App\Models\Alert::where('is_active', true)->count();
@endphp

<nav class="top-navbar navbar fixed-top" id="topNavbar">
    <div class="container-fluid px-3">

        {{-- Left: sidebar toggle + brand --}}
        <div class="d-flex align-items-center">
            <button class="btn btn-link text-white me-3 p-0" id="sidebarToggleBtn" title="Toggle sidebar">
                <i class="fas fa-bars fa-lg"></i>
            </button>

            <a class="navbar-brand d-flex align-items-center" href="{{ auth()->user()->isAdmin() ? route('dashboard') : (auth()->user()->isDivisionChief() ? route('dashboard.division') : route('dashboard.barangay')) }}">
                <div class="brand-icon me-2">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div class="d-none d-md-block">
                    <div class="brand-title">Sablayan</div>
                    <div class="brand-subtitle">Risk Assessment System</div>
                </div>
            </a>
        </div>

        {{-- Right: alerts + user menu --}}
        <div class="d-flex align-items-center">

            {{-- Active alerts badge --}}
            @if($activeAlerts > 0)
                <div class="nav-item me-3">
                    <span class="badge bg-danger px-2 py-1" style="font-size:.75rem;">
                        <i class="fas fa-triangle-exclamation me-1"></i>{{ $activeAlerts }} Active Alert{{ $activeAlerts > 1 ? 's' : '' }}
                    </span>
                </div>
            @endif

            {{-- User dropdown --}}
            <div class="dropdown">
                <button class="btn btn-link text-white d-flex align-items-center p-0 text-decoration-none"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="nav-avatar me-2">{{ $initial }}</div>
                    <div class="d-none d-md-block text-start">
                        <div class="nav-user-name">{{ $displayName }}</div>
                        <div class="nav-user-role">{{ $roleLabel }}</div>
                    </div>
                    <i class="fas fa-chevron-down ms-2 small opacity-75"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width:200px;">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-semibold" style="font-size:.9rem;">{{ $displayName }}</div>
                        <div class="text-muted" style="font-size:.75rem;">{{ $roleLabel }}</div>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="#">
                            <i class="fas fa-user-circle me-2 text-primary"></i> Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item py-2 text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> Sign Out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</nav>
