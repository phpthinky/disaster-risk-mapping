<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — Sablayan Risk Assessment</title>

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Font Awesome 6 --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- Leaflet Maps --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Compiled app styles --}}
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">

    {{-- Page-specific styles --}}
    @stack('styles')
</head>
<body>

@auth
    {{-- Top Navbar --}}
    <x-navbar />

    {{-- Sidebar --}}
    <x-sidebar />

    {{-- Mobile overlay --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    {{-- Main wrapper --}}
    <div class="main-wrapper" id="mainWrapper">

        @hasSection('page-header')
            <div class="page-header-bar px-4 pt-4 pb-0">
                @yield('page-header')
            </div>
        @endif

        <div class="content-wrapper">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fas fa-circle-exclamation me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <i class="fas fa-triangle-exclamation me-2"></i>{{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="fas fa-circle-exclamation me-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>

        {{-- Footer --}}
        <footer class="app-footer">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <i class="fas fa-copyright me-1"></i>
                        {{ date('Y') }} Sablayan MDRRMO. All rights reserved.
                    </div>
                    <div class="col-md-6 text-md-end">
                        <i class="fas fa-code-branch me-1"></i> Version 3.0.0
                    </div>
                </div>
            </div>
        </footer>

    </div>{{-- /.main-wrapper --}}

@else
    {{-- Guest layout (login page) --}}
    <div class="guest-wrapper">
        @yield('content')
    </div>
@endauth

{{-- Bootstrap 5 JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

{{-- Leaflet JS --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

{{-- Compiled app JS --}}
<script src="{{ mix('js/app.js') }}"></script>

{{-- Sidebar toggle --}}
@auth
<script>
(function () {
    var sidebar     = document.getElementById('sidebar');
    var mainWrapper = document.getElementById('mainWrapper');
    var toggleBtn   = document.getElementById('sidebarToggleBtn');
    var overlay     = document.getElementById('sidebarOverlay');

    if (!sidebar) return;

    // Restore persisted collapsed state on desktop
    if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 992) {
        sidebar.classList.add('collapsed');
        mainWrapper && mainWrapper.classList.add('expanded');
    }

    function toggleSidebar() {
        if (window.innerWidth <= 992) {
            sidebar.classList.toggle('show');
            overlay && overlay.classList.toggle('show');
        } else {
            sidebar.classList.toggle('collapsed');
            mainWrapper && mainWrapper.classList.toggle('expanded');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
    }

    toggleBtn && toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    overlay && overlay.addEventListener('click', function () {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('show');
            overlay && overlay.classList.remove('show');
        }
    });

    // Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
})();
</script>
@endauth

{{-- Page-specific scripts --}}
@stack('scripts')

</body>
</html>
