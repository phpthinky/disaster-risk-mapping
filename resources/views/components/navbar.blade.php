@php
    $user = auth()->user();
    $initial = strtoupper(substr($user->username, 0, 1));
    $displayName = $user->name ?? $user->username;
    $roleLabel = ucwords(str_replace('_', ' ', $user->role));

    // Active alerts visible to this user (municipality-wide OR matching their barangay)
    $activeAlerts = \App\Models\Alert::where('is_active', true)
        ->where(function ($q) use ($user) {
            $q->whereNull('barangay_id');
            if ($user->barangay_id) {
                $q->orWhere('barangay_id', $user->barangay_id);
            }
        })
        ->orderByRaw("FIELD(alert_type,'danger','warning','info')")
        ->get();

    // Active announcements targeted at this user
    $activeAnnouncements = \App\Models\Announcement::where('is_active', true)
        ->visibleTo($user)
        ->latest()
        ->limit(5)
        ->get();

    $alertCount = $activeAlerts->count();
    $annCount   = $activeAnnouncements->count();
    $totalBadge = $alertCount + $annCount;
@endphp

<nav class="top-navbar navbar fixed-top" id="topNavbar">
    <div class="container-fluid px-3">

        {{-- Left: sidebar toggle + brand --}}
        <div class="d-flex align-items-center">
            <button class="btn btn-link text-white me-3 p-0" id="sidebarToggleBtn" title="Toggle sidebar">
                <i class="fas fa-bars fa-lg"></i>
            </button>

            <a class="navbar-brand d-flex align-items-center" href="{{ $user->isAdmin() ? route('dashboard') : ($user->isDivisionChief() ? route('dashboard.division') : route('dashboard.barangay')) }}">
                <div class="brand-icon me-2">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div class="d-none d-md-block">
                    <div class="brand-title">Sablayan</div>
                    <div class="brand-subtitle">Risk Assessment System</div>
                </div>
            </a>
        </div>

        {{-- Right: notifications bell + user menu --}}
        <div class="d-flex align-items-center gap-2">

            {{-- Notifications dropdown (alerts + announcements) --}}
            @if($totalBadge > 0)
            <div class="dropdown">
                <button class="btn btn-link text-white p-1 position-relative"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        title="Alerts &amp; Announcements">
                    <i class="fas fa-bell fa-lg"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          style="font-size:.6rem; min-width:1.2em;">
                        {{ $totalBadge }}
                    </span>
                </button>

                <div class="dropdown-menu dropdown-menu-end shadow p-0" style="width:340px; max-height:480px; overflow-y:auto;">

                    {{-- Active Alerts section --}}
                    @if($alertCount > 0)
                    <div class="px-3 py-2 bg-danger bg-opacity-10 border-bottom">
                        <span class="fw-semibold text-danger" style="font-size:.8rem;">
                            <i class="fas fa-triangle-exclamation me-1"></i>
                            {{ $alertCount }} Active Alert{{ $alertCount > 1 ? 's' : '' }}
                        </span>
                    </div>
                    @foreach($activeAlerts as $al)
                    @php
                        $alClass = match($al->alert_type) {
                            'danger'  => 'danger',
                            'warning' => 'warning',
                            default   => 'info',
                        };
                    @endphp
                    <div class="px-3 py-2 border-bottom" style="background: var(--bs-{{ $alClass }}-bg-subtle, #fff3cd11);">
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-{{ $alClass }} mt-1 flex-shrink-0">{{ ucfirst($al->alert_type) }}</span>
                            <div style="min-width:0;">
                                <div class="fw-semibold" style="font-size:.82rem;">{{ $al->title }}</div>
                                @if($al->message)
                                <div class="text-muted" style="font-size:.76rem; white-space:pre-wrap;">{{ Str::limit($al->message, 80) }}</div>
                                @endif
                                @if($al->barangay)
                                <div class="text-muted" style="font-size:.72rem;"><i class="fas fa-map-pin me-1"></i>{{ $al->barangay->name }}</div>
                                @else
                                <div class="text-muted" style="font-size:.72rem;"><i class="fas fa-globe-asia me-1"></i>Municipality-wide</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @endif

                    {{-- Announcements section --}}
                    @if($annCount > 0)
                    <div class="px-3 py-2 bg-primary bg-opacity-10 border-bottom">
                        <span class="fw-semibold text-primary" style="font-size:.8rem;">
                            <i class="fas fa-bullhorn me-1"></i>
                            Announcements
                        </span>
                    </div>
                    @foreach($activeAnnouncements as $ann)
                    @php
                        $annColor = \App\Models\Announcement::typeBorderColor($ann->announcement_type);
                        $annIcon  = \App\Models\Announcement::typeIcon($ann->announcement_type);
                    @endphp
                    <div class="px-3 py-2 border-bottom" style="border-left: 3px solid {{ $annColor }} !important;">
                        <div class="fw-semibold" style="font-size:.82rem;">
                            <i class="fas {{ $annIcon }} me-1" style="color:{{ $annColor }};"></i>
                            {{ $ann->title }}
                        </div>
                        <div class="text-muted" style="font-size:.76rem; white-space:pre-wrap;">{{ Str::limit($ann->message, 80) }}</div>
                        <div class="text-muted" style="font-size:.7rem;">{{ $ann->created_at->diffForHumans() }}</div>
                    </div>
                    @endforeach
                    @endif

                    <div class="px-3 py-2 text-center">
                        <a href="{{ route('announcements.index') }}" class="text-primary small me-3">
                            <i class="fas fa-bullhorn me-1"></i>All Announcements
                        </a>
                        @if($user->isAdmin())
                        <a href="{{ route('alerts.index') }}" class="text-danger small">
                            <i class="fas fa-triangle-exclamation me-1"></i>Manage Alerts
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @else
            {{-- No active items — show quiet bell --}}
            <a href="{{ route('announcements.index') }}" class="btn btn-link text-white p-1" title="Announcements">
                <i class="fas fa-bell fa-lg opacity-60"></i>
            </a>
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
