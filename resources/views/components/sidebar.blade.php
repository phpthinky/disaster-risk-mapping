@php
    $user = auth()->user();
    $role = $user->role;

    // Helper: is this route the current active one?
    $active = fn(string $routeName) =>
        request()->routeIs($routeName) ? 'active' : '';

    $menu = [
        // MAIN section — visible to all roles
        [
            'section' => 'MAIN MENU',
            'items'   => [
                [
                    'title'  => 'Dashboard',
                    'icon'   => 'fa-chart-pie',
                    'route'  => match($role) {
                        'admin'          => 'dashboard',
                        'division_chief' => 'dashboard.division',
                        'barangay_staff' => 'dashboard.barangay',
                        default          => 'dashboard',
                    },
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Map View',
                    'icon'   => 'fa-map-marked-alt',
                    'route'  => 'map.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Households',
                    'icon'   => 'fa-house-chimney-user',
                    'route'  => 'households.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Hazard Zones',
                    'icon'   => 'fa-triangle-exclamation',
                    'route'  => 'hazards.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Incident Reports',
                    'icon'   => 'fa-file-circle-exclamation',
                    'route'  => 'incidents.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Population Data',
                    'icon'   => 'fa-users',
                    'route'  => 'population.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Evacuation Centers',
                    'icon'   => 'fa-house-medical-flag',
                    'route'  => 'evacuations.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Barangays',
                    'icon'   => 'fa-city',
                    'route'  => 'barangays.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
            ],
        ],
        // COMMUNICATION section
        [
            'section' => 'COMMUNICATION',
            'items'   => [
                [
                    'title'  => 'Announcements',
                    'icon'   => 'fa-bullhorn',
                    'route'  => 'announcements.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Alerts',
                    'icon'   => 'fa-bell',
                    'route'  => 'alerts.index',
                    'roles'  => ['admin'],
                ],
            ],
        ],
        // SYSTEM section
        [
            'section' => 'SYSTEM',
            'items'   => [
                [
                    'title'  => 'Reports',
                    'icon'   => 'fa-file-lines',
                    'route'  => 'reports.index',
                    'roles'  => ['admin', 'barangay_staff', 'division_chief'],
                ],
                [
                    'title'  => 'Users',
                    'icon'   => 'fa-users-gear',
                    'route'  => 'users.index',
                    'roles'  => ['admin'],           // admin only
                ],
            ],
        ],
    ];
@endphp

<div class="sidebar" id="sidebar">
    {{-- Brand --}}
    <div class="sidebar-header p-3 border-bottom">
    <div class="d-flex align-items-center justify-content-center justify-content-md-start">
        
    </div>
</div>
    {{-- Menu --}}
    <div class="sidebar-menu">
        @foreach($menu as $group)
            <div class="menu-label">{{ $group['section'] }}</div>
            <ul class="nav flex-column">
                @foreach($group['items'] as $item)
                    @if(in_array($role, $item['roles']))
                        @php
                            // Only try to build URL if route exists
                            $routeExists = \Illuminate\Support\Facades\Route::has($item['route']);
                            $url = $routeExists ? route($item['route']) : '#';
                            $isActive = $routeExists && request()->routeIs($item['route']) ? 'active' : '';
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link {{ $isActive }}" href="{{ $url }}">
                                <i class="fas {{ $item['icon'] }} menu-icon"></i>
                                <span class="menu-text">{{ $item['title'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endforeach
    </div>

    {{-- User footer --}}
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar-sm">
                {{ strtoupper(substr($user->username, 0, 1)) }}
            </div>
            <div class="user-details">
                <span class="user-name">{{ $user->name ?? $user->username }}</span>
                <span class="user-role">{{ ucwords(str_replace('_', ' ', $role)) }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="ms-2">
                @csrf
                <button type="submit" class="btn btn-link logout-btn p-0" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </div>
</div>
