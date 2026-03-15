<?php
declare(strict_types=1);

function build_report_filters(array $input): array
{
    $filters = [
        'start_date' => $input['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
        'end_date' => $input['end_date'] ?? date('Y-m-d'),
        'department_id' => (int) ($input['department_id'] ?? 0),
        'staff_id' => (int) ($input['staff_id'] ?? 0),
        'status' => trim((string) ($input['status'] ?? '')),
    ];

    return $filters;
}

function fetch_attendance_report(PDO $pdo, array $filters): array
{
    $where = ['a.date BETWEEN ? AND ?'];
    $params = [$filters['start_date'], $filters['end_date']];

    if ($filters['department_id'] > 0) {
        $where[] = 's.department_id = ?';
        $params[] = $filters['department_id'];
    }

    if ($filters['staff_id'] > 0) {
        $where[] = 's.staff_id = ?';
        $params[] = $filters['staff_id'];
    }

    if ($filters['status'] !== '') {
        $where[] = 'a.status = ?';
        $params[] = $filters['status'];
    }

    $sql =
        'SELECT a.date, a.check_in, a.check_out, a.working_hours, a.status, '
        . 's.staff_id, s.name, s.email, s.phone, s.device_user_id, d.dept_name '
        . 'FROM attendance a '
        . 'JOIN staff s ON a.staff_id = s.staff_id '
        . 'LEFT JOIN departments d ON s.department_id = d.dept_id '
        . 'WHERE ' . implode(' AND ', $where) . ' '
        . 'ORDER BY a.date DESC, s.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_report_summary(PDO $pdo, array $filters): array
{
    $where = ['a.date BETWEEN ? AND ?'];
    $params = [$filters['start_date'], $filters['end_date']];

    if ($filters['department_id'] > 0) {
        $where[] = 's.department_id = ?';
        $params[] = $filters['department_id'];
    }

    if ($filters['staff_id'] > 0) {
        $where[] = 's.staff_id = ?';
        $params[] = $filters['staff_id'];
    }

    if ($filters['status'] !== '') {
        $where[] = 'a.status = ?';
        $params[] = $filters['status'];
    }

    $sql =
        'SELECT COUNT(*) AS total_records, '
        . 'SUM(a.working_hours) AS total_hours, '
        . 'SUM(CASE WHEN a.status = "Late" THEN 1 ELSE 0 END) AS late_count '
        . 'FROM attendance a '
        . 'JOIN staff s ON a.staff_id = s.staff_id '
        . 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return [
        'total_records' => (int) ($row['total_records'] ?? 0),
        'total_hours' => $row['total_hours'] !== null ? (float) $row['total_hours'] : 0.0,
        'late_count' => (int) ($row['late_count'] ?? 0),
    ];
}

function fetch_absences_for_date(PDO $pdo, string $date, int $departmentId, int $staffId): array
{
    $params = [$date];
    $where = 'WHERE a.attendance_id IS NULL';

    if ($departmentId > 0) {
        $where .= ' AND s.department_id = ?';
        $params[] = $departmentId;
    }

    if ($staffId > 0) {
        $where .= ' AND s.staff_id = ?';
        $params[] = $staffId;
    }

    $sql =
        'SELECT s.staff_id, s.name, d.dept_name '
        . 'FROM staff s '
        . 'LEFT JOIN departments d ON s.department_id = d.dept_id '
        . 'LEFT JOIN attendance a ON a.staff_id = s.staff_id AND a.date = ? '
        . $where . ' '
        . 'ORDER BY s.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function output_report_csv(array $records): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Name', 'Department', 'Email', 'Phone', 'Device User ID', 'Check In', 'Check Out', 'Working Hours', 'Status']);

    foreach ($records as $row) {
        fputcsv($out, [
            $row['date'],
            $row['name'],
            $row['dept_name'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['device_user_id'] ?? '',
            $row['check_in'] ?? '',
            $row['check_out'] ?? '',
            $row['working_hours'] !== null ? (string) $row['working_hours'] : '',
            $row['status'],
        ]);
    }

    fclose($out);
}
