<?php
declare(strict_types=1);

function process_attendance(PDO $pdo, string $startDate, string $endDate, array &$summary, array &$errors): void
{
    $summary = [
        'groups' => 0,
        'inserted_or_updated' => 0,
        'skipped' => 0,
    ];
    $errors = [];

    $startDate = trim($startDate);
    $endDate = trim($endDate);
    if ($startDate === '' || $endDate === '') {
        $errors[] = 'Start and end dates are required.';
        return;
    }

    if ($startDate > $endDate) {
        $errors[] = 'Start date cannot be after end date.';
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT l.device_user_id, l.scan_date, MIN(l.scan_time) AS first_scan, MAX(l.scan_time) AS last_scan, s.staff_id '
        . 'FROM attendance_logs l '
        . 'JOIN staff s ON s.device_user_id = l.device_user_id '
        . 'WHERE l.scan_date BETWEEN ? AND ? '
        . 'GROUP BY l.device_user_id, l.scan_date, s.staff_id '
        . 'ORDER BY l.scan_date ASC'
    );

    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        $errors[] = 'No logs found for the selected date range.';
        return;
    }

    $upsert = $pdo->prepare(
        'INSERT INTO attendance (staff_id, date, check_in, check_out, working_hours, status) '
        . 'VALUES (?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'check_in = VALUES(check_in), '
        . 'check_out = VALUES(check_out), '
        . 'working_hours = VALUES(working_hours), '
        . 'status = VALUES(status)'
    );

    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $summary['groups']++;
            $staffId = (int) $row['staff_id'];
            if ($staffId <= 0) {
                $summary['skipped']++;
                continue;
            }

            $checkIn = (string) $row['first_scan'];
            $lastScan = (string) $row['last_scan'];
            $checkOut = ($lastScan !== $checkIn) ? $lastScan : null;

            $workingHours = null;
            if ($checkOut !== null) {
                $startTs = strtotime($row['scan_date'] . ' ' . $checkIn);
                $endTs = strtotime($row['scan_date'] . ' ' . $checkOut);
                if ($startTs !== false && $endTs !== false && $endTs >= $startTs) {
                    $hours = ($endTs - $startTs) / 3600;
                    $workingHours = round($hours, 2);
                }
            }

            $status = ($checkIn > '08:30:00') ? 'Late' : 'Present';

            $upsert->execute([
                $staffId,
                $row['scan_date'],
                $checkIn,
                $checkOut,
                $workingHours,
                $status,
            ]);
            $summary['inserted_or_updated']++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Processing failed: ' . $e->getMessage();
    }
}
