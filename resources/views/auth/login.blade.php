@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
<div class="row justify-content-center w-100 m-0">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

        {{-- Logo / branding --}}
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:60px;height:60px;background:rgba(255,255,255,.12);">
                <i class="fas fa-map-marked-alt fa-2x text-white"></i>
            </div>
            <h4 class="fw-bold text-white mb-1">Sablayan MDRRMO</h4>
            <p class="text-white-50 mb-0" style="font-size:.82rem;">
                Disaster Risk Mapping System
            </p>
        </div>

        {{-- Card --}}
        <div class="card shadow-lg border-0">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-4 text-center text-muted">Sign in to your account</h6>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="username" class="form-label fw-medium">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input
                                id="username"
                                type="text"
                                class="form-control border-start-0 @error('username') is-invalid @enderror"
                                name="username"
                                value="{{ old('username') }}"
                                placeholder="Enter your username"
                                required
                                autocomplete="username"
                                autofocus
                            >
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-medium">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input
                                id="password"
                                type="password"
                                class="form-control border-start-0 @error('password') is-invalid @enderror"
                                name="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                   {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label text-muted" for="remember" style="font-size:.85rem;">
                                Remember me
                            </label>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center mt-3">
            <small class="text-white-50">
                Municipality of Sablayan, Occidental Mindoro &mdash; MDRRMO
            </small>
        </div>

    </div>
</div>
@endsection
