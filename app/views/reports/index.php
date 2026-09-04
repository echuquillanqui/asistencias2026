<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* Estilos del Sidebar */
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
        <h2 class="mb-4 fw-bold text-secondary">📊 Generar Reportes</h2>

        <?php if (($_GET['err'] ?? '') === 'filtro_invalido'): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Selecciona una sede o un empleado válido para generar el reporte.
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-7">
                <div class="card shadow border-0">
                    <div class="card-header bg-success text-white py-3">
                        <h5 class="m-0"><i class="bi bi-file-earmark-excel"></i> Exportar a Excel (.xls)</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted mb-4">Selecciona el rango de fechas y descarga el historial completo, por sede o por empleado.</p>
                        
                        <form action="?c=Report&a=export" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Fecha Inicio</label>
                                <input type="date" name="start_date" class="form-control form-control-lg" required value="<?php echo date('Y-m-01'); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Fecha Fin</label>
                                <input type="date" name="end_date" class="form-control form-control-lg" required value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Descargar reporte</label>
                                <select name="filter_type" id="filterType" class="form-select form-select-lg">
                                    <option value="all">Completo (todas las sedes y empleados)</option>
                                    <option value="site">Por sede</option>
                                    <option value="employee">Por empleado</option>
                                </select>
                            </div>

                            <div class="mb-4 d-none" id="siteFilter">
                                <label class="form-label fw-bold" for="siteName">Sede</label>
                                <select name="site_name" id="siteName" class="form-select form-select-lg">
                                    <option value="">-- Seleccionar sede --</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?php echo htmlspecialchars($site); ?>"><?php echo htmlspecialchars($site); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4 d-none" id="employeeFilter">
                                <label class="form-label fw-bold" for="employeeId">Empleado</label>
                                <select name="employee_id" id="employeeId" class="form-select form-select-lg">
                                    <option value="">-- Seleccionar empleado --</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo (int)$employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg shadow"><i class="bi bi-download me-2"></i> Descargar Reporte</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="alert alert-info shadow-sm border-0">
                    <h5 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Información</h5>
                    <p>El archivo descargado es en formato <strong>Excel (.xls)</strong> con pestañas y formato de colores.</p>
                    <hr>
                    <p class="mb-0 small">Puedes abrirlo directamente con:</p>
                    <ul class="small mt-2">
                        <li>Microsoft Excel</li>
                        <li>Google Sheets</li>
                        <li>LibreOffice Calc</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const filterType = document.getElementById('filterType');
    const siteFilter = document.getElementById('siteFilter');
    const employeeFilter = document.getElementById('employeeFilter');
    const siteName = document.getElementById('siteName');
    const employeeId = document.getElementById('employeeId');

    function updateReportFilter() {
        const bySite = filterType.value === 'site';
        const byEmployee = filterType.value === 'employee';
        siteFilter.classList.toggle('d-none', !bySite);
        employeeFilter.classList.toggle('d-none', !byEmployee);
        siteName.required = bySite;
        employeeId.required = byEmployee;
    }

    filterType.addEventListener('change', updateReportFilter);
    updateReportFilter();
</script>
</body>
</html>
