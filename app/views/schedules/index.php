<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Horarios</title>
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

    <main class="flex-grow-1 p-4" style="height: 100vh; overflow-y: auto;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold text-secondary mb-1"><i class="bi bi-clock-history"></i> Horarios</h2>
                <p class="text-muted mb-0">Registre y consulte los turnos disponibles para los empleados.</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                <i class="bi bi-plus-circle"></i> Nuevo horario
            </button>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'creado'): ?>
            <div class="alert alert-success alert-dismissible fade show">Horario registrado correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php if ($_GET['err'] === 'duplicado'): ?>Ya existe un horario con ese nombre.
                <?php elseif ($_GET['err'] === 'datos_incompletos'): ?>Complete todos los campos obligatorios.
                <?php else: ?>No se pudo registrar el horario. Inténtelo nuevamente.<?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th class="ps-4">Horario</th><th>Entrada</th><th>Desayuno</th><th>Almuerzo</th><th>Salida</th><th class="text-center pe-4">Empleados</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($schedules)): ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?php echo htmlspecialchars($schedule['name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['entry_time']); ?></td>
                                    <td>
                                        <?php if (!empty($schedule['breakfast_time']) || !empty($schedule['breakfast_return_time'])): ?>
                                            <?php echo htmlspecialchars($schedule['breakfast_time'] ?: '—'); ?> – <?php echo htmlspecialchars($schedule['breakfast_return_time'] ?: '—'); ?>
                                        <?php else: ?><span class="badge text-bg-secondary">No aplica</span><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($schedule['lunch_out_time']); ?> – <?php echo htmlspecialchars($schedule['lunch_return_time']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['check_out_time']); ?></td>
                                    <td class="text-center pe-4"><span class="badge rounded-pill text-bg-info"><?php echo (int)$schedule['employee_count']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center p-5 text-muted">No hay horarios registrados.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="newScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-clock"></i> Registrar horario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="?c=Schedule&a=store" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-bold">Nombre</label><input type="text" name="name" class="form-control" placeholder="Ej: TURNO MAÑANA" required></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Entrada</label><input type="time" name="entry_time" class="form-control" value="08:00" required></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Salida final</label><input type="time" name="check_out_time" class="form-control" value="18:00" required></div>
                        <div class="col-12"><div class="alert alert-info py-2 mb-0"><i class="bi bi-info-circle"></i> El horario de desayuno es opcional. Deje ambos campos vacíos si el turno no contempla desayuno.</div></div>
                        <div class="col-md-6"><label class="form-label">Salida a desayuno <span class="text-muted">(opcional)</span></label><input type="time" name="breakfast_time" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Retorno de desayuno <span class="text-muted">(opcional)</span></label><input type="time" name="breakfast_return_time" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Salida a almuerzo</label><input type="time" name="lunch_out_time" class="form-control" value="13:00" required></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Retorno de almuerzo</label><input type="time" name="lunch_return_time" class="form-control" value="14:00" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar horario</button></div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
