<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Empleados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        .sidebar { min-height: 100vh; background-color: #212529; color: white; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 20px; display: block; border-left: 3px solid transparent; transition: 0.3s; }
        .sidebar a:hover { background-color: #343a40; color: white; }
        .sidebar a.active { background-color: #0d6efd; color: white; border-left-color: white; }
        .sidebar i { width: 25px; }
        .table-inactive { opacity: 0.6; background-color: #f8f9fa; }

        /* CSS para imprimir carnet */
        @media print {
            body * { visibility: hidden; }
            .modal-backdrop, .modal-header, .modal-footer, .sidebar { display: none !important; }
            #printableArea, #printableArea * { visibility: visible; }
            #printableArea { position: absolute; left: 50%; top: 50px; transform: translateX(-50%); width: 350px; border: 2px solid #333; padding: 30px; border-radius: 15px; text-align: center; }
            #qrImage { width: 200px !important; height: 200px !important; }
        }
    </style>
</head>
<body class="bg-light">

<div class="d-flex">
    <?php require_once '../app/views/layouts/sidebar.php'; ?>

    <div class="flex-grow-1 p-4" style="height: 100vh; overflow-y: auto;">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>👨‍💼 Lista de Empleados</h2>
            
            <button class="btn btn-success shadow" data-bs-toggle="modal" data-bs-target="#newEmployeeModal">
                <i class="bi bi-plus-circle"></i> Nuevo Empleado
            </button>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body py-3">
                <form action="" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="c" value="Employee">
                    <div class="col-auto"><label class="col-form-label fw-bold">Buscar:</label></div>
                    <div class="col-md-5"><input type="text" name="q" class="form-control" placeholder="Nombre, Código o Cargo..." value="<?php echo isset($_GET['q']) ? $_GET['q'] : ''; ?>"></div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button>
                        <?php if(isset($_GET['q']) && $_GET['q'] != ''): ?><a href="?c=Employee" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                Operación realizada con éxito. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-4">Estado</th><th>Nombre Completo</th><th>Departamento</th><th>Cargo</th><th class="text-end pe-4">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if(isset($employees) && count($employees) > 0): ?>
                            <?php foreach($employees as $emp): ?>
                            <tr class="<?php echo ($emp['status'] == 'inactivo') ? 'table-inactive' : ''; ?>">
                                <td class="ps-4"><?php echo ($emp['status'] == 'activo') ? '<span class="badge bg-success">ACTIVO</span>' : '<span class="badge bg-danger">INACTIVO</span>'; ?></td>
                                <td><div class="fw-bold"><?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?></div><small class="text-muted"><?php echo $emp['employee_code']; ?></small></td>
                                <td><?php echo $emp['department_name']; ?></td>
                                <td><?php echo $emp['position']; ?></td>
                                <td class="text-end pe-4">
                                    <?php if($emp['status'] == 'activo'): ?>
                                        <button class="btn btn-sm btn-info text-white shadow-sm" onclick="verCarnet('<?php echo $emp['first_name']; ?>', '<?php echo $emp['employee_code']; ?>', '<?php echo $emp['position']; ?>')"><i class="bi bi-qr-code"></i></button>
                                    <?php endif; ?>
                                    <a href="?c=Employee&a=edit&id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-warning shadow-sm"><i class="bi bi-pencil-fill"></i></a>
                                    <?php if($emp['status'] == 'activo'): ?>
                                        <a href="?c=Employee&a=toggle&id=<?php echo $emp['id']; ?>&status=activo" class="btn btn-sm btn-outline-danger shadow-sm" onclick="confirmarAccion(event, this.href, '¿Desactivar?', 'No podrá marcar.', 'warning')"><i class="bi bi-power"></i></a>
                                    <?php else: ?>
                                        <a href="?c=Employee&a=toggle&id=<?php echo $emp['id']; ?>&status=inactivo" class="btn btn-sm btn-outline-success shadow-sm" onclick="confirmarAccion(event, this.href, '¿Reactivar?', 'Podrá marcar.', 'success')"><i class="bi bi-power"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-5 text-muted">No hay empleados registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newEmployeeModal" tabindex="-1">
  <div class="modal-dialog modal-lg"> <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill"></i> Nuevo Empleado</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      
      <form action="?c=Employee&a=store" method="POST">
          <div class="modal-body">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nombres</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Apellidos</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Código Empleado (DNI/ID)</label>
                    <input type="text" name="employee_code" class="form-control" required placeholder="Ej: EMP005">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Departamento</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Cargo / Puesto</label>
                    <input type="text" name="position" class="form-control" required>
                </div>
                
                <div class="col-12 mt-4">
                    <div class="alert alert-info mb-0 small">
                        <i class="bi bi-info-circle"></i> La contraseña por defecto será: <strong>123456</strong>
                    </div>
                </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success fw-bold">Guardar Empleado</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="carnetModal" tabindex="-1">
  <div class="modal-dialog modal-sm"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Carnet Digital</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><div id="printableArea"><img src="" id="qrImage" class="img-fluid mb-3 border rounded p-1 shadow-sm" style="width: 180px;"><h4 id="carnetName" class="fw-bold mb-1 text-dark"></h4><p id="carnetPos" class="text-muted mb-2 text-uppercase small fw-bold"></p><div class="mt-2"><span id="carnetCode" class="badge bg-dark fs-6 font-monospace px-3 py-2"></span></div></div></div><div class="modal-footer justify-content-center bg-light"><button type="button" class="btn btn-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmarAccion(event, url, titulo, mensaje, icono) {
        event.preventDefault();
        Swal.fire({ title: titulo, text: mensaje, icon: icono, showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Si', cancelButtonText: 'No' }).then((r) => { if (r.isConfirmed) window.location.href = url; })
    }
    function verCarnet(n, c, p) {
        document.getElementById('qrImage').src = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" + c;
        document.getElementById('carnetName').innerText = n; document.getElementById('carnetCode').innerText = c; document.getElementById('carnetPos').innerText = p;
        new bootstrap.Modal(document.getElementById('carnetModal')).show();
    }
</script>
</body>
</html>