<?php

class SunafilReportService {
    public function buildRows(array $employees, array $logs, $startDate, $endDate) {
        $logsByEmployeeAndDate = [];
        foreach ($logs as $log) {
            $logsByEmployeeAndDate[$log['employee_id']][$log['date_log']][] = $log;
        }

        $rows = [];
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        foreach ($employees as $employee) {
            for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
                $dateValue = $date->format('Y-m-d');
                $dayLogs = $logsByEmployeeAndDate[$employee['id']][$dateValue] ?? [];
                if (!$dayLogs) {
                    $rows[] = $this->makeRow($employee, $dateValue, null);
                    continue;
                }
                // Se conserva una fila por registro: no se fusionan ni alteran marcaciones originales.
                foreach ($dayLogs as $log) {
                    $rows[] = $this->makeRow($employee, $dateValue, $log);
                }
            }
        }
        usort($rows, function ($a, $b) {
            return [$a['date'], $a['last_name'], $a['first_name']] <=> [$b['date'], $b['last_name'], $b['first_name']];
        });
        return $rows;
    }

    public function makeRow(array $employee, $date, ?array $log) {
        $entry = $this->firstTime($employee['schedule_entry_time'] ?? null);
        $exit = $this->firstTime($employee['schedule_check_out_time'] ?? null);
        $checkIn = $this->normalizeTime($log['check_in_time'] ?? null);
        $checkOut = $this->normalizeTime($log['check_out_time'] ?? null);

        $scheduledStart = $entry ? new DateTimeImmutable($date . ' ' . $entry) : null;
        $scheduledEnd = $exit ? new DateTimeImmutable($date . ' ' . $exit) : null;
        if ($scheduledStart && $scheduledEnd && $scheduledEnd <= $scheduledStart) {
            $scheduledEnd = $scheduledEnd->modify('+1 day');
        }
        $actualStart = $checkIn ? new DateTimeImmutable($date . ' ' . $checkIn) : null;
        $actualEnd = $checkOut ? new DateTimeImmutable($date . ' ' . $checkOut) : null;
        if ($actualStart && $actualEnd && $actualEnd < $actualStart) {
            $actualEnd = $actualEnd->modify('+1 day');
        }

        $before = ($actualStart && $scheduledStart && $actualStart < $scheduledStart)
            ? (int)(($scheduledStart->getTimestamp() - $actualStart->getTimestamp()) / 60) : 0;
        $after = ($actualEnd && $scheduledEnd && $actualEnd > $scheduledEnd)
            ? (int)(($actualEnd->getTimestamp() - $scheduledEnd->getTimestamp()) / 60) : 0;

        return [
            'date' => $date,
            'document' => (string)$employee['employee_code'],
            'last_name' => (string)$employee['last_name'],
            'first_name' => (string)$employee['first_name'],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'scheduled_entry' => $entry,
            'scheduled_exit' => $exit,
            'schedule_name' => (string)($employee['schedule_name'] ?? ''),
            'before' => $this->formatMinutes($before),
            'after' => $this->formatMinutes($after),
            'outside_total' => $this->formatMinutes($before + $after),
            'observation' => $this->observation($log, $checkIn, $checkOut),
            'site_name' => (string)($employee['site_name'] ?? ''),
        ];
    }

    private function observation(?array $log, $checkIn, $checkOut) {
        if ($log === null) return 'INASISTENCIA.';
        if (!$checkIn && !$checkOut) return 'MARCACIÓN INCOMPLETA.';
        if (!$checkIn) return 'SIN MARCACIÓN DE INGRESO.';
        if (!$checkOut) return 'SIN MARCACIÓN DE SALIDA.';
        return '';
    }

    private function firstTime($value) {
        $parts = array_values(array_filter(array_map('trim', explode(',', (string)$value))));
        return isset($parts[0]) ? $this->normalizeTime($parts[0]) : null;
    }

    private function normalizeTime($value) {
        if ($value === null || $value === '') return null;
        $time = substr((string)$value, 0, 8);
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function formatMinutes($minutes) {
        return sprintf('%02d:%02d', intdiv((int)$minutes, 60), (int)$minutes % 60);
    }
}
