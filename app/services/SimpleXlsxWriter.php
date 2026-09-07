<?php

/** Escritor OOXML mínimo para no incorporar una segunda dependencia de exportación. */
class SimpleXlsxWriter {
    public function save(array $rows, array $metadata, $path) {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($rows, $metadata));
        $zip->addFromString('docProps/core.xml', $this->coreProperties());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->close();
    }

    private function sheet(array $rows, array $meta) {
        $headers = ['N.°', 'Fecha', 'Número de documento', 'Apellidos y nombres del trabajador', 'Hora de ingreso', 'Hora de salida', 'Hora programada de ingreso', 'Hora programada de salida', 'Nombre del turno', 'Permanencia antes de la jornada', 'Permanencia después de la jornada', 'Total de permanencia fuera de la jornada', 'Observación'];
        $lines = [];
        $lines[] = $this->row(1, [['REGISTRO DE CONTROL DE ASISTENCIA', 1]], 28);
        $labels = [
            'Razón social del empleador' => $meta['business_name'] ?? '', 'RUC del empleador' => $meta['ruc'] ?? '',
            'Sede o centro de trabajo' => $meta['site'] ?? '', 'Dirección de la sede' => $meta['address'] ?? '',
            'Periodo consultado' => $meta['period'] ?? '', 'Fecha y hora de generación' => $meta['generated_at'] ?? '',
            'Horario o turno' => $meta['schedule'] ?? '', 'Tiempo de refrigerio' => $meta['break_time'] ?? '',
        ];
        $r = 2;
        foreach ($labels as $label => $value) $lines[] = $this->row($r++, [[$label, 2], [$value, 0]], 20);
        $headerRow = $r;
        $headerCells = [];
        foreach ($headers as $header) $headerCells[] = [$header, 3];
        $lines[] = $this->row($r++, $headerCells, 42);
        foreach ($rows as $index => $data) {
            $name = trim($data['last_name'] . ', ' . $data['first_name'], ', ');
            $values = [$index + 1, date('d/m/Y', strtotime($data['date'])), $data['document'], $name,
                $this->hhmm($data['check_in']), $this->hhmm($data['check_out']), $this->hhmm($data['scheduled_entry']),
                $this->hhmm($data['scheduled_exit']), $data['schedule_name'], $data['before'], $data['after'],
                $data['outside_total'], $data['observation']];
            $cells = [];
            foreach ($values as $i => $value) $cells[] = [$value, in_array($i, [1,4,5,6,7,9,10,11], true) ? 5 : 4];
            $lines[] = $this->row($r++, $cells, 24);
        }
        $last = max($headerRow, $r - 1);
        $cols = [7,13,20,34,13,13,18,18,22,21,21,25,30];
        $columnXml = '';
        foreach ($cols as $i => $width) $columnXml .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:M'.$last.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow+1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/><cols>'.$columnXml.'</cols><sheetData>'.implode('', $lines).'</sheetData>'
            . '<mergeCells count="9"><mergeCell ref="A1:M1"/><mergeCell ref="B2:M2"/><mergeCell ref="B3:M3"/><mergeCell ref="B4:M4"/><mergeCell ref="B5:M5"/><mergeCell ref="B6:M6"/><mergeCell ref="B7:M7"/><mergeCell ref="B8:M8"/><mergeCell ref="B9:M9"/></mergeCells>'
            . '<autoFilter ref="A'.$headerRow.':M'.$last.'"/><printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/><headerFooter><oddFooter>&amp;C Página &amp;P de &amp;N</oddFooter></headerFooter>'
            . '</worksheet>';
    }

    private function row($number, array $cells, $height) {
        $xml = '<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
        foreach ($cells as $i => $cell) {
            $ref = $this->column($i + 1) . $number;
            $xml .= '<c r="'.$ref.'" s="'.$cell[1].'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($cell[0]).'</t></is></c>';
        }
        return $xml . '</row>';
    }

    private function column($number) { $name=''; while ($number) { $number--; $name=chr(65+$number%26).$name; $number=intdiv($number,26); } return $name; }
    private function hhmm($time) { return $time ? substr($time, 0, 5) : ''; }
    private function escape($value) { return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function contentTypes() { return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>'; }
    private function rootRelationships() { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>'; }
    private function workbook() { return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reporte SUNAFIL" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Reporte SUNAFIL\'!$10:$10</definedName></definedNames></workbook>'; }
    private function workbookRelationships() { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'; }
    private function styles() { return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF198754"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyFont="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs></styleSheet>'; }
    private function coreProperties() { $now=gmdate('Y-m-d\TH:i:s\Z'); return '<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Reporte SUNAFIL</dc:title><dc:creator>Sistema de Asistencia</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created></cp:coreProperties>'; }
    private function appProperties() { return '<?xml version="1.0" encoding="UTF-8"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Sistema de Asistencia</Application></Properties>'; }
}
