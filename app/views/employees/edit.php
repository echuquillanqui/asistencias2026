<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empleado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* Estilos necesarios para el Sidebar */
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

    <div class="flex-grow-1 p-4" style="height: 100vh; overflow-y: auto;">
        
        <div class="d-flex align-items-center mb-4">
            <a href="?c=Employee" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i> Volver</a>
            <h2 class="mb-0 fw-bold text-secondary"><i class="bi bi-pencil-square"></i> Editar Empleado</h2>
        </div>

        <div class="card shadow border-0">
            <div class="card-header bg-warning text-dark fw-bold">
                Actualizar Datos
            </div>
            <div class="card-body p-4">
                <form action="?c=Employee&a=update_data" method="POST">
                    <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Código (QR)</label>
                            <input type="text" name="employee_code" class="form-control" required value="<?php echo $emp['employee_code']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo $emp['email']; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombres</label>
                            <input type="text" name="first_name" class="form-control" required value="<?php echo $emp['first_name']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Apellidos</label>
                            <input type="text" name="last_name" class="form-control" required value="<?php echo $emp['last_name']; ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Departamento</label>
                            <select name="department_id" class="form-select">
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($dept['id'] == $emp['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo $dept['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cargo</label>
                            <input type="text" name="position" class="form-control" required value="<?php echo $emp['position']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sede</label>
                            <select name="site_name" class="form-select text-uppercase" required>
                                <?php foreach($availableSites as $site): ?>
                                    <option value="<?php echo $site; ?>" <?php echo (($emp['site_name'] ?? '') === $site) ? 'selected' : ''; ?>>
                                        <?php echo $site; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Horario</label>
                            <select name="schedule_id" class="form-select">
                                <option value="">-- Horario General --</option>
                                <?php foreach($schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>" <?php echo ((int)($emp['schedule_id'] ?? 0) === (int)$schedule['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($schedule['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-end">
                        <a href="?c=Employee" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-warning fw-bold px-4">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
