<?php

namespace App\Controllers;

use DateTime;
use DateTimeZone;
use DateInterval;
use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class LeaveController extends BaseController
{
    use ResponseTrait;
    protected $db;
    protected $kolkataTimezone;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->kolkataTimezone = new DateTimeZone('Asia/Kolkata');
    }

    /**
     * Add Leave Application
     * Handles PAID, WFH, and UNPAID leaves
     */
    public function add_leave()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $created_by = $decodedToken->user_id ?? null;

        // Generate unique leave form code
        $leave_form_code = 'LEAVE' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

        // Handle both JSON and FormData inputs
        $input = [];
        if ($this->request->getHeaderLine('Content-Type') === 'application/json') {
            $input = $this->request->getJSON(true);
        } else {
            $input = $this->request->getPost();
            if (isset($input['total_days'])) {
                $input['total_days'] = floatval($input['total_days']);
            }
        }

        // Validation rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_ref_code' => 'required|max_length[100]',
            'leave_type' => 'required|in_list[PAID,WFH,UNPAID]',
            'leave_duration' => 'required|in_list[FULL,HALF]',
            'start_date' => 'required|valid_date',
            'end_date' => 'permit_empty|valid_date',
            'reason' => 'required|max_length[500]',
            'total_days' => 'required|decimal'
        ]);

        if (!$validation->run($input)) {
            return $this->respond([
                'status' => false,
                'message' => $validation->getErrors()
            ], 400);
        }

        $this->db->transBegin();

        try {
            $userRefCode = $input['user_ref_code'] ?? $created_by;
            $leaveType = strtoupper($input['leave_type'] ?? '');
            $leaveDuration = strtoupper($input['leave_duration'] ?? '');
            $startDate = $input['start_date'] ?? '';
            $endDate = $leaveDuration === 'HALF' ? $input['start_date'] : ($input['end_date'] ?? $input['start_date']);
            $reason = $input['reason'] ?? '';
            $totalDays = floatval($input['total_days'] ?? 0);

            // Check if user exists
            $userExists = $this->db->table('tbl_register')
                ->where('user_code', $userRefCode)
                ->where('is_active', 'Y')
                ->countAllResults();

            if (!$userExists) {
                return $this->respond([
                    'status' => false,
                    'message' => 'User not found or inactive'
                ], 404);
            }

            // Get user details
            $user = $this->db->table('tbl_register')
                ->select('joining_date, role_ref_code')
                ->where('user_code', $userRefCode)
                ->get()
                ->getRow();

            if (!$user) {
                return $this->respond([
                    'status' => false,
                    'message' => 'User details not found'
                ], 404);
            }

            $isAdmin = ($user->role_ref_code === 'ADM_3w7');

            // For PAID leaves, check earned leaves balance with monthly carry forward rules
            if ($leaveType === 'PAID') {
                $currentDate = new DateTime('now', $this->kolkataTimezone);
                $leaveDate = new DateTime($startDate, $this->kolkataTimezone);

                // Get available balance for the leave month
                $paidLeaveBalance = $this->getAvailablePaidLeavesForMonth($userRefCode, $leaveDate);

                if ($paidLeaveBalance < $totalDays) {
                    return $this->respond([
                        'status' => false,
                        'message' => 'Insufficient paid leave balance for this month. Available: ' . $paidLeaveBalance . ' days'
                    ], 400);
                }
            }

            // For unpaid leaves, calculate salary deduction
            $isUnpaid = ($leaveType === 'UNPAID');
            $salaryDeductionAmount = 0;

            if ($isUnpaid) {
                $salaryDeductionAmount = $this->calculateSalaryDeduction($userRefCode, $totalDays);
            }

            // Determine status
            $status = 'PENDING';
            $approvedBy = null;
            $approvalDate = null;

            // WFH is auto-approved
            if ($leaveType === 'WFH') {
                $status = 'AUTO_APPROVED';
                $approvedBy = 'SYSTEM';
                $approvalDate = (new DateTime('now', $this->kolkataTimezone))->format('Y-m-d H:i:s');
            }

            // Prepare leave data
            $data = [
                'leave_form_code' => $leave_form_code,
                'user_ref_code' => $userRefCode,
                'leave_code' => $leaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'reason' => $reason,
                'leave_duration' => $leaveDuration,
                'half_day' => $leaveDuration === 'HALF' ? 'Y' : 'N',
                'status' => $status,
                'approver_ref_code' => $approvedBy,
                'approval_date' => $approvalDate,
                'active_status' => 'Y',
                'created_by' => $created_by,
                'created_at' => (new DateTime('now', $this->kolkataTimezone))->format('Y-m-d H:i:s')
            ];

            // Insert leave application
            $insertResult = $this->db->table('tbl_leave_forms')->insert($data);

            if (!$insertResult) {
                $error = $this->db->error();
                throw new \Exception('Failed to insert leave application: ' . $error['message']);
            }

            // For unpaid leaves, create salary deduction record
            if ($isUnpaid && $salaryDeductionAmount > 0) {
                $deductionData = [
                    'user_ref_code' => $userRefCode,
                    'leave_form_code' => $leave_form_code,
                    'deduction_type' => 'UNPAID_LEAVE',
                    'amount' => $salaryDeductionAmount,
                    'deduction_date' => $startDate,
                    'description' => 'Unpaid leave deduction for ' . $totalDays . ' days',
                    'created_at' => (new DateTime('now', $this->kolkataTimezone))->format('Y-m-d H:i:s'),
                    'created_by' => $created_by
                ];

                $this->db->table('tbl_salary_deductions')->insert($deductionData);
            }

            if ($this->db->transStatus() === FALSE) {
                $this->db->transRollback();
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to submit leave application'
                ], 500);
            }

            $this->db->transCommit();

            return $this->respond([
                'status' => true,
                'message' => $status === 'AUTO_APPROVED' ?
                    'Work From Home request auto-approved' :
                    'Leave application submitted successfully',
                'leave_form_code' => $leave_form_code,
                'auto_approved' => $status === 'AUTO_APPROVED'
            ], 201);

        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond([
                'status' => false,
                'message' => 'Failed to submit leave application',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function getAvailablePaidLeavesForMonth($userRefCode, $requestDate)
    {
        // Get user details
        $user = $this->db->table('tbl_register')
            ->select('joining_date, role_ref_code')
            ->where('user_code', $userRefCode)
            ->get()
            ->getRow();

        if (!$user) {
            return 0;
        }

        $isAdmin = ($user->role_ref_code === 'ADM_3w7');

        // Calculate using Kolkata timezone
        $joiningDate = new DateTime($user->joining_date . ' 00:00:00', $this->kolkataTimezone);

        // Calculate probation status at request date
        $probationEndDate = (clone $joiningDate)->add(new DateInterval('P6M'));
        $inProbationAtRequest = ($requestDate < $probationEndDate) && !$isAdmin;

        if ($inProbationAtRequest) {
            return 0; // No paid leaves during probation
        }

        // Get request year and month
        $requestYear = (int) $requestDate->format('Y');
        $requestMonth = (int) $requestDate->format('m');

        // Calculate total months worked AFTER probation up to the request month
        $startFromDate = ($probationEndDate > $joiningDate) ? clone $probationEndDate : clone $joiningDate;
        $monthsEarned = 0;

        // Count months from probation end to request date
        $currentCheckDate = clone $startFromDate;
        while (
            $currentCheckDate <= $requestDate &&
            $currentCheckDate->format('Y') == $requestYear
        ) {
            $monthsEarned++;
            $currentCheckDate->add(new DateInterval('P1M'));
        }

        // For admin, get fixed yearly allocation
        if ($isAdmin) {
            $monthsEarned = 12; // Admin gets 12 per year
        }

        // Get paid leaves taken in the SAME YEAR up to request month
        $startOfYear = $requestYear . '-01-01';
        $endOfRequestMonth = $requestYear . '-' . str_pad($requestMonth, 2, '0', STR_PAD_LEFT) . '-31';

        $paidTakenResult = $this->db->table('tbl_leave_forms')
            ->selectSum('total_days', 'total_paid')
            ->where('user_ref_code', $userRefCode)
            ->where('leave_code', 'PAID')
            ->where('status', 'APPROVED')
            ->where('start_date >=', $startOfYear)
            ->where('start_date <=', $endOfRequestMonth)
            ->get()
            ->getRow();

        $paidTaken = floatval($paidTakenResult->total_paid ?? 0);

        // Calculate available leaves
        $availableLeaves = max(0, $monthsEarned - $paidTaken);

        return $availableLeaves;
    }
    private function checkMonthlyLeaveLimit($userRefCode, $requestDate, $requestedDays)
    {
        $requestDateTime = new DateTime($requestDate, $this->kolkataTimezone);
        $year = $requestDateTime->format('Y');
        $month = $requestDateTime->format('n');

        $monthlyLeaves = $this->getMonthlyLeavesTaken($userRefCode, $year, $month);

        if ($month == 1) {
            // January: Max 1 leave
            if (($monthlyLeaves + $requestedDays) > 1) {
                return [
                    'allowed' => false,
                    'message' => 'January limit exceeded. Maximum 1 paid leave allowed in January.'
                ];
            }
        } elseif ($month == 2) {
            // Check if leaves were taken in January
            $januaryLeaves = $this->getMonthlyLeavesTaken($userRefCode, $year, 1);

            if ($januaryLeaves == 0) {
                // No leaves in January, can take 2 in February
                if (($monthlyLeaves + $requestedDays) > 2) {
                    return [
                        'allowed' => false,
                        'message' => 'February limit exceeded. Maximum 2 paid leaves allowed in February if no leave was taken in January.'
                    ];
                }
            } else {
                // Leaves were taken in January, max 1 in February
                if (($monthlyLeaves + $requestedDays) > 1) {
                    return [
                        'allowed' => false,
                        'message' => 'February limit exceeded. Maximum 1 paid leave allowed in February if leave was taken in January.'
                    ];
                }
            }
        }

        return ['allowed' => true, 'message' => ''];
    }

    private function getPaidLeaveBalanceWithNewRules($userRefCode, $currentDate, $requestDate)
    {
        // Get user details
        $user = $this->db->table('tbl_register')
            ->select('joining_date, role_ref_code')
            ->where('user_code', $userRefCode)
            ->get()
            ->getRow();

        if (!$user) {
            return 0;
        }

        $isAdmin = ($user->role_ref_code === 'ADM_3w7');

        // Calculate using Kolkata timezone
        $joiningDate = new DateTime($user->joining_date . ' 00:00:00', $this->kolkataTimezone);
        $probationEndDate = (clone $joiningDate)->add(new DateInterval('P6M'));

        $inProbation = ($currentDate < $probationEndDate) && !$isAdmin;

        // Calculate earned paid leaves
        $earnedPaidLeaves = 0;
        if (!$inProbation) {
            $monthsSinceJoining = $this->calculateMonthsDifference($joiningDate, $currentDate);
            $monthsAfterProbation = max(0, $monthsSinceJoining - 6);
            $earnedPaidLeaves = $monthsAfterProbation; // 1 per month after probation

            // For admin, give 12 paid leaves
            if ($isAdmin) {
                $earnedPaidLeaves = 12;
            }
        }

        // Get current year
        $currentYear = $currentDate->format('Y');
        $requestYear = (new DateTime($requestDate, $this->kolkataTimezone))->format('Y');

        // Get paid leaves taken in the SAME YEAR as the request
        $paidResult = $this->db->table('tbl_leave_forms')
            ->selectSum('total_days', 'total_paid')
            ->where('user_ref_code', $userRefCode)
            ->where('leave_code', 'PAID')
            ->where('status', 'APPROVED')
            ->where('YEAR(start_date)', $requestYear) // Only count leaves from the same year
            ->get()
            ->getRow();

        $paidTaken = floatval($paidResult->total_paid ?? 0);

        // Calculate balance for the requested year
        $yearlyBalance = max(0, $earnedPaidLeaves - $paidTaken);

        // Apply monthly limits based on the request date
        $requestDateTime = new DateTime($requestDate, $this->kolkataTimezone);
        $requestMonth = $requestDateTime->format('n'); // 1 for January, 2 for February

        if ($requestMonth == 1) {
            // January: Check how many leaves already taken in January
            $januaryLeaves = $this->getMonthlyLeavesTaken($userRefCode, $requestYear, 1);
            $januaryLimit = 1;

            // Available leaves in January = min(yearly balance, January limit - leaves already taken)
            $availableInJanuary = min($yearlyBalance, $januaryLimit - $januaryLeaves);
            return max(0, $availableInJanuary);

        } elseif ($requestMonth == 2) {
            // February: Check if leaves were taken in January
            $januaryLeaves = $this->getMonthlyLeavesTaken($userRefCode, $requestYear, 1);
            $februaryLeaves = $this->getMonthlyLeavesTaken($userRefCode, $requestYear, 2);

            if ($januaryLeaves == 0) {
                // No leaves taken in January, can take 2 in February
                $februaryLimit = 2;
                $availableInFebruary = min($yearlyBalance, $februaryLimit - $februaryLeaves);
                return max(0, $availableInFebruary);
            } else {
                // Leaves were taken in January, normal balance applies
                $februaryLimit = 1; // Only 1 if leaves were taken in January
                $availableInFebruary = min($yearlyBalance, $februaryLimit - $februaryLeaves);
                return max(0, $availableInFebruary);
            }
        }

        // For other months, return the yearly balance
        return $yearlyBalance;
    }

    private function getMonthlyLeavesTaken($userRefCode, $year, $month)
    {
        $result = $this->db->table('tbl_leave_forms')
            ->selectSum('total_days', 'monthly_total')
            ->where('user_ref_code', $userRefCode)
            ->where('leave_code', 'PAID')
            ->where('status', 'APPROVED')
            ->where('YEAR(start_date)', $year)
            ->where('MONTH(start_date)', $month)
            ->get()
            ->getRow();

        return floatval($result->monthly_total ?? 0);
    }

    /**
     * Get Employee Leave Information
     */
    public function getEmployeeLeaveInfo()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $user_ref_code = $decodedToken->user_id;

        // Also check if user_ref_code is passed in request body
        if ($this->request->getMethod() === 'post') {
            $input = $this->request->getJSON(true);
            if (isset($input['user_ref_code'])) {
                $user_ref_code = $input['user_ref_code'];
            }
        }

        try {
            // Get user details
            $user = $this->db->table('tbl_register')
                ->select('joining_date, role_ref_code, first_name, last_name')
                ->where('user_code', $user_ref_code)
                ->where('is_active', 'Y')
                ->get()
                ->getRow();

            if (!$user) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $isAdmin = ($user->role_ref_code === 'ADM_3w7');
            $joiningDate = new DateTime($user->joining_date . ' 00:00:00', $this->kolkataTimezone);
            $currentDate = new DateTime('now', $this->kolkataTimezone);

            // Calculate probation period
            $probationEndDate = (clone $joiningDate)->add(new DateInterval('P6M'));
            $inProbation = ($currentDate < $probationEndDate) && !$isAdmin;

            // Get current year and month
            $currentYear = (int) $currentDate->format('Y');
            $currentMonth = (int) $currentDate->format('m');

            // Calculate total earned paid leaves for current year up to current month
            $monthsEarnedThisYear = 0;

            if (!$inProbation) {
                // Calculate from January of current year or from probation end date
                $startDate = max(
                    new DateTime($currentYear . '-01-01', $this->kolkataTimezone),
                    $probationEndDate
                );

                $monthCheck = clone $startDate;
                while (
                    $monthCheck <= $currentDate &&
                    $monthCheck->format('Y') == $currentYear
                ) {
                    $monthsEarnedThisYear++;
                    $monthCheck->add(new DateInterval('P1M'));
                }

                if ($isAdmin) {
                    $monthsEarnedThisYear = 12; // Admin gets 12 per year
                }
            }

            // Get paid leaves taken in current year
            $paidResult = $this->db->table('tbl_leave_forms')
                ->selectSum('total_days', 'total_paid')
                ->where('user_ref_code', $user_ref_code)
                ->where('leave_code', 'PAID')
                ->where('status', 'APPROVED')
                ->where('YEAR(start_date)', $currentYear)
                ->get()
                ->getRow();

            $paidTaken = floatval($paidResult->total_paid ?? 0);

            // Calculate available paid leaves (can carry forward from previous months)
            $paidBalance = max(0, $monthsEarnedThisYear - $paidTaken);

            // Get monthly breakdown
            $monthlyBreakdown = $this->getMonthlyLeaveBreakdown($user_ref_code, $currentYear, $currentMonth, $isAdmin, $probationEndDate);

            // Get paid leaves pending
            $paidPendingResult = $this->db->table('tbl_leave_forms')
                ->selectSum('total_days', 'total_paid_pending')
                ->where('user_ref_code', $user_ref_code)
                ->where('leave_code', 'PAID')
                ->where('status', 'PENDING')
                ->get()
                ->getRow();

            $paidPending = floatval($paidPendingResult->total_paid_pending ?? 0);

            // Get other leave types
            $wfhResult = $this->db->table('tbl_leave_forms')
                ->selectSum('total_days', 'total_wfh')
                ->where('user_ref_code', $user_ref_code)
                ->where('leave_code', 'WFH')
                ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
                ->get()
                ->getRow();

            $wfhTaken = floatval($wfhResult->total_wfh ?? 0);

            $wfhPendingResult = $this->db->table('tbl_leave_forms')
                ->selectSum('total_days', 'total_wfh_pending')
                ->where('user_ref_code', $user_ref_code)
                ->where('leave_code', 'WFH')
                ->where('status', 'PENDING')
                ->get()
                ->getRow();

            $wfhPending = floatval($wfhPendingResult->total_wfh_pending ?? 0);

            $unpaidResult = $this->db->table('tbl_leave_forms')
                ->selectSum('total_days', 'total_unpaid')
                ->where('user_ref_code', $user_ref_code)
                ->where('leave_code', 'UNPAID')
                ->where('status', 'APPROVED')
                ->get()
                ->getRow();

            $unpaidTaken = floatval($unpaidResult->total_unpaid ?? 0);

            $unpaidPendingResult = $this->db->table('tbl_leave_forms')
                ->selectSum('total_days', 'total_unpaid_pending')
                ->where('user_ref_code', $user_ref_code)
                ->where('leave_code', 'UNPAID')
                ->where('status', 'PENDING')
                ->get()
                ->getRow();

            $unpaidPending = floatval($unpaidPendingResult->total_unpaid_pending ?? 0);

            return $this->respond([
                'status' => true,
                'data' => [
                    'in_probation' => $inProbation,
                    'joining_date' => $user->joining_date,
                    'probation_end_date' => $probationEndDate->format('Y-m-d'),
                    'months_earned_this_year' => $monthsEarnedThisYear,
                    'paid_leaves_taken' => $paidTaken,
                    'paid_leaves_pending' => $paidPending,
                    'paid_leaves_balance' => $paidBalance,
                    'wfh_taken_total' => $wfhTaken,
                    'wfh_pending' => $wfhPending,
                    'unpaid_taken_total' => $unpaidTaken,
                    'unpaid_pending' => $unpaidPending,
                    'can_apply_paid' => !$inProbation || $isAdmin,
                    'can_apply_wfh' => true,
                    'can_apply_unpaid' => true,
                    'employee_name' => trim($user->first_name . ' ' . $user->last_name),
                    'monthly_breakdown' => $monthlyBreakdown,
                    'carry_forward_info' => [
                        'monthly_carry_forward' => true,
                        'yearly_carry_forward' => false,
                        'message' => 'Unused paid leaves carry forward to next month(s) within same year. No carry forward to next year.',
                        'cannot_take_future_leaves' => 'You can only use leaves earned up to current month'
                    ],
                    'current_year' => $currentYear,
                    'current_month' => $currentMonth,
                    'leave_policy' => [
                        'probation_period_months' => 6,
                        'paid_leaves_per_month' => 1,
                        'admin_paid_leaves_per_year' => 12,
                        'monthly_carry_forward' => true,
                        'yearly_reset' => true,
                        'no_future_leaves' => true,
                        'description' => 'Earn 1 paid leave per month after probation. Unused leaves carry forward within same year. Yearly reset with no carry forward to next year.'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error fetching leave information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getMonthlyLeaveBreakdown($userRefCode, $year, $currentMonth, $isAdmin, $probationEndDate)
    {
        $breakdown = [];

        // For admin, all months have 1 leave
        $monthlyAllocation = $isAdmin ? 1 : 0;

        for ($month = 1; $month <= $currentMonth; $month++) {
            $monthDate = new DateTime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01', $this->kolkataTimezone);

            // Check if month is after probation for non-admin
            if (!$isAdmin) {
                $monthlyAllocation = ($monthDate >= $probationEndDate) ? 1 : 0;
            }

            // Get leaves taken in this month
            $monthStart = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));

            $takenResult = $this->db->table('tbl_leave_forms')
                ->selectSum('total_days', 'monthly_taken')
                ->where('user_ref_code', $userRefCode)
                ->where('leave_code', 'PAID')
                ->where('status', 'APPROVED')
                ->where('start_date >=', $monthStart)
                ->where('start_date <=', $monthEnd)
                ->get()
                ->getRow();

            $taken = floatval($takenResult->monthly_taken ?? 0);

            // Calculate accumulated balance (carry forward from previous months)
            $accumulatedBalance = 0;
            if ($month == 1) {
                $accumulatedBalance = $monthlyAllocation - $taken;
            } else {
                // Get previous month's accumulated balance
                $prevMonthBalance = isset($breakdown[$month - 2]['accumulated_balance']) ? $breakdown[$month - 2]['accumulated_balance'] : 0;
                $accumulatedBalance = $prevMonthBalance + $monthlyAllocation - $taken;
            }

            $breakdown[] = [
                'month' => $month,
                'month_name' => date('F', strtotime($year . '-' . $month . '-01')),
                'allocation' => $monthlyAllocation,
                'taken' => $taken,
                'accumulated_balance' => max(0, $accumulatedBalance),
                'available_now' => max(0, $accumulatedBalance),
                'after_probation' => $isAdmin || ($monthDate >= $probationEndDate)
            ];
        }

        return $breakdown;
    }

    private function getYearlyLeaves($userRefCode, $year, $leaveType)
    {
        $result = $this->db->table('tbl_leave_forms')
            ->selectSum('total_days', 'yearly_total')
            ->where('user_ref_code', $userRefCode)
            ->where('leave_code', $leaveType)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->where('YEAR(start_date)', $year)
            ->get()
            ->getRow();

        return floatval($result->yearly_total ?? 0);
    }


    /**
     * Get Paid Leave Balance
     */
    private function getPaidLeaveBalance($userRefCode, $currentDate)
    {
        return $this->getAvailablePaidLeavesForMonth($userRefCode, $currentDate);
    }
    /**
     * Calculate months difference between two dates
     */
    private function calculateMonthsDifference($startDate, $endDate)
    {
        $start = clone $startDate;
        $end = clone $endDate;

        $yearDiff = $end->format('Y') - $start->format('Y');
        $monthDiff = $end->format('m') - $start->format('m');
        $dayDiff = $end->format('d') - $start->format('d');

        $totalMonths = ($yearDiff * 12) + $monthDiff;

        if ($dayDiff < 0) {
            $totalMonths--;
        }

        return max(0, $totalMonths);
    }

    /**
     * Calculate salary deduction for unpaid leave
     */
    private function calculateSalaryDeduction($userRefCode, $days)
    {
        $salary = $this->db->table('tbl_salary_details')
            ->select('basic_salary')
            ->where('user_ref_code', $userRefCode)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getRow();

        if ($salary && $salary->basic_salary > 0) {
            $dailySalary = $salary->basic_salary / 22;
            return $dailySalary * $days;
        }

        return 0;
    }

    /**
     * Approve/Reject Leave
     */
    public function approveLeave()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $created_by = $decodedToken->user_id ?? null;

        // Get JSON input
        $input = $this->request->getJSON(true);

        // If not JSON, try to get POST data
        if (empty($input)) {
            $input = $this->request->getPost();
        }

        $validation = \Config\Services::validation();
        $validation->setRules([
            'leave_form_code' => 'required|max_length[100]',
            'status' => 'required|in_list[APPROVED,REJECTED]'
        ]);

        if (!$validation->run($input)) {
            return $this->respond([
                'status' => false,
                'message' => $validation->getErrors()
            ], 400);
        }

        $leave_form_code = $input['leave_form_code'] ?? null;
        $status = strtoupper($input['status'] ?? '');

        $this->db->transStart();

        try {
            // Get leave application
            $leave = $this->db->table('tbl_leave_forms')
                ->where('leave_form_code', $leave_form_code)
                ->get()
                ->getRow();

            if (!$leave) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Leave application not found'
                ], 404);
            }

            $userRefCode = $leave->user_ref_code;
            $leaveType = $leave->leave_code;
            $totalDays = floatval($leave->total_days);

            // For PAID leave approval, check balance
            if ($status === 'APPROVED' && $leaveType === 'PAID') {
                $currentDate = new DateTime('now', $this->kolkataTimezone);
                $paidBalance = $this->getPaidLeaveBalance($userRefCode, $currentDate);

                if ($paidBalance < $totalDays) {
                    return $this->respond([
                        'status' => false,
                        'message' => 'Insufficient paid leave balance. Available: ' . $paidBalance . ' days'
                    ], 400);
                }
            }

            // Update leave status
            $updateData = [
                'status' => $status,
                'approver_ref_code' => $created_by,
                'approval_date' => (new DateTime('now', $this->kolkataTimezone))->format('Y-m-d H:i:s'),
                'updated_at' => (new DateTime('now', $this->kolkataTimezone))->format('Y-m-d H:i:s'),
                'updated_by' => $created_by
            ];

            $this->db->table('tbl_leave_forms')
                ->where('leave_form_code', $leave_form_code)
                ->update($updateData);

            $this->db->transComplete();

            return $this->respond([
                'status' => true,
                'message' => "Leave $status successfully"
            ]);

        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond([
                'status' => false,
                'message' => 'Error processing leave approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get All Leaves (Admin View)
     */
    public function getallleave()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        try {
            $builder = $this->db->table('tbl_leave_forms lf');
            $result = $builder
                ->select('lf.*, r.first_name, r.last_name, r.joining_date, r.role_ref_code')
                ->join('tbl_register r', 'lf.user_ref_code = r.user_code', 'left')
                ->orderBy('lf.created_at', 'DESC')
                ->get()
                ->getResult();

            // Calculate earned leaves for each leave
            foreach ($result as &$leave) {
                if (!empty($leave->attachment)) {
                    $leave->attachment = base_url('leave_attachments/' . $leave->attachment);
                }

                // Calculate probation status at leave date
                if ($leave->joining_date) {
                    $joiningDate = new DateTime($leave->joining_date . ' 00:00:00', $this->kolkataTimezone);
                    $leaveDate = new DateTime($leave->start_date . ' 00:00:00', $this->kolkataTimezone);

                    $probationEndDate = (clone $joiningDate)->add(new DateInterval('P6M'));
                    $inProbationAtLeave = ($leaveDate < $probationEndDate) && ($leave->role_ref_code !== 'ADM_3w7');

                    $leave->in_probation_at_leave = $inProbationAtLeave;
                    $leave->employee_name = trim($leave->first_name . ' ' . $leave->last_name);
                }
            }

            return $this->respond([
                'status' => true,
                'message' => 'Leave requests fetched successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Leaves for specific user
     */
    public function getallleaveforuser()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $user_code = $decodedToken->user_id ?? null;
        if (!$user_code) {
            return $this->respond([
                'status' => false,
                'message' => 'User code missing in token'
            ], 400);
        }

        try {
            $result = $this->db->table('tbl_leave_forms lf')
                ->select('lf.*, r.first_name, r.last_name, r.joining_date')
                ->join('tbl_register r', 'lf.user_ref_code = r.user_code', 'left')
                ->where('lf.user_ref_code', $user_code)
                ->orderBy('lf.created_at', 'DESC')
                ->get()
                ->getResult();

            foreach ($result as &$leave) {
                if (!empty($leave->attachment)) {
                    $leave->attachment = base_url('leave_attachments/' . $leave->attachment);
                }

                $leave->employee_name = trim($leave->first_name . ' ' . $leave->last_name);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Leave requests fetched successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Leave Applications for HOD
     */
    public function getleaveapplicationforhod()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $hodCode = $decodedToken->user_id ?? null;
        if (!$hodCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User code not found in token'
            ], 400);
        }

        // Verify user is HOD
        $hod = $this->db->table('tbl_register')
            ->select('user_code, first_name, last_name')
            ->where('user_code', $hodCode)
            ->where('Designations', 'DESGCPL003')
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$hod) {
            return $this->respond([
                'status' => false,
                'message' => 'User is not an HOD'
            ]);
        }

        // Get team members under this HOD
        $teamMembers = $this->db->table('tbl_register')
            ->select('user_code')
            ->where('hod_ref_code', $hodCode)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        if (empty($teamMembers)) {
            return $this->respond([
                'status' => true,
                'message' => 'No team members found under this HOD',
                'hod_name' => trim(($hod['first_name'] ?? '') . ' ' . ($hod['last_name'] ?? '')),
                'data' => []
            ]);
        }

        $userCodes = array_column($teamMembers, 'user_code');

        // Get leave applications
        $leaveBuilder = $this->db->table('tbl_leave_forms l');
        $leaveBuilder->select('l.*, r.first_name, r.last_name, r.joining_date');
        $leaveBuilder->join('tbl_register r', 'r.user_code = l.user_ref_code', 'left');
        $leaveBuilder->whereIn('l.user_ref_code', $userCodes);
        $leaveBuilder->orderBy('l.created_at', 'DESC');
        $leaveApplications = $leaveBuilder->get()->getResultArray();

        if (empty($leaveApplications)) {
            return $this->respond([
                'status' => true,
                'message' => 'No leave applications found',
                'hod_name' => trim(($hod['first_name'] ?? '') . ' ' . ($hod['last_name'] ?? '')),
                'data' => []
            ]);
        }

        foreach ($leaveApplications as &$leave) {
            $leave['employee_name'] = trim(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? ''));
        }

        return $this->respond([
            'status' => true,
            'message' => 'Leave applications fetched successfully',
            'hod_name' => trim(($hod['first_name'] ?? '') . ' ' . ($hod['last_name'] ?? '')),
            'data' => $leaveApplications
        ]);
    }

    /**
     * Get Today's Leave Employees
     */
    public function gettodayleaveemployee()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        // Use Kolkata timezone for today's date
        $today = (new DateTime('now', $this->kolkataTimezone))->format('Y-m-d');

        try {
            $builder = $this->db->table('tbl_leave_forms lf');

            $builder->select('lf.*, r.first_name, r.last_name')
                ->join('tbl_register r', 'lf.user_ref_code = r.user_code', 'left')
                ->where('lf.start_date <=', $today)
                ->where('lf.end_date >=', $today)
                ->where('lf.status', 'APPROVED')
                ->orderBy('lf.created_at', 'DESC');

            $result = $builder->get()->getResult();

            foreach ($result as &$leave) {
                if (!empty($leave->attachment)) {
                    $leave->attachment = base_url('leave_attachments/' . $leave->attachment);
                }

                $leave->employee_name = trim($leave->first_name . ' ' . $leave->last_name);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Today\'s leave employees fetched successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Leave Summary for Dashboard
     */
    public function getLeaveSummary()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $user_ref_code = $decodedToken->user_id;

        try {
            // Get current date in Kolkata timezone
            $currentDate = new DateTime('now', $this->kolkataTimezone);
            $currentYear = $currentDate->format('Y');
            $currentMonth = $currentDate->format('m');

            // Get leave stats for current month
            $monthStart = $currentYear . '-' . $currentMonth . '-01';
            $monthEnd = $currentDate->format('Y-m-t');

            $monthlyStats = $this->db->table('tbl_leave_forms')
                ->select('leave_code, status, SUM(total_days) as total_days')
                ->where('user_ref_code', $user_ref_code)
                ->where('start_date >=', $monthStart)
                ->where('start_date <=', $monthEnd)
                ->groupBy('leave_code, status')
                ->get()
                ->getResultArray();

            // Get upcoming leaves (next 30 days)
            $next30Days = (clone $currentDate)->add(new DateInterval('P30D'))->format('Y-m-d');

            $upcomingLeaves = $this->db->table('tbl_leave_forms')
                ->select('*')
                ->where('user_ref_code', $user_ref_code)
                ->where('start_date >=', $currentDate->format('Y-m-d'))
                ->where('start_date <=', $next30Days)
                ->whereIn('status', ['APPROVED', 'AUTO_APPROVED', 'PENDING'])
                ->orderBy('start_date', 'ASC')
                ->get()
                ->getResultArray();

            return $this->respond([
                'status' => true,
                'data' => [
                    'monthly_stats' => $monthlyStats,
                    'upcoming_leaves' => $upcomingLeaves,
                    'current_month' => $currentDate->format('F Y'),
                    'timezone' => 'Asia/Kolkata'
                ]
            ]);

        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error fetching leave summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getuserattendance()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_time');
        $builder->where('user_ref_code', $userCode);
        $query = $builder->get();
        $data = $query->getResult();

        return $this->respond([
            'status' => true,
            'message' => 'attendance data fetched successfully',
            'data' => $data
        ]);
    }

    public function getMonthlyAttendance()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $request = $this->request->getJSON(true);
        $monthYear = $request['month_year'] ?? '';

        // ✅ Validate month format
        if (empty($monthYear) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing month_year format. Use YYYY-MM'
            ], 400);
        }

        list($year, $month) = explode('-', $monthYear);
        $startDate = "{$year}-{$month}-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        $db = \Config\Database::connect();

        // ✅ Get holidays
        $holidayDates = [];
        $holidayQuery = $db->table('tbl_holiday_mst')
            ->select('holiday_date')
            ->where('holiday_date >=', $startDate)
            ->where('holiday_date <=', $endDate)
            ->get()
            ->getResult();

        foreach ($holidayQuery as $h) {
            $holidayDates[] = $h->holiday_date;
        }

        // ✅ Get all employees
        $employees = $db->table('tbl_login')
            ->select('user_code_ref as user_ref_code, user_name')
            ->get()
            ->getResult();

        // ✅ Build all days of month (holiday/working)
        $allDays = [];
        $current = strtotime($startDate);
        $last = strtotime($endDate);

        while ($current <= $last) {
            $dateStr = date('Y-m-d', $current);
            $dayNum = date('j', $current);
            $dayName = date('l', $current);
            $isHoliday = false;

            if ($dayName == 'Sunday') {
                $isHoliday = true;
            }
            if ($dayName == 'Saturday') {
                $weekNum = ceil($dayNum / 7);
                if ($weekNum == 1 || $weekNum == 3) {
                    $isHoliday = true;
                }
            }
            if (in_array($dateStr, $holidayDates)) {
                $isHoliday = true;
            }

            $allDays[$dateStr] = $isHoliday ? 'holiday' : 'working';
            $current = strtotime("+1 day", $current);
        }

        // ✅ Get attendance with punch_in & punch_out
        $attendanceQuery = $db->table('tbl_time')
            ->select('user_ref_code, today_date, punch_in, punch_out')
            ->where('today_date >=', $startDate)
            ->where('today_date <=', $endDate)
            ->get()
            ->getResult();

        $attendanceMap = [];
        foreach ($attendanceQuery as $row) {
            $attendanceMap[$row->user_ref_code][$row->today_date] = [
                'punch_in' => $row->punch_in,
                'punch_out' => $row->punch_out
            ];
        }

        // ✅ Prepare final result
        $result = [];
        foreach ($employees as $emp) {
            $totalDays = count($allDays);
            $holidayCnt = count(array_filter($allDays, fn($v) => $v === 'holiday'));
            $workingDays = $totalDays - $holidayCnt;

            $presentCnt = 0;
            $dailyLogs = [];

            foreach ($allDays as $dateStr => $type) {
                $dayLog = [
                    'date' => $dateStr,
                    'status' => $type,
                    'punch_in' => null,
                    'punch_out' => null
                ];

                if ($type === 'working' && isset($attendanceMap[$emp->user_ref_code][$dateStr])) {
                    $presentCnt++;
                    $dayLog['status'] = 'present';
                    $dayLog['punch_in'] = $attendanceMap[$emp->user_ref_code][$dateStr]['punch_in'];
                    $dayLog['punch_out'] = $attendanceMap[$emp->user_ref_code][$dateStr]['punch_out'];
                } elseif ($type === 'working') {
                    $dayLog['status'] = 'absent';
                }

                $dailyLogs[] = $dayLog;
            }

            $absentCnt = $workingDays - $presentCnt;

            $result[] = [
                'user_ref_code' => $emp->user_ref_code,
                'user_name' => $emp->user_name,
                'total_days' => $totalDays,
                'holidays' => $holidayCnt,
                'working_days' => $workingDays,
                'present' => $presentCnt,
                'absent' => $absentCnt,
                'attendance_log' => $dailyLogs   // ✅ includes punch_in & punch_out
            ];
        }

        return $this->respond([
            'status' => true,
            'message' => 'Monthly attendance fetched successfully',
            'data' => $result
        ]);
    }

    public function getMonthlyAttendanceforuser()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }
        $request = $this->request->getJSON(true);
        $monthYear = $request['month_year'] ?? '';
        if (empty($monthYear) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing month_year format. Use YYYY-MM'
            ], 400);
        }
        list($year, $month) = explode('-', $monthYear);
        $startDate = "{$year}-{$month}-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_time');
        $builder->where('user_ref_code', $userCode);
        $builder->where('today_date >=', $startDate);
        $builder->where('today_date <=', $endDate);
        $query = $builder->get();
        $data = $query->getResult();
        return $this->respond([
            'status' => true,
            'message' => 'Attendance data fetched successfully',
            'data' => $data
        ]);
    }
}