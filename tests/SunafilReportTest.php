<?php
require_once __DIR__ . '/../app/services/SunafilReportService.php';
require_once __DIR__ . '/../app/services/SimpleXlsxWriter.php';

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) throw new RuntimeException($message . ': esperado ' . var_export($expected, true) . ', recibido ' . var_export($actual, true));
}

$service = new SunafilReportService();
$normal = ['id'=>1, 'employee_code'=>'12345678', 'first_name'=>'Ana', 'last_name'=>'Pérez', 'site_name'=>'Lima', 'schedule_name'=>'Día', 'schedule_entry_time'=>'08:00', 'schedule_check_out_time'=>'18:00'];
$cases = [
    [['check_in_time'=>'07:30:00','check_out_time'=>'18:45:00'], '00:30', '00:45', '01:15', ''],
    [['check_in_time'=>'08:15:00','check_out_time'=>'17:30:00'], '00:00', '00:00', '00:00', ''],
    [['check_in_time'=>null,'check_out_time'=>'18:00:00'], '00:00', '00:00', '00:00', 'SIN MARCACIÓN DE INGRESO.'],
    [['check_in_time'=>'08:00:00','check_out_time'=>null], '00:00', '00:00', '00:00', 'SIN MARCACIÓN DE SALIDA.'],
];
foreach ($cases as $case) {
    $row = $service->makeRow($normal, '2026-09-01', $case[0]);
    assertSameValue($case[1], $row['before'], 'permanencia anterior');
    assertSameValue($case[2], $row['after'], 'permanencia posterior');
    assertSameValue($case[3], $row['outside_total'], 'permanencia total');
    assertSameValue($case[4], $row['observation'], 'observación');
}

$night = $normal;
$night['schedule_entry_time'] = '22:00';
$night['schedule_check_out_time'] = '06:00';
$nightRow = $service->makeRow($night, '2026-09-01', ['check_in_time'=>'21:45:00','check_out_time'=>'06:30:00']);
assertSameValue('00:15', $nightRow['before'], 'turno nocturno anterior');
assertSameValue('00:30', $nightRow['after'], 'turno nocturno posterior');

$rows = $service->buildRows([$normal], [], '2026-09-01', '2026-09-03');
assertSameValue(3, count($rows), 'rango inclusivo sin marcaciones');
assertSameValue('INASISTENCIA.', $rows[0]['observation'], 'inasistencia');

$rows = $service->buildRows([$normal], [
    ['id'=>1,'employee_id'=>1,'date_log'=>'2026-09-01','check_in_time'=>'08:00:00','check_out_time'=>'18:00:00'],
    ['id'=>2,'employee_id'=>1,'date_log'=>'2026-09-01','check_in_time'=>'08:05:00','check_out_time'=>'18:05:00'],
], '2026-09-01', '2026-09-01');
assertSameValue(2, count($rows), 'varias marcaciones no se fusionan');

$logo = tempnam(sys_get_temp_dir(), 'logo_test_');
file_put_contents($logo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
$file = tempnam(sys_get_temp_dir(), 'xlsx_test_');
(new SimpleXlsxWriter())->save($rows, [
    'business_name'=>'Empresa de Prueba S.A.C.', 'trade_name'=>'Empresa', 'ruc'=>'20123456789',
    'fiscal_address'=>'Av. Principal 123', 'site'=>'Sede Lima', 'address'=>'Jr. Trabajo 456',
    'period'=>'01/09/2026 al 01/09/2026', 'logo_path'=>$logo,
], $file);
$zip = new ZipArchive();
assertSameValue(true, $zip->open($file) === true, 'xlsx abre como ZIP');
foreach (['[Content_Types].xml','xl/workbook.xml','xl/styles.xml','xl/worksheets/sheet1.xml','xl/drawings/drawing1.xml'] as $part) {
    $xml = $zip->getFromName($part);
    assertSameValue(true, $xml !== false && simplexml_load_string($xml) !== false, 'OOXML válido: ' . $part);
}
assertSameValue(true, strpos($zip->getFromName('xl/workbook.xml'), 'Reporte SUNAFIL') !== false, 'nombre de hoja');
assertSameValue(true, strpos($zip->getFromName('xl/worksheets/sheet1.xml'), 'Empresa de Prueba S.A.C.') !== false, 'datos empresariales');
assertSameValue(true, $zip->getFromName('xl/media/company-logo.png') !== false, 'logo incorporado');
$zip->close();
unlink($file);
unlink($logo);
echo "SunafilReportTest: OK\n";
