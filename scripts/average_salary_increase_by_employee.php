<?php

$report_dir = dirname(__DIR__) . '/reports';

if (!file_exists($report_dir) && !mkdir($report_dir) && !is_dir($report_dir)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $report_dir));
}

$pdo = new PDO('mysql:host=localhost;dbname=employees;port=3333', 'root', 'test123');

$sql = <<<SQL
SELECT emp_no, salary, to_date, DATEDIFF(to_date, from_date) AS date_diff FROM salaries ORDER BY emp_no, from_date
SQL;

$stats = [];
$emp = [
    'emp_no'            => 0,
    'starting'          => 0,
    'salary_diff_total' => 0,
    'salary_total'      => 0,
    'interval_total'    => 0,
    'changes'           => 0,
];

$fp = fopen($report_dir . '/average_salary_increase_by_employee.csv', 'wb');
fputcsv($fp, ['Employee No', 'Starting', 'Ending', 'Average Increase', 'Average Salary', 'Average Interval', 'Changes']);

$salary = 0;
$last_salary = 0;
$last_emp_no = 0;

foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $key => $details) {
    $emp_no = (int) $details['emp_no'];
    $salary = (int) $details['salary'];
    $date_diff = (int) $details['date_diff'];

    if ($emp_no !== $last_emp_no) {
        if ($key !== 0) {
            $sub_one_changes = $emp['changes'] - 1;
            fputcsv($fp, [
                'emp_no'       => $last_emp_no,
                'starting'     => $emp['starting'],
                'ending'       => $last_salary,
                'avg_increase' => $sub_one_changes === 0 ? 0 : round($emp['salary_diff_total'] / $sub_one_changes),
                'avg_salary'   => round($emp['salary_total'] / $emp['changes']),
                'avg_interval' => $sub_one_changes === 0 ? 0 : round($emp['interval_total'] / $sub_one_changes),
                'changes'      => $emp['changes'],
            ]);
            $last_salary = 0;
        }

        // reset
        $emp = [
            'emp_no'            => $emp_no,
            'starting'          => $salary,
            'salary_diff_total' => 0,
            'salary_total'      => 0,
            'interval_total'    => 0,
            'changes'           => 0,
        ];
    }

    if ($last_salary > 0) {
        $emp['salary_diff_total'] += $salary - $last_salary;
    }
    if (strpos($details['to_date'], '9999') === false) {
        $emp['interval_total'] += $date_diff;
    }
    $emp['salary_total'] += $salary;
    $emp['changes']++;
    $last_salary = $salary;
    $last_emp_no = $emp_no;
}

if (!isset($stats[$last_emp_no])) {
    $sub_one_changes = $emp['changes'] - 1;
    fputcsv($fp, [
        'emp_no'       => $last_emp_no,
        'starting'     => $emp['starting'],
        'ending'       => $last_salary,
        'avg_increase' => $sub_one_changes === 0 ? 0 : round($emp['salary_diff_total'] / $sub_one_changes),
        'avg_salary'   => round($emp['salary_total'] / $emp['changes']),
        'avg_interval' => $sub_one_changes === 0 ? 0 : round($emp['interval_total'] / $sub_one_changes),
        'changes'      => $emp['changes'],
    ]);
}

fclose($fp);
