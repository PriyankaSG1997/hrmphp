<?php

namespace App\Controllers;

use DateTime;
use CodeIgniter\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;
use Config\Services;
use App\Models\HomeModel;
use Config\Database;
use DateTimeZone;
use Config\App;
use Helper\jwtvalidate;

require_once ROOTPATH . 'public/JWT/src/JWT.php';
class LeaveController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    use ResponseTrait;

    public function getallleavetype()
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
            $this->db = \Config\Database::connect();
            $builder = $this->db->table('tbl_leave_mst');
            $builder->where('is_active', 'Y');
            $query = $builder->get();
            $result = $query->getResult();
            return $this->respond([
                'status' => true,
                'data'   => $result
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to fetch leave types',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // public function add_leave()
    // {
    //     helper('jwtvalidate');
    //     $authHeader = $this->request->getHeaderLine('Authorization');
    //     $decodedToken = validatejwt($authHeader);
    //     if (!$decodedToken) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Invalid or missing JWT'
    //         ], 401);
    //     }
    //     $created_by = $decodedToken->user_id ?? null;
    //     $input = $this->request->getPost();
    //     $leave_form_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    //     $data = [
    //         'leave_form_code'    => $leave_form_code,
    //         'user_ref_code'      => $input['user_ref_code'] ?? $created_by,
    //         'leave_code'         => $input['leave_code'] ?? null,
    //         'start_date'         => $input['start_date'] ?? null,
    //         'end_date'           => $input['end_date'] ?? null,
    //         'total_days'         => $input['total_days'] ?? null,
    //         'reason'             => $input['reason'] ?? '',
    //         'status'             => 'PENDING',
    //         'created_by'         => $created_by
    //     ];
    //     $attachmentFile = $this->request->getFile('attachment');
    //     if ($attachmentFile && $attachmentFile->isValid() && !$attachmentFile->hasMoved()) {
    //         $attachmentName = $leave_form_code . '_' . $attachmentFile->getRandomName();
    //         $attachmentFile->move(ROOTPATH . 'public/leave_attachments', $attachmentName);
    //         $data['attachment'] = 'leave_attachments/' . $attachmentName;
    //     }

    //     try {
    //         $db = \Config\Database::connect();
    //         $db->table('tbl_leave_forms')->insert($data);

    //         return $this->respond([
    //             'status' => true,
    //             'message' => 'Leave application submitted successfully',
    //             'leave_form_code' => $leave_form_code
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Failed to submit leave application',
    //             'error'   => $e->getMessage()
    //         ], 500);
    //     }
    // }


    // public function approveLeave()
    // {
    //     helper('jwtvalidate');
    //     $authHeader = $this->request->getHeaderLine('Authorization');
    //     $decodedToken = validatejwt($authHeader);
    //     if (!$decodedToken) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Invalid or missing JWT'
    //         ], 401);
    //     }
    //     $created_by = $decodedToken->user_id ?? null;
    //     $input = $this->request->getJSON(true);
    //     $leave_form_code     = $input['leave_form_code'] ?? null;
    //     $user_ref_code       = $input['user_ref_code'] ?? null;
    //     $status              = strtoupper($input['status'] ?? '');
    //     $leave_type          = strtoupper($input['leave_type'] ?? '');
    //     $year_start_end_date = $input['year_start_end_date'] ?? null;
    //     $start_date          = $input['start_date'] ?? null;
    //     $end_date            = $input['end_date'] ?? null;
    //     $total_days          = $input['total_days'] ?? null;
    //     if (!$leave_form_code || !$user_ref_code || !$leave_type || !$year_start_end_date || !$status || !$start_date || !$end_date) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Missing or invalid required fields.'
    //         ], 400);
    //     }
    //     $allowedLeaveTypes = ['CASUAL', 'MARRIAGE', 'MATERNITY', 'PATERNITY', 'SICK'];
    //     if (!in_array($leave_type, $allowedLeaveTypes)) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Invalid leave type.'
    //         ], 400);
    //     }
    //     $db = \Config\Database::connect();
    //     $now = (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    //     $newBalance = null;
    //     if ($status === 'APPROVED') {
    //         $builder = $db->table('tbl_emp_leaves_remains');
    //         $leaveData = $builder->where('user_ref_code', $user_ref_code)
    //             ->where('years_start_end_date', $year_start_end_date)
    //             ->get()
    //             ->getRowArray();
    //         if (!$leaveData) {
    //             return $this->respond([
    //                 'status' => false,
    //                 'message' => 'Leave data not found for user.'
    //             ], 404);
    //         }
    //         $currentBalance = (int)($leaveData[$leave_type] ?? 0);
    //         $holidayDates = array_column(
    //             $db->table('tbl_holiday_mst')
    //                 ->select('holiday_date')
    //                 ->where('is_active', 'Y')
    //                 ->where('holiday_date >=', $start_date)
    //                 ->where('holiday_date <=', $end_date)
    //                 ->get()
    //                 ->getResultArray(),
    //             'holiday_date'
    //         );
    //         $start = new \DateTime($start_date);
    //         $end   = new \DateTime($end_date);
    //         $end->modify('+1 day');
    //         $workingDays = 0;
    //         $interval = new \DatePeriod($start, new \DateInterval('P1D'), $end);
    //         foreach ($interval as $date) {
    //             $day = $date->format('w');
    //             $dayOfMonth = $date->format('j');
    //             $currentDateStr = $date->format('Y-m-d');
    //             $weekOfMonth = ceil($dayOfMonth / 7);

    //             $isSunday = $day == 0;
    //             $is2ndOr4thSaturday = ($day == 6) && ($weekOfMonth == 2 || $weekOfMonth == 4);
    //             $isHoliday = in_array($currentDateStr, $holidayDates);

    //             if (!$isSunday && !$is2ndOr4thSaturday && !$isHoliday) {
    //                 $workingDays++;
    //             }
    //         }
    //         if ($workingDays <= 0) {
    //             return $this->respond([
    //                 'status' => false,
    //                 'message' => 'No working (deductible) days found in the selected range.'
    //             ]);
    //         }
    //         if ($currentBalance < $workingDays) {
    //             return $this->respond([
    //                 'status' => false,
    //                 'message' => 'Insufficient leave balance.'
    //             ], 400);
    //         }
    //         $newBalance = $currentBalance - $workingDays;
    //         $updateData = [
    //             $leave_type  => $newBalance,
    //             'updated_at' => $now,
    //             'updated_by' => $created_by
    //         ];

    //         $builder->where('user_ref_code', $user_ref_code)
    //             ->where('years_start_end_date', $year_start_end_date)
    //             ->update($updateData);
    //     }
    //     $db->table('tbl_leave_forms')->where('leave_form_code', $leave_form_code)->update([
    //         'status'            => $status,
    //         'approver_ref_code' => $created_by,
    //         'start_date'       =>  $start_date,
    //         'end_date'        =>    $end_date,
    //         'total_days'      => $total_days,
    //         'approval_date'     => $now,
    //         'updated_at'        => $now,
    //         'updated_by'        => $created_by
    //     ]);

    //     return $this->respond([
    //         'status' => true,
    //         'message' => $status === 'APPROVED'
    //             ? "Leave approved. Remaining {$leave_type} leave: {$newBalance}"
    //             : "Leave status updated to {$status} (no leave balance deducted)"
    //     ]);
    // }




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
        $input = $this->request->getPost();
        $leave_form_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

        // ✅ Detect half-day leave
        $isHalfDay = isset($input['half_day']) && strtoupper($input['half_day']) === 'Y';

        $data = [
            'leave_form_code' => $leave_form_code,
            'user_ref_code'   => $input['user_ref_code'] ?? $created_by,
            'leave_code'      => $input['leave_code'] ?? null,
            'start_date'      => $input['start_date'] ?? null,
            'end_date'        => $input['end_date'] ?? null,
            'total_days'      => $isHalfDay ? 0.5 : ($input['total_days'] ?? null),
            'reason'          => $input['reason'] ?? '',
            'half_day'        => $isHalfDay ? 'Y' : 'N', // ✅ Store it in DB if you have this column
            'status'          => 'PENDING',
            'created_by'      => $created_by
        ];

        $attachmentFile = $this->request->getFile('attachment');
        if ($attachmentFile && $attachmentFile->isValid() && !$attachmentFile->hasMoved()) {
            $attachmentName = $leave_form_code . '_' . $attachmentFile->getRandomName();
            $attachmentFile->move(ROOTPATH . 'public/leave_attachments', $attachmentName);
            $data['attachment'] = 'leave_attachments/' . $attachmentName;
        }

        try {
            $db = \Config\Database::connect();
            $db->table('tbl_leave_forms')->insert($data);

            return $this->respond([
                'status' => true,
                'message' => 'Leave application submitted successfully',
                'leave_form_code' => $leave_form_code
            ], 201);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to submit leave application',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


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
        $input = $this->request->getJSON(true);

        $leave_form_code     = $input['leave_form_code'] ?? null;
        $user_ref_code       = $input['user_ref_code'] ?? null;
        $status              = strtoupper($input['status'] ?? '');
        $leave_type          = strtoupper($input['leave_type'] ?? '');
        $year_start_end_date = $input['year_start_end_date'] ?? null;
        $start_date          = $input['start_date'] ?? null;
        $end_date            = $input['end_date'] ?? null;
        $total_days          = $input['total_days'] ?? null;
        $isHalfDay           = isset($input['half_day']) && strtoupper($input['half_day']) === 'Y'; // ✅ NEW

        if (!$leave_form_code || !$user_ref_code || !$leave_type || !$year_start_end_date || !$status || !$start_date || !$end_date) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing or invalid required fields.'
            ], 400);
        }

        $allowedLeaveTypes = ['CASUAL', 'MARRIAGE', 'MATERNITY', 'PATERNITY', 'SICK'];
        if (!in_array($leave_type, $allowedLeaveTypes)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid leave type.'
            ], 400);
        }

        $db = \Config\Database::connect();
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $newBalance = null;

        if ($status === 'APPROVED') {
            $builder = $db->table('tbl_emp_leaves_remains');
            $leaveData = $builder->where('user_ref_code', $user_ref_code)
                ->where('years_start_end_date', $year_start_end_date)
                ->get()
                ->getRowArray();

            if (!$leaveData) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Leave data not found for user.'
                ], 404);
            }

            $currentBalance = (float)($leaveData[$leave_type] ?? 0);

            // ✅ For half-day, directly deduct 0.5
            if ($isHalfDay) {
                $workingDays = 0.5;
            } else {
                // Existing working day calculation
                $holidayDates = array_column(
                    $db->table('tbl_holiday_mst')
                        ->select('holiday_date')
                        ->where('is_active', 'Y')
                        ->where('holiday_date >=', $start_date)
                        ->where('holiday_date <=', $end_date)
                        ->get()
                        ->getResultArray(),
                    'holiday_date'
                );

                $start = new \DateTime($start_date);
                $end   = new \DateTime($end_date);
                $end->modify('+1 day');
                $workingDays = 0;
                $interval = new \DatePeriod($start, new \DateInterval('P1D'), $end);

                foreach ($interval as $date) {
                    $day = $date->format('w');
                    $dayOfMonth = $date->format('j');
                    $currentDateStr = $date->format('Y-m-d');
                    $weekOfMonth = ceil($dayOfMonth / 7);

                    $isSunday = $day == 0;
                    $is2ndOr4thSaturday = ($day == 6) && ($weekOfMonth == 2 || $weekOfMonth == 4);
                    $isHoliday = in_array($currentDateStr, $holidayDates);

                    if (!$isSunday && !$is2ndOr4thSaturday && !$isHoliday) {
                        $workingDays++;
                    }
                }
            }

            if ($workingDays <= 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'No working (deductible) days found in the selected range.'
                ]);
            }

            if ($currentBalance < $workingDays) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Insufficient leave balance.'
                ], 400);
            }

            $newBalance = $currentBalance - $workingDays;

            $updateData = [
                $leave_type  => $newBalance,
                'updated_at' => $now,
                'updated_by' => $created_by
            ];

            $builder->where('user_ref_code', $user_ref_code)
                ->where('years_start_end_date', $year_start_end_date)
                ->update($updateData);
        }

        $db->table('tbl_leave_forms')->where('leave_form_code', $leave_form_code)->update([
            'status'            => $status,
            'approver_ref_code' => $created_by,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'total_days'        => $isHalfDay ? 0.5 : $total_days,
            'approval_date'     => $now,
            'updated_at'        => $now,
            'updated_by'        => $created_by
        ]);

        return $this->respond([
            'status' => true,
            'message' => $status === 'APPROVED'
                ? "Leave approved. Remaining {$leave_type} leave: {$newBalance}"
                : "Leave status updated to {$status} (no leave balance deducted)"
        ]);
    }


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
            $db = \Config\Database::connect();

            $builder = $db->table('tbl_leave_forms lf');
            $result = $builder
                ->select('
                lf.*, 
                tl.user_name,
                elr.CASUAL, elr.MARRIAGE, elr.MATERNITY, elr.PATERNITY, elr.SICK
            ')
                ->join('tbl_login tl', 'lf.user_ref_code = tl.user_code_ref', 'left')
                ->join('tbl_emp_leaves_remains elr', 'lf.user_ref_code = elr.user_ref_code', 'left')
                ->orderBy('lf.created_at', 'ASC')
                ->get()
                ->getResult();

            foreach ($result as &$leave) {
                if (!empty($leave->attachment)) {
                    $leave->attachment = base_url('leave_attachments/' . $leave->attachment);
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
        $db = \Config\Database::connect();
        $hod = $db->table('tbl_register')
            ->select('user_code, first_name, last_name, hod_ref_code, Designations')
            ->where('user_code', $hodCode)
            ->where('Designations', 'DESGCPL003')
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$hod) {
            return $this->respond([
                'status' => false,
                'message' => 'User is not an HOD or not found in DESGCPL003'
            ]);
        }
        $hodRefCode = $hod['user_code'];
        $hodName = trim(($hod['first_name'] ?? '') . ' ' . ($hod['last_name'] ?? ''));
        $teamLeads = $db->table('tbl_register')
            ->select('user_code')
            ->where('hod_ref_code', $hodRefCode)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();
        if (empty($teamLeads)) {
            return $this->respond([
                'status' => true,
                'message' => 'No team leads found under this HOD',
                'hod_name' => $hodName,
                'data' => []
            ]);
        }
        $teamLeadCodes = array_column($teamLeads, 'user_code');
        $teamMembers = $db->table('tbl_register')
            ->select('user_code')
            ->whereIn('team_lead_ref_code', $teamLeadCodes)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();
        $allUserCodes = array_merge($teamLeadCodes, array_column($teamMembers, 'user_code'));
        if (empty($allUserCodes)) {
            return $this->respond([
                'status' => true,
                'message' => 'No team members found under this HOD',
                'hod_name' => $hodName,
                'data' => []
            ]);
        }
        $leaveBuilder = $db->table('tbl_leave_forms l');
        $leaveBuilder->select('l.*, r.first_name, r.last_name, r.Designations');
        $leaveBuilder->join('tbl_register r', 'r.user_code = l.user_ref_code', 'left');
        $leaveBuilder->whereIn('l.user_ref_code', $allUserCodes);
        $leaveBuilder->orderBy('l.created_at', 'DESC');
        $leaveApplications = $leaveBuilder->get()->getResultArray();
        if (empty($leaveApplications)) {
            return $this->respond([
                'status' => true,
                'message' => 'No leave applications found for this HOD’s team',
                'hod_name' => $hodName,
                'data' => []
            ]);
        }
        foreach ($leaveApplications as &$leave) {
            $leave['employee_name'] = trim(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? ''));
        }

        return $this->respond([
            'status' => true,
            'message' => 'Leave applications fetched successfully',
            'hod_name' => $hodName,
            'data' => $leaveApplications
        ]);
    }


    public function getallleavebyusercodewithcount()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        $input = $this->request->getJSON(true);
        $user_ref_code       = $input['user_code'] ?? null;
        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_leave_forms');
        $result = $builder
            ->where('start_date >=', date('Y-m-01'))
            ->where('user_ref_code', $user_ref_code)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResult();
        $today = date('Y-m-d');
        $upcomingCount = 0;

        foreach ($result as &$leave) {
            if (!empty($leave->attachment)) {
                $leave->attachment = base_url('leave_attachments/' . $leave->attachment);
            }

            if (!empty($leave->start_date) && $leave->start_date >= $today) {
                $upcomingCount++;
            }
        }

        return $this->respond([
            'status' => true,
            'message' => 'Leave requests fetched successfully.',
            'count_upcoming_leaves' => $upcomingCount,
            'data' => $result
        ]);
    }

    public function leave_attachments($fileName)
    {
        $filePath = FCPATH . 'public/leave_attachments/' . $fileName;
        if (file_exists($filePath)) {
            return $this->response
                ->setHeader('Content-Type', mime_content_type($filePath))
                ->setBody(file_get_contents($filePath));
        }

        return $this->response->setStatusCode(404)->setBody('File not found');
    }
    public function getleavebyusercode()
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
        $user_ref_code = $request['user_ref_code'] ?? null;

        if (empty($user_ref_code)) {
            return $this->respond([
                'status' => false,
                'message' => 'user_ref_code is required'
            ], 400);
        }

        $currentMonth = date('m');
        $currentYear = date('Y');

        if ((int)$currentMonth < 4) {
            $startYear = $currentYear - 1;
            $endYear = $currentYear;
        } else {
            $startYear = $currentYear;
            $endYear = $currentYear + 1;
        }

        $financialYear = "1-04-" . $startYear . "_to_31-03-" . $endYear;


        $db = \Config\Database::connect();
        $builder = $db->table('tbl_emp_leaves_remains');

        $leaveData = $builder->where('user_ref_code', $user_ref_code)
            ->where('years_start_end_date', $financialYear)
            ->get()
            ->getRowArray();

        if (!$leaveData) {
            return $this->respond([
                'status' => false,
                'message' => 'No leave data found for this user in the current financial year.'
            ], 404);
        }

        return $this->respond([
            'status' => true,
            'data' => $leaveData
        ]);
    }
    public function gettodayattendance()
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
        $today = date('Y-m-d');
        $builder->where('user_ref_code', $userCode);
        $builder->where('DATE(today_date)', $today);
        $query = $builder->get();
        $data = $query->getResult();

        return $this->respond([
            'status' => true,
            'message' => 'Today\'s attendance data fetched successfully',
            'data' => $data
        ]);
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
    public function getuserattendanceforadmin()
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

        $request   = $this->request->getJSON(true);
        $userCode  = $request['user_code'] ?? '';
        $monthYear = $request['month_year'] ?? ''; // Format YYYY-MM

        if (empty($userCode)) {
            return $this->respond([
                'status' => false,
                'message' => 'User code is required'
            ], 400);
        }

        // Validate month_year
        if (empty($monthYear) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing month_year format. Use YYYY-MM'
            ], 400);
        }

        // Build date range
        list($year, $month) = explode('-', $monthYear);
        $startDate = "{$year}-{$month}-01";
        $endDate   = date("Y-m-t", strtotime($startDate)); // last day of month

        // Fetch from DB
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_time');
        $builder->where('user_ref_code', $userCode);
        $builder->where('today_date >=', $startDate);
        $builder->where('today_date <=', $endDate);
        $query = $builder->get();
        $data  = $query->getResult();

        return $this->respond([
            'status'  => true,
            'message' => 'Attendance data fetched successfully',
            'data'    => $data
        ]);
    }

    public function allotclientlist()
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
        $authHeader = $this->request->getHeaderLine('Authorization');
        $request = $this->request->getJSON(true);
        $userCode = $request['user_code'] ?? '';
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

    public function getdatewiseattendance()
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
        $today_date = $request['dates'] ?? null;

        if (!$today_date) {
            return $this->respond([
                'status' => false,
                'message' => 'date not found in request'
            ], 400);
        }

        try {
            $db = \Config\Database::connect();
            $builder = $db->table('tbl_time tt')
                ->select('tt.*, tl.user_name') // Add user_name from tbl_login
                ->join('tbl_login tl', 'tt.user_ref_code = tl.user_code_ref', 'left')
                ->where('tt.today_date', $today_date);

            $query = $builder->get();
            $data = $query->getResult();

            return $this->respond([
                'status' => true,
                'message' => 'attendance data fetched successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getdailytasklist()
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
        $userCode = $request['user_code'] ?? '';
        $date     = $request['dates'] ?? '';
        if (empty($date)) {
            return $this->respond([
                'status' => false,
                'message' => 'Date is required'
            ], 400);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_dailytask');

        // Only active tasks
        $builder->where('is_active', 'Y');
        // Filter by date
        $builder->where('task_date', $date);
        // Filter by user if provided
        if (!empty($userCode)) {
            $builder->where('user_ref_code', $userCode);
        }
        $query = $builder->get();
        $data  = $query->getResult();
        return $this->respond([
            'status'  => true,
            'message' => 'Data fetched successfully',
            'data'    => $data
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
        $endDate   = date("Y-m-t", strtotime($startDate));

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
        $last    = strtotime($endDate);

        while ($current <= $last) {
            $dateStr = date('Y-m-d', $current);
            $dayNum  = date('j', $current);
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
                'punch_in'  => $row->punch_in,
                'punch_out' => $row->punch_out
            ];
        }

        // ✅ Prepare final result
        $result = [];
        foreach ($employees as $emp) {
            $totalDays   = count($allDays);
            $holidayCnt  = count(array_filter($allDays, fn($v) => $v === 'holiday'));
            $workingDays = $totalDays - $holidayCnt;

            $presentCnt = 0;
            $dailyLogs  = [];

            foreach ($allDays as $dateStr => $type) {
                $dayLog = [
                    'date'      => $dateStr,
                    'status'    => $type,
                    'punch_in'  => null,
                    'punch_out' => null
                ];

                if ($type === 'working' && isset($attendanceMap[$emp->user_ref_code][$dateStr])) {
                    $presentCnt++;
                    $dayLog['status']    = 'present';
                    $dayLog['punch_in']  = $attendanceMap[$emp->user_ref_code][$dateStr]['punch_in'];
                    $dayLog['punch_out'] = $attendanceMap[$emp->user_ref_code][$dateStr]['punch_out'];
                } elseif ($type === 'working') {
                    $dayLog['status'] = 'absent';
                }

                $dailyLogs[] = $dayLog;
            }

            $absentCnt = $workingDays - $presentCnt;

            $result[] = [
                'user_ref_code' => $emp->user_ref_code,
                'user_name'     => $emp->user_name,
                'total_days'    => $totalDays,
                'holidays'      => $holidayCnt,
                'working_days'  => $workingDays,
                'present'       => $presentCnt,
                'absent'        => $absentCnt,
                'attendance_log' => $dailyLogs   // ✅ includes punch_in & punch_out
            ];
        }

        return $this->respond([
            'status'  => true,
            'message' => 'Monthly attendance fetched successfully',
            'data'    => $result
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
        $request   = $this->request->getJSON(true);
        $monthYear = $request['month_year'] ?? '';
        if (empty($monthYear) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing month_year format. Use YYYY-MM'
            ], 400);
        }
        list($year, $month) = explode('-', $monthYear);
        $startDate = "{$year}-{$month}-01";
        $endDate   = date("Y-m-t", strtotime($startDate));
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_time');
        $builder->where('user_ref_code', $userCode);
        $builder->where('today_date >=', $startDate);
        $builder->where('today_date <=', $endDate);
        $query = $builder->get();
        $data  = $query->getResult();
        return $this->respond([
            'status'  => true,
            'message' => 'Attendance data fetched successfully',
            'data'    => $data
        ]);
    }
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
            $db = \Config\Database::connect();
            $result = $db->table('tbl_leave_forms lf')
                ->select('
                lf.*, 
                elr.CASUAL, 
                elr.MARRIAGE, 
                elr.MATERNITY, 
                elr.PATERNITY, 
                elr.SICK
            ')
                ->join('tbl_emp_leaves_remains elr', 'lf.user_ref_code = elr.user_ref_code', 'left')
                ->where('lf.user_ref_code', $user_code)
                ->orderBy('lf.created_at', 'DESC')
                ->get()
                ->getResult();
            foreach ($result as &$leave) {
                if (!empty($leave->attachment)) {
                    $leave->attachment = base_url('leave_attachments/' . $leave->attachment);
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

        $today = date('Y-m-d');
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('tbl_leave_forms lf');
            $builder->select('lf.*, tl.user_name');
            $builder->join('tbl_login tl', 'lf.user_ref_code = tl.user_code_ref', 'left');
            $builder->where('lf.start_date <=', $today);
            $builder->where('lf.end_date >=', $today);
            $builder->where('lf.status', 'APPROVED');
            $builder->orderBy('lf.created_at', 'DESC');
            $result = $builder->get()->getResult();

            foreach ($result as &$leave) {
                if (!empty($leave->attachment)) {
                    $leave->attachment = base_url('leave_attachments/' . $leave->attachment);
                }
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
}
