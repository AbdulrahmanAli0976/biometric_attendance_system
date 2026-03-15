<?php
declare(strict_types=1);

function parse_device_csv(string $csvText): array
{
    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', trim($csvText));
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

function normalize_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'd/m/Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime && $dt->format($format) === $value) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function normalize_time(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['H:i:s', 'H:i'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('H:i:s');
        }
    }

    return null;
}

function import_device_logs(PDO $pdo, array $rows, ?int $defaultDeviceId, array &$errors, int &$inserted, int &$skipped): void
{
    $inserted = 0;
    $skipped = 0;

    if (empty($rows)) {
        $errors[] = 'No rows found in the input.';
        return;
    }

    $header = array_map('strtolower', array_map('trim', $rows[0]));
    $hasHeader = in_array('device_user_id', $header, true) && in_array('scan_date', $header, true) && in_array('scan_time', $header, true);
    $startIndex = $hasHeader ? 1 : 0;

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO attendance_logs (device_user_id, scan_date, scan_time, device_id) VALUES (?, ?, ?, ?)'
    );

    $pdo->beginTransaction();
    try {
        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];

            if ($hasHeader) {
                $assoc = array_combine($header, array_pad($row, count($header), ''));
                $deviceUserId = trim((string) ($assoc['device_user_id'] ?? ''));
                $date = normalize_date((string) ($assoc['scan_date'] ?? ''));
                $time = normalize_time((string) ($assoc['scan_time'] ?? ''));
                $deviceId = isset($assoc['device_id']) && trim((string) $assoc['device_id']) !== '' ? (int) $assoc['device_id'] : $defaultDeviceId;
            } else {
                $deviceUserId = trim((string) ($row[0] ?? ''));
                $date = normalize_date((string) ($row[1] ?? ''));
                $time = normalize_time((string) ($row[2] ?? ''));
                $deviceId = isset($row[3]) && trim((string) $row[3]) !== '' ? (int) $row[3] : $defaultDeviceId;
            }

            if ($deviceUserId === '' || $date === null || $time === null) {
                $errors[] = 'Row ' . ($i + 1) . ' skipped due to invalid data.';
                $skipped++;
                continue;
            }

            $stmt->execute([$deviceUserId, $date, $time, $deviceId]);
            if ($stmt->rowCount() === 1) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Import failed: ' . $e->getMessage();
    }
}
