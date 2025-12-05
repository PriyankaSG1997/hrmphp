<?php

namespace App\Models;

use CodeIgniter\Model;

class SalaryModel extends Model
{


    //     public function calculateSalary($data)
    // {
    //     $db = db_connect();
    //     $userCode = $data['user_ref_code'];
    //     $month = $data['month'];

    //     // ✅ Fetch salary details for this employee
    //     $salary = $db->table('tbl_salary_details')
    //         ->select('
    //             COALESCE(basic_salary, 0) as basic_salary,
    //             COALESCE(special_allowance, 0) as special_allowance,
    //             COALESCE(hra, 0) as hra,
    //             COALESCE(insurance, 0) as insurance,
    //             COALESCE(pt, 0) as pt,
    //             COALESCE(tds, 0) as tds
    //         ')
    //         ->where('user_ref_code', $userCode)
    //         ->get()
    //         ->getRowArray();

    //     if (!$salary) {
    //         return [
    //             'status' => false,
    //             'message' => 'Salary details not found for user ' . $userCode
    //         ];
    //     }

    //     // ✅ Calculate gross salary
    //     $grossSalary = floatval($salary['basic_salary'])
    //         + floatval($salary['special_allowance'])
    //         + floatval($salary['hra']);

    //     // ✅ Attendance Data
    //     $attendanceData = $this->getAttendanceData($userCode, $month);

    //     $totalDays = $attendanceData['total_working_days'];
    //     $leaves = $attendanceData['total_leaves'];
    //     $penaltyFraction = $attendanceData['total_penalty_fraction'];

    //     // ✅ Deductions (prefer request → fallback to DB values)
    //     $insurance = isset($data['insurance']) ? $data['insurance'] : $salary['insurance'];
    //     $pt        = isset($data['pt']) ? $data['pt'] : $salary['pt'];
    //     $tds       = isset($data['tds']) ? $data['tds'] : $salary['tds'];

    //     $perDaySalary = $totalDays > 0 ? ($grossSalary / $totalDays) : 0;
    //     $leaveDeduction = $perDaySalary * $leaves;
    //     $latePenaltyDeduction = $perDaySalary * $penaltyFraction;

    //     $totalDeductions = $leaveDeduction + $latePenaltyDeduction + $insurance + $pt + $tds;
    //     $netSalary = $grossSalary - $totalDeductions;

    //     return [
    //         'status' => true,
    //         'data' => [
    //             'gross_salary' => round($grossSalary, 2),
    //             'total_working_days' => $totalDays,
    //             'present_days' => $attendanceData['total_present_days'],
    //             'leaves' => $leaves,
    //             'late_penalties' => $attendanceData['late_penalties'],
    //             'penalty_fraction' => $penaltyFraction,
    //             'leave_deduction' => round($leaveDeduction, 2),
    //             'late_penalty_deduction' => round($latePenaltyDeduction, 2),
    //             'insurance' => $insurance,
    //             'pt' => $pt,
    //             'tds' => $tds,
    //             'total_deductions' => round($totalDeductions, 2),
    //             'net_salary' => round($netSalary, 2)
    //         ]
    //     ];
    // }
 public function calculateSalary($data)
{
    $db = db_connect();
    $userCode = $data['user_ref_code'];
    $month = $data['month'];

    // ✅ Get latest salary row per user
    $latestSalarySubquery = "
        SELECT s1.*
        FROM tbl_salary_details s1
        INNER JOIN (
            SELECT user_ref_code, MAX(created_at) AS max_created
            FROM tbl_salary_details
            GROUP BY user_ref_code
        ) s2 
        ON s1.user_ref_code = s2.user_ref_code 
        AND s1.created_at = s2.max_created
    ";

    $user = $db->table('tbl_register r')
        ->select('
            r.user_code, 
            r.first_name, 
            r.last_name,
            e.Gender,
            COALESCE(s.basic_salary, 0) as basic_salary, 
            COALESCE(s.special_allowance, 0) as special_allowance, 
            COALESCE(s.hra, 0) as hra,
            COALESCE(s.insurance, 0) as insurance,
            COALESCE(s.pt, 0) as pt,
            COALESCE(s.pf, 0) as pf,
            COALESCE(s.tds, 0) as tds
        ')
        ->join("($latestSalarySubquery) s", 's.user_ref_code = r.user_code', 'left')
        ->join('tbl_employee_details e', 'e.user_code_ref = r.user_code', 'left')
        ->where('r.user_code', $userCode)
        ->where('r.is_active', 'Y')
        ->get()
        ->getRowArray();

    if (!$user) {
        return [
            'status' => false,
            'message' => 'Employee or salary details not found for user ' . $userCode
        ];
    }

    // ✅ Rest of your salary calculation (same as before)
    $grossSalary = floatval($user['basic_salary'])
        + floatval($user['special_allowance'])
        + floatval($user['hra']);

    $attendanceData = $this->getAttendanceData($userCode, $month);
    $totalDays = $attendanceData['total_working_days'];
    $leaves = $attendanceData['total_leaves'];
    $penaltyFraction = $attendanceData['total_penalty_fraction'];

    $insurance = isset($data['insurance']) ? $data['insurance'] : $user['insurance'];
    $pf        = isset($data['pf']) ? $data['pf'] : $user['pf'];
    $tds       = isset($data['tds']) ? $data['tds'] : $user['tds'];

    $gender = strtolower($user['Gender'] ?? '');
    $monthNum = intval(date('m', strtotime($month . '-01')));
    $pt = 0;
    if ($monthNum === 2) {
        $pt = 300;
    } else {
        if ($grossSalary <= 7500) {
            $pt = 0;
        } elseif ($grossSalary > 7500 && $grossSalary < 10000) {
            $pt = ($gender === 'male') ? 175 : 0;
        } elseif ($grossSalary >= 10000 && $grossSalary <= 25000) {
            $pt = ($gender === 'male') ? 200 : 0;
        } elseif ($grossSalary > 25000) {
            $pt = 200;
        }
    }

    $perDaySalary = $totalDays > 0 ? ($grossSalary / $totalDays) : 0;
    $leaveDeduction = $perDaySalary * $leaves;
    $latePenaltyDeduction = $perDaySalary * $penaltyFraction;

    $totalDeductions = $leaveDeduction + $latePenaltyDeduction + $insurance + $pt + $pf + $tds;
    $netSalary = $grossSalary - $totalDeductions;

    return [
        'status' => true,
        'data' => [
            'user_code' => $user['user_code'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'basic_salary' => $user['basic_salary'],
            'special_allowance' => $user['special_allowance'],
            'hra' => $user['hra'],
            'gross_salary' => round($grossSalary, 2),
            'total_working_days' => $totalDays,
            'present_days' => $attendanceData['total_present_days'],
            'leaves' => $leaves,
            'late_penalties' => $attendanceData['late_penalties'],
            'penalty_fraction' => $penaltyFraction,
            'leave_deduction' => round($leaveDeduction, 2),
            'late_penalty_deduction' => round($latePenaltyDeduction, 2),
            'insurance' => $insurance,
            'pt' => $pt,
            'pf' => $pf,
            'tds' => $tds,
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($netSalary, 2)
        ]
    ];
}

    // public function calculateSalaryForAll($month, $extraDeductions = [])
    // {
    //     $db = db_connect();

    //     // Fetch all active employees with salary details
    //     $users = $db->table('tbl_register r')
    //         ->select('
    //         r.user_code, 
    //         r.first_name, 
    //         r.last_name,
    //         COALESCE(s.basic_salary, 0) as basic_salary, 
    //         COALESCE(s.special_allowance, 0) as special_allowance, 
    //         COALESCE(s.hra, 0) as hra,
    //         COALESCE(s.insurance, 0) as insurance,
    //         COALESCE(s.pt, 0) as pt,
    //         COALESCE(s.tds, 0) as tds
    //     ')
    //         ->join('tbl_salary_details s', 's.user_ref_code = r.user_code', 'left')
    //         ->where('r.is_active', 'Y')
    //         ->get()
    //         ->getResultArray();

    //     $results = [];

    //     foreach ($users as $user) {
    //         $userCode = $user['user_code'];

    //         // Calculate gross salary from components
    //         $grossSalary = floatval($user['basic_salary'])
    //             + floatval($user['special_allowance'])
    //             + floatval($user['hra']);

    //         // Attendance Data
    //         $attendanceData = $this->getAttendanceData($userCode, $month);

    //         $totalDays = $attendanceData['total_working_days'];
    //         $leaves = $attendanceData['total_leaves'];
    //         $penaltyFraction = $attendanceData['total_penalty_fraction'];

    //         // Deductions (from DB + optional overrides from API request)
    //         $insurance = $extraDeductions['insurance'] ?? $user['insurance'];
    //         $pt        = $extraDeductions['pt'] ?? $user['pt'];
    //         $tds       = $extraDeductions['tds'] ?? $user['tds'];

    //         $perDaySalary = $totalDays > 0 ? ($grossSalary / $totalDays) : 0;
    //         $leaveDeduction = $perDaySalary * $leaves;
    //         $latePenaltyDeduction = $perDaySalary * $penaltyFraction;

    //         $totalDeductions = $leaveDeduction + $latePenaltyDeduction + $insurance + $pt + $tds;
    //         $netSalary = $grossSalary - $totalDeductions;

    //         $results[] = [
    //             'user_code' => $userCode,
    //             'name' => $user['first_name'] . ' ' . $user['last_name'],
    //             'basic_salary' => $user['basic_salary'],
    //             'special_allowance' => $user['special_allowance'],
    //             'hra' => $user['hra'],
    //             'gross_salary' => round($grossSalary, 2),
    //             'total_working_days' => $totalDays,
    //             'present_days' => $attendanceData['total_present_days'],
    //             'leaves' => $leaves,
    //             'late_penalties' => $attendanceData['late_penalties'],
    //             'penalty_fraction' => $penaltyFraction,
    //             'leave_deduction' => round($leaveDeduction, 2),
    //             'late_penalty_deduction' => round($latePenaltyDeduction, 2),
    //             'insurance' => $insurance,
    //             'pt' => $pt,
    //             'tds' => $tds,
    //             'total_deductions' => round($totalDeductions, 2),
    //             'net_salary' => round($netSalary, 2)
    //         ];
    //     }

    //     return [
    //         'status' => true,
    //         'data' => $results
    //     ];
    // }
    public function calculateSalaryForAll($month)
    {
        $db = db_connect();
        $latestSalarySubquery = "
        SELECT s1.*
        FROM tbl_salary_details s1
        INNER JOIN (
            SELECT user_ref_code, MAX(created_at) AS max_created
            FROM tbl_salary_details
            GROUP BY user_ref_code
        ) s2 ON s1.user_ref_code = s2.user_ref_code AND s1.created_at = s2.max_created
    ";
        $users = $db->table('tbl_register r')
            ->select('
            r.user_code, 
            r.first_name, 
            r.last_name,
            e.Gender,
            COALESCE(s.basic_salary, 0) as basic_salary, 
            COALESCE(s.special_allowance, 0) as special_allowance, 
            COALESCE(s.hra, 0) as hra,
            COALESCE(s.insurance, 0) as insurance,
            COALESCE(s.pt, 0) as pt,  -- we will override this dynamically
            COALESCE(s.pf, 0) as pf,
            COALESCE(s.tds, 0) as tds -- TDS now comes directly from DB
        ')
            ->join("($latestSalarySubquery) s", 's.user_ref_code = r.user_code', 'left')
            ->join('tbl_employee_details e', 'e.user_code_ref = r.user_code', 'left')
            ->where('r.is_active', 'Y')
            ->get()
            ->getResultArray();
        $results = [];
        foreach ($users as $user) {
            $userCode = $user['user_code'];
            $grossSalary = floatval($user['basic_salary'])
                + floatval($user['special_allowance'])
                + floatval($user['hra']);
            $attendanceData = $this->getAttendanceData($userCode, $month);
            $totalDays = $attendanceData['total_working_days'];
            $leaves = $attendanceData['total_leaves'];
            $penaltyFraction = $attendanceData['total_penalty_fraction'];
            $insurance = floatval($user['insurance']);
            $pf        = floatval($user['pf']);
            $tds       = floatval($user['tds']);
            $gender = strtolower($user['Gender'] ?? '');
            $monthNum = intval(date('m', strtotime($month . '-01')));
            $pt = 0;
            if ($monthNum === 2) {
                $pt = 300;
            } else {
                if ($grossSalary <= 7500) {
                    $pt = 0;
                } elseif ($grossSalary > 7500 && $grossSalary < 10000) {
                    $pt = ($gender === 'male') ? 175 : 0;
                } elseif ($grossSalary >= 10000 && $grossSalary <= 25000) {
                    $pt = ($gender === 'male') ? 200 : 0;
                } elseif ($grossSalary > 25000) {
                    $pt = 200;
                }
            }
            $perDaySalary = $totalDays > 0 ? ($grossSalary / $totalDays) : 0;
            $leaveDeduction = $perDaySalary * $leaves;
            $latePenaltyDeduction = $perDaySalary * $penaltyFraction;
            $totalDeductions = $leaveDeduction + $latePenaltyDeduction + $insurance + $pt + $pf + $tds;
            $netSalary = $grossSalary - $totalDeductions;
            $results[] = [
                'user_code' => $userCode,
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'basic_salary' => $user['basic_salary'],
                'special_allowance' => $user['special_allowance'],
                'hra' => $user['hra'],
                'gross_salary' => round($grossSalary, 2),
                'total_working_days' => $totalDays,
                'present_days' => $attendanceData['total_present_days'],
                'leaves' => $leaves,
                'late_penalties' => $attendanceData['late_penalties'],
                'penalty_fraction' => $penaltyFraction,
                'leave_deduction' => round($leaveDeduction, 2),
                'late_penalty_deduction' => round($latePenaltyDeduction, 2),
                'insurance' => $insurance,
                'pt' => $pt,  
                'pf' => $pf,
                'tds' => $tds,
                'total_deductions' => round($totalDeductions, 2),
                'net_salary' => round($netSalary, 2)
            ];
        }
        return [
            'status' => true,
            'data' => $results
        ];
    }


// working function 
    // public function getAttendanceData($userCode, $month)
    // {
    //     $allDays = [];
    //     $presentDays = [];
    //     $latePenalties = [];
    //     $daysInMonth = date('t', strtotime($month . '-01'));
    //     $holidayDates = [];
    //     $holidayQuery = $this->db->table('tbl_holiday_mst')
    //         ->select('holiday_date')
    //         ->where('is_active', 'Y')
    //         ->where("DATE_FORMAT(holiday_date, '%Y-%m') =", $month)
    //         ->get();

    //     foreach ($holidayQuery->getResultArray() as $row) {
    //         $holidayDates[] = $row['holiday_date'];
    //     }
    //     for ($i = 1; $i <= $daysInMonth; $i++) {
    //         $date = sprintf('%s-%02d', $month, $i);
    //         $dayOfWeek = date('w', strtotime($date));
    //         $weekOfMonth = ceil($i / 7);
    //         if (
    //             $dayOfWeek == 0 ||
    //             ($dayOfWeek == 6 && in_array($weekOfMonth, [2, 4])) ||
    //             in_array($date, $holidayDates)
    //         ) {
    //             continue;
    //         }
    //         $allDays[] = $date;
    //         $attendanceQuery = $this->db->table('tbl_time')
    //             ->where('user_ref_code', $userCode)
    //             ->where('today_date', $date)
    //             ->where('is_active', 'Y')
    //             ->get();
    //         $attendance = $attendanceQuery->getRowArray();
    //         if ($attendance) {
    //             $presentDays[] = $date;
    //             $punchIn = strtotime($attendance['punch_in']);
    //             $late10_16 = strtotime("10:16:00");
    //             $late10_30 = strtotime("10:30:00");
    //             $late11 = strtotime("11:00:00");

    //             if ($punchIn >= $late10_16 && $punchIn < $late10_30) {
    //                 $latePenalties[$date] = 0.10;
    //             } elseif ($punchIn >= $late10_30 && $punchIn < $late11) {
    //                 $latePenalties[$date] = 0.25;
    //             } elseif ($punchIn >= $late11) {
    //                 $latePenalties[$date] = 0.50;
    //             }
    //         }
    //     }
    //     $totalWorkingDays = count($allDays);
    //     $totalPresentDays = count($presentDays);
    //     $totalLeaves = $totalWorkingDays - $totalPresentDays;
    //     $totalPenaltyFraction = array_sum($latePenalties);
    //     return [
    //         'total_working_days' => $totalWorkingDays,
    //         'total_present_days' => $totalPresentDays,
    //         'total_leaves' => $totalLeaves,
    //         'late_penalties' => $latePenalties,
    //         'total_penalty_fraction' => $totalPenaltyFraction
    //     ];
    // }
    public function getAttendanceData($userCode, $month)
{
    $allDays = [];
    $presentDays = [];
    $latePenalties = [];
    $daysInMonth = date('t', strtotime($month . '-01'));
    $holidayDates = [];

    // ✅ Get all holidays for the given month
    $holidayQuery = $this->db->table('tbl_holiday_mst')
        ->select('holiday_date')
        ->where('is_active', 'Y')
        ->where("DATE_FORMAT(holiday_date, '%Y-%m') =", $month)
        ->get();

    foreach ($holidayQuery->getResultArray() as $row) {
        $holidayDates[] = $row['holiday_date'];
    }

    // ✅ Preload all approved late punch-ins for this user (to skip deduction)
    $approvedLatePunchins = $this->db->table('tbl_mark_latepunchin')
        ->select('punch_in_date')
        ->where('user_code', $userCode)
        ->get()
        ->getResultArray();

    $approvedDates = array_column($approvedLatePunchins, 'punch_in_date');

    // ✅ Loop through all working days in month
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $date = sprintf('%s-%02d', $month, $i);
        $dayOfWeek = date('w', strtotime($date));
        $weekOfMonth = ceil($i / 7);

        // Skip Sundays, 2nd & 4th Saturdays, and holidays
        if (
            $dayOfWeek == 0 ||
            ($dayOfWeek == 6 && in_array($weekOfMonth, [2, 4])) ||
            in_array($date, $holidayDates)
        ) {
            continue;
        }

        $allDays[] = $date;

        // ✅ Get attendance for that date
        $attendanceQuery = $this->db->table('tbl_time')
            ->where('user_ref_code', $userCode)
            ->where('today_date', $date)
            ->where('is_active', 'Y')
            ->get();
        $attendance = $attendanceQuery->getRowArray();

        if ($attendance) {
            $presentDays[] = $date;

            // ✅ Skip penalty if approved in tbl_mark_latepunchin
            if (in_array($date, $approvedDates)) {
                continue;
            }

            // ✅ Calculate late penalty based on punch-in time
            $punchIn = strtotime($attendance['punch_in']);
            $late10_16 = strtotime("10:16:00");
            $late10_30 = strtotime("10:30:00");
            $late11 = strtotime("11:00:00");

            if ($punchIn >= $late10_16 && $punchIn < $late10_30) {
                $latePenalties[$date] = 0.10;
            } elseif ($punchIn >= $late10_30 && $punchIn < $late11) {
                $latePenalties[$date] = 0.25;
            } elseif ($punchIn >= $late11) {
                $latePenalties[$date] = 0.50;
            }
        }
    }

    // ✅ Calculate final totals
    $totalWorkingDays = count($allDays);
    $totalPresentDays = count($presentDays);
    $totalLeaves = $totalWorkingDays - $totalPresentDays;
    $totalPenaltyFraction = array_sum($latePenalties);

    return [
        'total_working_days' => $totalWorkingDays,
        'total_present_days' => $totalPresentDays,
        'total_leaves' => $totalLeaves,
        'late_penalties' => $latePenalties,
        'total_penalty_fraction' => $totalPenaltyFraction
    ];
}

    public function getAttendanceDataWithCount($userCode, $month)
    {
        $allDays = [];
        $presentDays = [];
        $latePenalties = [];
        $daysInMonth = date('t', strtotime($month . '-01'));
        $holidayDates = [];

        // 🔹 Get all holidays for this month
        $holidayQuery = $this->db->table('tbl_holiday_mst')
            ->select('holiday_date')
            ->where('is_active', 'Y')
            ->where("DATE_FORMAT(holiday_date, '%Y-%m') =", $month)
            ->get();

        foreach ($holidayQuery->getResultArray() as $row) {
            $holidayDates[] = $row['holiday_date'];
        }

        // 🔹 Late counters
        $lateCount_10_16 = 0;
        $lateCount_10_30 = 0;
        $lateCount_11 = 0;

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = sprintf('%s-%02d', $month, $i);
            $dayOfWeek = date('w', strtotime($date));
            $weekOfMonth = ceil($i / 7);

            // Skip Sundays, 2nd & 4th Saturdays, and holidays
            if (
                $dayOfWeek == 0 ||
                ($dayOfWeek == 6 && in_array($weekOfMonth, [2, 4])) ||
                in_array($date, $holidayDates)
            ) {
                continue;
            }

            $allDays[] = $date;

            // 🔹 Check attendance
            $attendanceQuery = $this->db->table('tbl_time')
                ->where('user_ref_code', $userCode)
                ->where('today_date', $date)
                ->where('is_active', 'Y')
                ->get();

            $attendance = $attendanceQuery->getRowArray();
            if ($attendance) {
                $presentDays[] = $date;
                $punchIn = strtotime($attendance['punch_in']);
                $late10_16 = strtotime("10:16:00");
                $late10_30 = strtotime("10:30:00");
                $late11 = strtotime("11:00:00");

                if ($punchIn >= $late10_16 && $punchIn < $late10_30) {
                    $latePenalties[$date] = 0.10;
                    $lateCount_10_16++;
                } elseif ($punchIn >= $late10_30 && $punchIn < $late11) {
                    $latePenalties[$date] = 0.25;
                    $lateCount_10_30++;
                } elseif ($punchIn >= $late11) {
                    $latePenalties[$date] = 0.50;
                    $lateCount_11++;
                }
            }
        }

        $totalWorkingDays = count($allDays);
        $totalPresentDays = count($presentDays);
        $totalLeaves = $totalWorkingDays - $totalPresentDays;
        $totalPenaltyFraction = array_sum($latePenalties);

        return [
            'total_working_days'       => $totalWorkingDays,
            'total_present_days'       => $totalPresentDays,
            'total_leaves'             => $totalLeaves,
            'late_penalties'           => $latePenalties,
            'total_penalty_fraction'   => $totalPenaltyFraction,
            'penalty_counts' => [
                'late_10_16_count' => $lateCount_10_16,
                'late_10_30_count' => $lateCount_10_30,
                'late_after_11_count' => $lateCount_11
            ]
        ];
    }
}
