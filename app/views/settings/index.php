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

        <?php if(isset($_GET['err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($_GET['err']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="?c=Setting&a=update" method="POST" enctype="multipart/form-data" class="row g-4">
        <div class="col-xl-6">
        <div class="card shadow border-0 h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="m-0">Horarios</h5>
            </div>
            <div class="card-body p-4">
                    
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

            </div>
        </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="m-0"><i class="bi bi-building"></i> Datos para el reporte SUNAFIL</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small">Estos datos se mostrarán dinámicamente en la cabecera del Excel exportado.</p>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Razón social</label>
                            <input type="text" name="employer_business_name" maxlength="200" class="form-control" value="<?php echo htmlspecialchars($employer_business_name ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">RUC</label>
                            <input type="text" name="employer_ruc" maxlength="11" minlength="11" pattern="[0-9]{11}" inputmode="numeric" class="form-control" value="<?php echo htmlspecialchars($employer_ruc ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Nombre comercial</label>
                            <input type="text" name="employer_trade_name" maxlength="200" class="form-control" value="<?php echo htmlspecialchars($employer_trade_name ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Domicilio fiscal</label>
                            <input type="text" name="employer_fiscal_address" maxlength="255" class="form-control" value="<?php echo htmlspecialchars($employer_fiscal_address ?? ''); ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Sede / centro de trabajo</label>
                            <input type="text" name="workplace_name" maxlength="150" class="form-control" value="<?php echo htmlspecialchars($workplace_name ?? ''); ?>">
                            <div class="form-text">Se usa cuando el reporte incluye todas las sedes.</div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Dirección del centro de trabajo</label>
                            <input type="text" name="workplace_address" maxlength="255" class="form-control" value="<?php echo htmlspecialchars($workplace_address ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Logo de la empresa</label>
                            <input type="file" name="employer_logo" accept="image/png,image/jpeg" class="form-control">
                            <div class="form-text">PNG o JPG, máximo 2 MB. Si no selecciona uno, se conserva el actual.</div>
                            <?php if (!empty($employer_logo)): ?>
                                <img src="<?php echo htmlspecialchars($employer_logo); ?>" alt="Logo actual" class="mt-3 border rounded p-2 bg-white" style="max-width: 180px; max-height: 90px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-lg px-5"><i class="bi bi-save me-2"></i>Guardar configuración</button>
        </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
