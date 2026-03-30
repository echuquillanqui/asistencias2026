<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #212529; color: white; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 20px; display: block; border-left: 3px solid transparent; transition: 0.3s; }
        .sidebar a:hover { background-color: #343a40; color: white; }
        .sidebar a.active { background-color: #0d6efd; color: white; border-left-color: white; }
        .sidebar i { width: 25px; }
    </style>
</head>
<body class="bg-light">

<div class="d-flex">
    <?php require_once '../app/views/layouts/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <h2 class="mb-4 fw-bold text-secondary"><i class="bi bi-gear-fill"></i> Configuración del Sistema</h2>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Configuración guardada correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow border-0" style="max-width: 500px;">
            <div class="card-header bg-primary text-white">
                <h5 class="m-0">Horarios</h5>
            </div>
            <div class="card-body p-4">
                <form action="?c=Setting&a=update" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Horarios de Entrada</label>
                        <p class="text-muted small">Puede colocar más de un horario separado por coma. Ej: 08:00,09:00</p>
                        <input type="text" name="entry_time" class="form-control form-control-lg text-center fw-bold" 
                               value="<?php echo htmlspecialchars($entry_time ?? '08:00'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Horarios de Desayuno</label>
                        <input type="text" name="breakfast_time" class="form-control form-control-lg text-center fw-bold" 
                               value="<?php echo htmlspecialchars($breakfast_time ?? '09:30'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Horarios de Salida a Almuerzo</label>
                        <input type="text" name="lunch_out_time" class="form-control form-control-lg text-center fw-bold" 
                               value="<?php echo htmlspecialchars($lunch_out_time ?? '13:00'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Horarios de Retorno de Almuerzo</label>
                        <input type="text" name="lunch_return_time" class="form-control form-control-lg text-center fw-bold" 
                               value="<?php echo htmlspecialchars($lunch_return_time ?? '14:00'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Horarios de Salida</label>
                        <input type="text" name="check_out_time" class="form-control form-control-lg text-center fw-bold" 
                               value="<?php echo htmlspecialchars($check_out_time ?? '18:00'); ?>" required>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Guardar Cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
