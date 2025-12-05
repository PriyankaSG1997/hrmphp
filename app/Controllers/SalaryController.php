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
use App\Models\SalaryModel;

require_once ROOTPATH . 'public/JWT/src/JWT.php';
class SalaryController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    use ResponseTrait;

    public function calculate()
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
        $salaryModel = new SalaryModel();
        $data = $this->request->getJSON(true);

        $result = $salaryModel->calculateSalary($data);
        return $this->response->setJSON($result);
    }
    public function calculateAll()
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
        $salaryModel = new SalaryModel();
        $data = $this->request->getJSON(true);
        if (!isset($data['month'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Month is required'
            ], 400);
        }
        $month = $data['month'];
        $result = $salaryModel->calculateSalaryForAll($month);
        return $this->response->setJSON($result);
    }
public function mark_late_punchin()
{
    helper('jwtvalidate');
    $authHeader = $this->request->getHeaderLine('Authorization');
    $decodedToken = validatejwt($authHeader);

    if (!$decodedToken) {
        return $this->respond([
            'status' => false,
            'message' => 'Invalid or missing JWT token.'
        ], 401);
    }
    $data = $this->request->getJSON(true);
    $punchInDate = $data['punch_in_date'] ?? null;
    $reason = $data['reason'] ?? null;
    $userCode = $data['user_code'] ?? null;
    if (!$punchInDate) {
        return $this->respond([
            'status' => false,
            'message' => 'punch_in_date is required.'
        ], 400);
    }
    try {
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_mark_latepunchin');
        $existing = $builder->where([
            'user_code' => $userCode,
            'punch_in_date' => $punchInDate,
            'is_active' => 'Y'
        ])->get()->getRowArray();
        if ($existing) {
            return $this->respond([
                'status' => false,
                'message' => 'This date is already marked as late punch-in exempted.'
            ], 409);
        }
        $insertData = [
            'user_code' => $userCode,
            'punch_in_date' => $punchInDate,
            'reason' => $reason,
            'created_by' => $userCode,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y'
        ];
        $builder->insert($insertData);
        return $this->respond([
            'status' => true,
            'message' => 'Late punch-in marked successfully.',
            'data' => $insertData
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

    public function getAttendanceByUserCode()
    {
        $request = $this->request->getJSON(true);
        $userCode = $request['user_ref_code'];
        $month = $request['month'];

        $salaryModel = new SalaryModel();
        $data = $salaryModel->getAttendanceData($userCode, $month);

        return $this->response->setJSON([
            'status' => true,
            'data' => $data
        ]);
    }
    public function getAttendanceByUserCodefordashoard()
    {
        $request = $this->request->getJSON(true);
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        // print_r($decodedToken);die;
        $userCode = $decodedToken->user_id;
        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $month = $request['month'];

        $salaryModel = new SalaryModel();
        $data = $salaryModel->getAttendanceDataWithCount($userCode, $month);

        return $this->response->setJSON([
            'status' => true,
            'data' => $data
        ]);
    }
    public function addSalary()
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

        $data = $this->request->getJSON(true);

        if (!isset($data['user_ref_code'], $data['basic_salary'],$data['tds'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing required fields'
            ], 400);
        }

        $db = \Config\Database::connect();

        $userExists = $db->table('tbl_register')
            ->where('user_code', $data['user_ref_code'])
            ->countAllResults();

        if ($userExists == 0) {
            return $this->respond([
                'status' => false,
                'message' => 'Employee not found ',
            ], 404);
        }

        $builder = $db->table('tbl_salary_details');

        try {
            $builder->insert([
                'user_ref_code'     => $data['user_ref_code'],
                'basic_salary'      => $data['basic_salary'],
                // 'hra'               => $data['hra'],
                // 'special_allowance' => $data['special_allowance'],
                // 'insurance'         => $data['insurance'] ?? 0,
                // 'pf'                => $data['pf'] ?? 0,
                'tds'               => $data['tds'] ?? 0,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            return $this->respond([
                'status' => true,
                'message' => 'Salary record added successfully',
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to add salary record',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSalary()
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

        $data = $this->request->getJSON(true);

        if (!isset($data['user_ref_code'], $data['basic_salary'], $data['hra'], $data['special_allowance'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing required fields'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_salary_details');

        try {
            $builder->where('user_ref_code', $data['user_ref_code'])->update([
                'basic_salary'      => $data['basic_salary'],
                'hra'               => $data['hra'],
                'special_allowance' => $data['special_allowance'],
                'insurance'         => $data['insurance'] ?? 0,
                'pf'                => $data['pf'] ?? 0,
                'tds'               => $data['tds'] ?? 0,
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

            return $this->respond([
                'status' => true,
                'message' => 'Salary record updated successfully',
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update salary record',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getSalaryDetails()
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

        $data = $this->request->getJSON(true);
        if (!isset($data['user_ref_code'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing user_ref_code'
            ], 400);
        }

        $userRefCode = $data['user_ref_code'];
        $db = \Config\Database::connect();

        // Fetch salary details
        $builder = $db->table('tbl_salary_details');
        $salaryDetails = $builder->where('user_ref_code', $userRefCode)->get()->getRowArray();

        if (!$salaryDetails) {
            return $this->respond([
                'status' => false,
                'message' => 'Salary details not found for this user'
            ], 404);
        }

        $basic = (float) $salaryDetails['basic_salary'];
        $hra = (float) $salaryDetails['hra'];
        $special = (float) $salaryDetails['special_allowance'];
        $insurance = (float) $salaryDetails['insurance'];
        $pf       = (float) $salaryDetails['pf'];
        $tds      = (float) $salaryDetails['tds'];
        $grossSalary = $basic + $hra + $special;

        // Fetch user info
        $userBuilder = $db->table('tbl_register');
        $userInfo = $userBuilder->where('user_code', $userRefCode)->get()->getRowArray();

        // Fetch designation name
        $designationName = null;
        if (!empty($userInfo['Designations'])) {
            $designationBuilder = $db->table('tbl_designation_mst');
            $designationRow = $designationBuilder->select('designation_name')
                ->where('designation_code', $userInfo['Designations'])
                ->get()
                ->getRowArray();

            if ($designationRow) {
                $designationName = $designationRow['designation_name'];
            }
        }
        $userInfo['designation_name'] = $designationName;
        return $this->respond([
            'status' => true,
            'salary_details' => $salaryDetails,
            'gross_salary' => number_format($grossSalary, 2, '.', ''),
            'user_info' => $userInfo
        ]);
    }

    public function addAppraisal()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $data = $this->request->getJSON(true);
        $required = ['user_ref_code', 'basic_salary', 'hra', 'special_allowance', 'appraisal_date'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing required field: $field"], 400);
            }
        }

        $userRefCode = $data['user_ref_code'];
        $db = Database::connect();

        try {
            // Check if the user exists
            $userExists = $db->table('tbl_register')
                ->where('user_code', $userRefCode)
                ->countAllResults();

            if ($userExists == 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Employee not found',
                ], 404);
            }

            // Insert a new salary record for the appraisal
            $insertData = [
                'user_ref_code' => $userRefCode,
                'basic_salary' => $data['basic_salary'],
                'hra' => $data['hra'],
                'special_allowance' => $data['special_allowance'],
                'insurance'         => $data['insurance'] ?? 0,
                'pf'                => $data['pf'] ?? 0,
                'tds'               => $data['tds'] ?? 0,
                'appraisal_date' => $data['appraisal_date'],
                'authenticated_by' => $data['authenticated_by'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $db->table('tbl_salary_details')->insert($insertData);

            // Get the ID of the newly inserted record
            $newId = $db->insertID();

            return $this->respond([
                'status' => true,
                'message' => 'Appraisal salary record added successfully.',
                'id' => $newId
            ], 200);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getAppraisalByUserCode()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $data = $this->request->getJSON(true);
        $userRefCode = $data['user_ref_code'] ?? null;

        if (!$userRefCode) {
            return $this->respond(['status' => false, 'message' => 'Missing user_ref_code in request body'], 400);
        }

        $db = \Config\Database::connect();

        // Check if user exists
        $user = $db->table('tbl_register')
            ->where('user_code', $userRefCode)
            ->get()
            ->getRow();

        if (!$user) {
            return $this->respond(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        // Get all salary history for the user, sorted by appraisal date in descending order
        $salaryRecords = $db->table('tbl_salary_details')
            ->where('user_ref_code', $userRefCode)
            ->orderBy('appraisal_date', 'DESC')
            ->get()
            ->getResultArray();

        $responseRecords = [];
        if (!empty($salaryRecords)) {
            // Get designation and company information once for efficiency
            $designation = $db->table('tbl_designation_mst')
                ->where('designation_code', $user->Designations)
                ->get()
                ->getRow();

            $company = $db->table('tbl_company')
                ->where('company_Code', $user->company_Code ?? null)
                ->get()
                ->getRow();

            $employee_name = $user->First_Name . ' ' . $user->Last_Name;
            $company_name = $company->company_name ?? null;
            $designation_name = $designation->designation_name ?? null;

            // Iterate through the salary records to compare consecutive appraisals
            for ($i = 0; $i < count($salaryRecords); $i++) {
                $newRecord = $salaryRecords[$i];
                $oldRecord = $salaryRecords[$i + 1] ?? null;

                // Calculate gross annual salary for the new record
                $newGrossAnnualSalary = ((float) $newRecord['basic_salary'] + (float) $newRecord['hra'] + (float) $newRecord['special_allowance']) * 12;

                // Prepare the new appraisal details including the 'id'
                $newAppraisalDetails = [
                    'id' => $newRecord['id'], // Added 'id' here
                    'basic_salary' => $newRecord['basic_salary'],
                    'hra' => $newRecord['hra'],
                    'special_allowance' => $newRecord['special_allowance'],
                    'insurance' => $newRecord['insurance'],
                    'pf' => $newRecord['pf'],
                    'tds' => $newRecord['tds'],
                    'gross_annual_salary' => number_format($newGrossAnnualSalary, 2, '.', ''),
                    'appraisal_date' => $newRecord['appraisal_date']
                ];

                $oldAppraisalDetails = null;
                if ($oldRecord) {
                    // Calculate gross annual salary for the old record
                    $oldGrossAnnualSalary = ((float) $oldRecord['basic_salary'] + (float) $oldRecord['hra'] + (float) $oldRecord['special_allowance']) * 12;

                    // Prepare the old appraisal details
                    $oldAppraisalDetails = [
                        'id' => $oldRecord['id'], // Added 'id' here
                        'basic_salary' => $oldRecord['basic_salary'],
                        'hra' => $oldRecord['hra'],
                        'special_allowance' => $oldRecord['special_allowance'],
                        'insurance' => $oldRecord['insurance'],
                        'pf' => $oldRecord['pf'],
                        'tds' => $oldRecord['tds'],
                        'gross_annual_salary' => number_format($oldGrossAnnualSalary, 2, '.', ''),
                        'appraisal_date' => $oldRecord['appraisal_date']
                    ];
                }

                // Combine the new and old records into a single response item
                $responseRecords[] = [
                    'employee_name' => $employee_name,
                    'company_name' => $company_name,
                    'designation' => $designation_name,
                    'new_appraisal_details' => $newAppraisalDetails,
                    'old_appraisal_details' => $oldAppraisalDetails
                ];
            }
        }

        if (empty($responseRecords)) {
            return $this->respond(['status' => false, 'message' => 'No appraisal records found for this employee.'], 404);
        }

        return $this->respond([
            'status' => true,
            'message' => 'Appraisal records retrieved successfully.',
            'data' => $responseRecords
        ], 200);
    }

    public function getAllAppraisalRecords()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        try {
            // Get database connection
            $db = \Config\Database::connect();

            $salaryRecords = $db->table('tbl_salary_details s')
                ->select('s.*, r.First_Name, r.Last_Name, r.company_Code, r.Designations, c.company_name, d.designation_name')
                ->join('tbl_register r', 's.user_ref_code = r.user_code', 'left')
                ->join('tbl_company c', 'r.company_Code = c.company_Code', 'left')
                ->join('tbl_designation_mst d', 'r.Designations = d.designation_code', 'left')
                ->orderBy('r.user_code', 'ASC')
                ->orderBy('s.appraisal_date', 'DESC')
                ->get()
                ->getResultArray();

            if (empty($salaryRecords)) {
                return $this->respond(['status' => false, 'message' => 'No appraisal records found.'], 404);
            }

            $groupedRecords = [];
            foreach ($salaryRecords as $record) {
                $userCode = $record['user_ref_code'];
                if (!isset($groupedRecords[$userCode])) {
                    $groupedRecords[$userCode] = [
                        'employee_name' => $record['First_Name'] . ' ' . $record['Last_Name'],
                        'user_ref_code' => $userCode,
                        'company_name' => $record['company_name'],
                        'designation' => $record['designation_name'],
                        'appraisal_history' => []
                    ];
                }

                $newGrossAnnualSalary = ((float) $record['basic_salary'] + (float) $record['hra'] + (float) $record['special_allowance']) * 12;
                $currentAppraisal = [
                    'id' => $record['id'], // Added 'id' here
                    'basic_salary' => $record['basic_salary'],
                    'hra' => $record['hra'],
                    'special_allowance' => $record['special_allowance'],
                    'insurance' => $record['insurance'],
                    'pf' => $record['pf'],
                    'tds' => $record['tds'],
                    'gross_annual_salary' => number_format($newGrossAnnualSalary, 2, '.', ''),
                    'appraisal_date' => $record['appraisal_date'],
                    'created_at' => $record['created_at'],
                    'updated_at' => $record['updated_at']
                ];

                $groupedRecords[$userCode]['appraisal_history'][] = $currentAppraisal;
            }

            return $this->respond([
                'status' => true,
                'message' => 'All appraisal records retrieved successfully.',
                'data' => array_values($groupedRecords)
            ], 200);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getAppraisalById()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $data = $this->request->getJSON(true);
        $id = $data['id'] ?? null;
        $userRefCode = $data['user_ref_code'] ?? null;

        if (!$id || !$userRefCode) {
            return $this->respond(['status' => false, 'message' => 'Missing id or user_ref_code in request body'], 400);
        }

        $db = \Config\Database::connect();

        try {
            $record = $db->table('tbl_salary_details s')
                ->select('s.*, r.First_Name, r.Last_Name, c.company_name, d.designation_name')
                ->join('tbl_register r', 's.user_ref_code = r.user_code', 'left')
                ->join('tbl_company c', 'r.company_Code = c.company_Code', 'left')
                ->join('tbl_designation_mst d', 'r.Designations = d.designation_code', 'left')
                ->where('s.id', $id)
                ->where('s.user_ref_code', $userRefCode)
                ->get()
                ->getRow();

            if (!$record) {
                return $this->respond(['status' => false, 'message' => 'Appraisal record not found or does not belong to this user.'], 404);
            }

            // Calculate gross annual salary
            $grossAnnualSalary = ((float) $record->basic_salary + (float) $record->hra + (float) $record->special_allowance) * 13;

            $response = [
                'status' => true,
                'message' => 'Appraisal record retrieved successfully.',
                'data' => [
                    'id' => $record->id,
                    'employee_name' => $record->First_Name . ' ' . $record->Last_Name,
                    'user_ref_code' => $record->user_ref_code,
                    'company_name' => $record->company_name,
                    'designation' => $record->designation_name,
                    'basic_salary' => $record->basic_salary,
                    'hra' => $record->hra,
                    'authenticated_by' => $record->authenticated_by,
                    'special_allowance' => $record->special_allowance,
                    'insurance' => $record->insurance,
                    'pf' => $record->pf,
                    'tds' => $record->tds,
                    'gross_annual_salary' => number_format($grossAnnualSalary, 2, '.', ''),
                    'gross_monthly_salary' => number_format($grossAnnualSalary / 13, 2, '.', ''),
                    'appraisal_date' => $record->appraisal_date,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                ]
            ];

            return $this->respond($response, 200);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
