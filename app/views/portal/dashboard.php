<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi credencial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .credential-card { max-width: 430px; }
        #qrImage { width: 260px; height: 260px; object-fit: contain; }
        .countdown { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-light min-vh-100">
<nav class="navbar navbar-dark bg-primary shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold"><i class="bi bi-qr-code me-2"></i>Mi credencial</span>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#passModal" aria-label="Cambiar contraseña">
                <i class="bi bi-key-fill"></i>
            </button>
            <a href="?c=Portal&a=logout" class="btn btn-sm btn-light text-primary fw-bold">Salir</a>
        </div>
    </div>
</nav>

<main class="container py-4 py-md-5">
    <div class="card credential-card mx-auto border-0 shadow text-center">
        <div class="card-header bg-white border-0 pt-4">
            <span class="badge text-bg-primary rounded-pill px-3">QR DINÁMICO</span>
        </div>
        <div class="card-body px-4 pb-4">
            <h1 class="h3 fw-bold mb-1"><?php echo htmlspecialchars($empName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-muted mb-3"><?php echo htmlspecialchars($empCode, ENT_QUOTES, 'UTF-8'); ?></p>

            <div class="d-inline-block border rounded-4 p-2 bg-white shadow-sm">
                <img id="qrImage" src="?c=Portal&amp;a=qr&amp;t=<?php echo time(); ?>" alt="QR temporal de asistencia">
            </div>

            <div class="mt-3">
                <p class="mb-1 fw-semibold text-success"><i class="bi bi-shield-check me-1"></i>Código seguro de un solo uso</p>
                <p class="text-muted small mb-0">Se renovará automáticamente en <span id="countdown" class="countdown fw-bold">30</span> segundos.</p>
                <p class="text-muted small mt-2 mb-0"><i class="bi bi-phone-lock me-1"></i>Tu cuenta está vinculada a este dispositivo.</p>
            </div>
        </div>
        <div class="card-footer bg-primary-subtle border-0 py-3 small text-primary-emphasis">
            Presenta esta pantalla ante la cámara del kiosco. No compartas capturas.
        </div>
    </div>
</main>

<div class="modal fade" id="passModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h2 class="modal-title fs-5 fw-bold"><i class="bi bi-shield-lock"></i> Cambiar contraseña</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form action="?c=Portal&amp;a=change_password" method="POST">
                <div class="modal-body">
                    <label class="form-label">Contraseña actual</label>
                    <input type="password" name="current_password" class="form-control mb-3" required>
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="new_password" class="form-control mb-3" required minlength="8">
                    <label class="form-label">Confirmar nueva contraseña</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const qrImage = document.getElementById('qrImage');
    const countdown = document.getElementById('countdown');
    let secondsRemaining = 30;

    function refreshQr() {
        qrImage.src = `?c=Portal&a=qr&t=${Date.now()}`;
        secondsRemaining = 30;
        countdown.textContent = secondsRemaining;
    }

    setInterval(() => {
        secondsRemaining -= 1;
        countdown.textContent = secondsRemaining;
        if (secondsRemaining <= 0) refreshQr();
    }, 1000);

    qrImage.addEventListener('error', () => {
        countdown.textContent = '--';
        setTimeout(refreshQr, 5000);
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg') === 'pass_actualizada') Swal.fire('¡Éxito!', 'Contraseña actualizada.', 'success');
    if (urlParams.get('err') === 'pass_incorrecta') Swal.fire('Error', 'Contraseña actual incorrecta.', 'error');
    if (urlParams.get('err') === 'no_coinciden') Swal.fire('Error', 'Las nuevas contraseñas no coinciden.', 'error');
</script>
</body>
</html>
