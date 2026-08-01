<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Payroll 2.0 PT Batu Karang</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,0.175);
            width: 100%;
            max-width: 420px;
        }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="card login-card mx-auto bg-white p-4 p-md-5">
        <!-- BRAND LOGO -->
        <div class="text-center mb-4">
            <div class="bg-primary text-white rounded-3 p-3 d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                <i class="fa-solid fa-gem fs-4"></i>
            </div>
            <h5 class="fw-bold text-dark m-0">PT. BATU KARANG</h5>
            <span class="badge bg-info text-dark fw-bold px-2 py-1 mt-1">PAYROLL 2.0 SYSTEM</span>
        </div>

        <!-- ALERT ERROR -->
        @if($errors->has('email'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 small mb-3" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i> {{ $errors->first('email') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 small mb-3" role="alert">
                <i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- FORM LOGIN -->
        <form action="{{ route('login.perform') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold small text-dark">Alamat Email Admin</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control bg-light border-start-0" placeholder="admin@batukarang.com" value="{{ old('email') }}" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small text-dark">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control bg-light border-start-0" placeholder="••••••••" required>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                    <label class="form-check-label small text-muted" for="rememberMe">
                        Ingat Saya
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold shadow-sm">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk ke Sistem
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <small class="text-muted">© {{ date('Y') }} PT. Batu Karang Malang</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>