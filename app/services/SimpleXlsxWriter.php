<?php

/** Escritor OOXML mínimo para no incorporar una segunda dependencia de exportación. */
class SimpleXlsxWriter {
    public function save(array $rows, array $metadata, $path) {
        $logo = $this->logoInfo($metadata['logo_path'] ?? '');
        $parts = [
            '[Content_Types].xml' => $this->contentTypes($logo),
            '_rels/.rels' => $this->rootRelationships(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->sheet($rows, $metadata, $logo !== null),
            'docProps/core.xml' => $this->coreProperties(),
            'docProps/app.xml' => $this->appProperties(),
        ];
        if ($logo !== null) {
            $parts['xl/worksheets/_rels/sheet1.xml.rels'] = $this->sheetRelationships();
            $parts['xl/drawings/drawing1.xml'] = $this->drawing($logo);
            $parts['xl/drawings/_rels/drawing1.xml.rels'] = $this->drawingRelationships($logo['filename']);
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        foreach ($parts as $name => $xml) {
            $this->assertValidXml($name, $xml);
            if (!$zip->addFromString($name, $xml)) {
                $zip->close();
                throw new RuntimeException('No se pudo escribir la parte ' . $name . ' del archivo Excel.');
            }
        }
        if ($logo !== null && !$zip->addFile($logo['path'], 'xl/media/' . $logo['filename'])) {
            $zip->close();
            throw new RuntimeException('No se pudo incorporar el logo al archivo Excel.');
        }
        if (!$zip->close() || !is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('El archivo Excel no pudo finalizarse correctamente.');
        }
    }

    private function sheet(array $rows, array $meta, $hasLogo) {
        $headers = ['N.°', 'Fecha', 'Número de documento', 'Apellidos y nombres del trabajador', 'Hora de ingreso', 'Hora de salida', 'Hora programada de ingreso', 'Hora programada de salida', 'Nombre del turno', 'Permanencia antes de la jornada', 'Permanencia después de la jornada', 'Total de permanencia fuera de la jornada', 'Observación'];
        $lines = [];
        $lines[] = $this->row(1, [['', 0], ['', 0], ['REGISTRO DE CONTROL DE ASISTENCIA', 1]], 32);
        $labels = [
            'Razón social del empleador' => $meta['business_name'] ?? '', 'RUC del empleador' => $meta['ruc'] ?? '',
            'Nombre comercial' => $meta['trade_name'] ?? '', 'Domicilio fiscal' => $meta['fiscal_address'] ?? '',
            'Sede o centro de trabajo' => $meta['site'] ?? '', 'Dirección de la sede' => $meta['address'] ?? '',
            'Periodo consultado' => $meta['period'] ?? '', 'Fecha y hora de generación' => $meta['generated_at'] ?? '',
            'Horario o turno' => $meta['schedule'] ?? '', 'Tiempo de refrigerio' => $meta['break_time'] ?? '',
        ];
        $r = 2;
        foreach ($labels as $label => $value) $lines[] = $this->row($r++, [['', 0], ['', 0], [$label, 2], [$value, 0]], 20);
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
            // OOXML exige autoFilter antes de mergeCells. Un XML bien formado
            // pero con estos elementos invertidos hace que Excel descarte la hoja.
            . '<autoFilter ref="A'.$headerRow.':M'.$last.'"/>'
            . '<mergeCells count="11"><mergeCell ref="C1:M1"/><mergeCell ref="D2:M2"/><mergeCell ref="D3:M3"/><mergeCell ref="D4:M4"/><mergeCell ref="D5:M5"/><mergeCell ref="D6:M6"/><mergeCell ref="D7:M7"/><mergeCell ref="D8:M8"/><mergeCell ref="D9:M9"/><mergeCell ref="D10:M10"/><mergeCell ref="D11:M11"/></mergeCells>'
            . '<printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/><headerFooter><oddFooter>&amp;C Página &amp;P de &amp;N</oddFooter></headerFooter>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
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
    private function escape($value) {
        // Datos copiados desde lectores biométricos o campos libres pueden contener
        // bytes inválidos o caracteres de control que XML 1.0 no admite. Excel
        // repara esos archivos eliminando sheet1.xml y el reporte queda vacío.
        $escaped = htmlspecialchars(
            (string)$value,
            ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $escaped
        );
    }
    private function assertValidXml($name, $xml) {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $xml !== '' && simplexml_load_string($xml) !== false;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$valid) {
            throw new RuntimeException('La parte ' . $name . ' contiene XML inválido.');
        }
    }
    private function contentTypes($logo) { return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.($logo ? '<Default Extension="'.$logo['extension'].'" ContentType="'.$logo['mime'].'"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '').'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>'; }
    private function rootRelationships() { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>'; }
    private function workbook() { return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reporte SUNAFIL" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Reporte SUNAFIL\'!$12:$12</definedName></definedNames></workbook>'; }
    private function workbookRelationships() { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'; }
    private function styles() { return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF198754"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyFont="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs></styleSheet>'; }
    private function logoInfo($path) {
        if (!$path || !is_file($path)) return null;
        $image = @getimagesize($path);
        if (!$image || !in_array($image['mime'], ['image/png', 'image/jpeg'], true)) return null;
        $extension = $image['mime'] === 'image/png' ? 'png' : 'jpg';
        return ['path' => $path, 'filename' => 'company-logo.' . $extension, 'extension' => $extension, 'mime' => $image['mime']];
    }
    private function sheetRelationships() { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>'; }
    private function drawing($logo) { return '<?xml version="1.0" encoding="UTF-8"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><xdr:twoCellAnchor editAs="oneCell"><xdr:from><xdr:col>0</xdr:col><xdr:colOff>50000</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>50000</xdr:rowOff></xdr:from><xdr:to><xdr:col>2</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>5</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Logo de la empresa"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm/><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:twoCellAnchor></xdr:wsDr>'; }
    private function drawingRelationships($filename) { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$filename.'"/></Relationships>'; }
    private function coreProperties() { $now=gmdate('Y-m-d\TH:i:s\Z'); return '<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Reporte SUNAFIL</dc:title><dc:creator>Sistema de Asistencia</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created></cp:coreProperties>'; }
    private function appProperties() { return '<?xml version="1.0" encoding="UTF-8"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Sistema de Asistencia</Application></Properties>'; }
}
