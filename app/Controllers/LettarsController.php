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
class LettarsController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format = 'json';
    protected $homeModel;
    use ResponseTrait;

    public function offerlettar()
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
        $userCode = $request['user_ref_code'] ?? null;

        if (!$userCode) {
            return $this->respond(['status' => false, 'message' => 'user_ref_code is required'], 400);
        }

        $db = \Config\Database::connect();

        $builder = $db->table('tbl_register as r');
        $builder->select('
        r.joining_date, r.first_name, r.last_name, 
        d.designation_name, c.company_name,
        e.permanent_address, 
        s.basic_salary, s.hra, s.special_allowance
    ');
        $builder->join('tbl_designation_mst as d', 'r.Designations = d.designation_code', 'left');
        $builder->join('tbl_company as c', 'r.company_code = c.company_code', 'left');
        $builder->join('tbl_employee_details as e', 'r.user_code = e.user_code_ref', 'left');
        $builder->join('tbl_salary_details as s', 'r.user_code = s.user_ref_code', 'left');
        $builder->where('r.user_code', $userCode);

        $query = $builder->get();
        $data = $query->getRowArray();

        if (!$data) {
            return $this->respond(['status' => false, 'message' => 'User not found'], 404);
        }

        $gross = (float) $data['basic_salary'] + (float) $data['hra'] + (float) $data['special_allowance'];
        $ctc = $gross * 12;

        $response = [
            'status' => true,
            'message' => 'Offer letter data fetched successfully',
            'data' => [
                'joining_date' => $data['joining_date'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'designation' => $data['designation_name'],
                'company_name' => $data['company_name'],
                'permanent_address' => $data['permanent_address'],
                'basic_salary' => $data['basic_salary'],
                'hra' => $data['hra'],
                'special_allowance' => $data['special_allowance'],
                'gross_salary' => $gross,
                'ctc' => $ctc,
            ]
        ];

        return $this->respond($response);
    }

    public function addOfferLetter()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Token validation failed'], 401);
        }

        $data = $this->request->getJSON(true);

        $required = ['date', 'employee_name', 'employee_address', 'company_name', 'designation', 'gross_salary', 'ctc', 'faithfully_name', 'faithfully_designation'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing: $field"], 400);
            }
        }

        $db = \Config\Database::connect();
        $insertData = [
            'date' => $data['date'],
            'employee_name' => $data['employee_name'],
            'employee_address' => $data['employee_address'],
            'company_name' => $data['company_name'],
            'designation' => $data['designation'],
            'gross_salary' => $data['gross_salary'],
            'ctc' => $data['ctc'],
            'faithfully_name' => $data['faithfully_name'],
            'faithfully_designation' => $data['faithfully_designation'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $db->table('tbl_offerletter')->insert($insertData);

        return $this->respond(['status' => true, 'message' => 'Offer letter added successfully']);
    }

    public function updateOfferLetter()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // Validate JWT
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Token validation failed'], 401);
        }

        // Get data from request
        $data = $this->request->getJSON(true);

        // Ensure ID is provided
        if (empty($data['id'])) {
            return $this->respond(['status' => false, 'message' => 'Missing: id'], 400);
        }

        // Required fields
        $required = ['date', 'employee_name', 'employee_address', 'company_name', 'designation', 'gross_salary', 'ctc', 'faithfully_name', 'faithfully_designation', 'status'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->respond(['status' => false, 'message' => "Missing: $field"], 400);
            }
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_offerletter');

        // Prepare update data
        $updateData = [
            'date' => $data['date'],
            'employee_name' => $data['employee_name'],
            'employee_address' => $data['employee_address'],
            'company_name' => $data['company_name'],
            'designation' => $data['designation'],
            'gross_salary' => $data['gross_salary'],
            'ctc' => $data['ctc'],
            'faithfully_name' => $data['faithfully_name'],
            'faithfully_designation' => $data['faithfully_designation'],
            // 'status' => $data['status'],
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Perform update
        $builder->where('id', $data['id']);
        $updated = $builder->update($updateData);

        if ($updated) {
            return $this->respond(['status' => true, 'message' => 'Offer letter updated successfully']);
        } else {
            return $this->respond(['status' => false, 'message' => 'Failed to update offer letter or no changes made'], 400);
        }
    }


    public function getAllOfferLetters()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // ✅ Validate JWT
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid token'], 401);
        }

        try {
            $db = \Config\Database::connect();

            // ✅ Join offer letters with company table
            $query = $db->table('tbl_offerletter AS tol')
                ->select('tol.*, tc.company_name as companyname, tc.logo AS company_logo, tc.address AS company_address, tc.email AS company_email')
                ->where('tol.is_active', 'Y')
                ->join('tbl_company AS tc', 'tol.company_name = tc.company_code', 'left')
                ->get()
                ->getResult();

            // ✅ Convert file paths to full URLs
            foreach ($query as $letter) {
                if (!empty($letter->company_logo)) {
                    $letter->company_logo = base_url($letter->company_logo);
                }
                if (!empty($letter->offer_letter_file)) { // assuming this is the column
                    $letter->offer_letter_file = base_url($letter->offer_letter_file);
                }
            }

            return $this->respond([
                'status' => true,
                'message' => 'Offer letters fetched successfully.',
                'data' => $query
            ]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Unexpected error: ' . $e->getMessage()
            ], 500);
        }
    }



    public function addPolicy()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }
        $data = $this->request->getJSON(true);
        $required = ['policy_title', 'policy_description', 'effective_date', 'status'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing: $field"], 400);
            }
        }
        $db = \Config\Database::connect();
        try {
            $insertData = [
                'policy_title' => $data['policy_title'],
                'policy_description' => $data['policy_description'],
                'effective_date' => $data['effective_date'],
                'status' => $data['status'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $db->table('tbl_policy')->insert($insertData);
            return $this->respond([
                'status' => true,
                'message' => 'Policy added successfully',
                'policy_id' => $db->insertID()
            ], 200);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function updatePolicy()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }
        $data = $this->request->getJSON(true);
        $required = ['policy_id', 'policy_title', 'policy_description', 'effective_date', 'status'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing: $field"], 400);
            }
        }
        $policyId = $data['policy_id'];
        $db = \Config\Database::connect();
        $policyExists = $db->table('tbl_policy')
            ->where('policy_id', $policyId)
            ->countAllResults();
        if ($policyExists == 0) {
            return $this->respond(['status' => false, 'message' => 'Policy not found'], 404);
        }
        try {
            $updateData = [
                'policy_title' => $data['policy_title'],
                'policy_description' => $data['policy_description'],
                'effective_date' => $data['effective_date'],
                'status' => $data['status'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $db->table('tbl_policy')->where('policy_id', $policyId)->update($updateData);

            return $this->respond(['status' => true, 'message' => 'Policy updated successfully'], 200);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function getAllPolicies()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid token'], 401);
        }
        try {
            $db = \Config\Database::connect();
            $policies = $db->table('tbl_policy')->where('status', 'Active')->get()->getResult();

            if (empty($policies)) {
                return $this->respond(['status' => false, 'message' => 'No policies found'], 404);
            }
            return $this->respond([
                'status' => true,
                'message' => 'Policies fetched successfully',
                'data' => $policies
            ], 200);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function addTask()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $data = $this->request->getJSON(true);

        $required = ['user_ref_code', 'task_date', 'task_description'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing: $field"], 400);
            }
        }

        $db = \Config\Database::connect();
        try {
            $insertData = [
                'user_ref_code' => $data['user_ref_code'],
                'task_date' => $data['task_date'],
                'project_code' => $data['project_code'] ?? null,
                'task_description' => $data['task_description'],
                'status' => $data['status'] ?? 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $db->table('tbl_dailytask')->insert($insertData);

            return $this->respond([
                'status' => true,
                'message' => 'Task added successfully',
                'task_id' => $db->insertID()
            ], 200);
        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function updateTask()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $data = $this->request->getJSON(true);

        $required = ['task_id', 'task_date', 'task_description', 'status'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing: $field"], 400);
            }
        }

        $taskId = $data['task_id'];
        $db = \Config\Database::connect();

        $taskExists = $db->table('tbl_dailytask')
            ->where('task_id', $taskId)
            ->countAllResults();

        if ($taskExists == 0) {
            return $this->respond(['status' => false, 'message' => 'Task not found'], 404);
        }

        try {
            $updateData = [
                'task_date' => $data['task_date'],
                'project_code' => $data['project_code'] ,
                'task_description' => $data['task_description'],
                'status' => $data['status'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $db->table('tbl_dailytask')->where('task_id', $taskId)->update($updateData);

            return $this->respond(['status' => true, 'message' => 'Task updated successfully'], 200);
        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getAllTasks()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid token'], 401);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_dailytask');

        try {
            // Optional: Filter by user if 'user_ref_code' is provided in the request body
            $data = $this->request->getJSON(true);
            if (isset($data['user_ref_code']) && !empty($data['user_ref_code'])) {
                $builder->where('user_ref_code', $data['user_ref_code']);
            }

            $tasks = $builder->get()->getResult();

            if (empty($tasks)) {
                return $this->respond(['status' => false, 'message' => 'No tasks found'], 404);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Tasks fetched successfully',
                'data' => $tasks
            ], 200);
        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
   public function getOfferLetterByIdPost()
{
    helper(['jwtvalidate_helper']);
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');
    if (!validatejwt($headers)) {
        return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
    }
    $request = $this->request->getJSON(true);
    $offerLetterId = $request['id'] ?? null;
    if (empty($offerLetterId)) {
        return $this->respond(['status' => false, 'message' => 'Offer letter ID is required.'], 400);
    }
    $db = \Config\Database::connect();
    try {
        $data = $db->table('tbl_offerletter AS tol')
            ->select('tol.*, tc.company_name AS company_master_name, tc.company_code AS company_code_ref, tc.logo AS company_logo, tc.address AS company_address, tc.email AS company_email')
            ->join('tbl_company AS tc', 'tol.company_name = tc.company_code', 'left')
            ->where('tol.id', $offerLetterId)
            ->get()
            ->getRow();
        if ($data) {
            $data->company_name = $data->company_master_name;
            
            // Format company logo path exactly like your existing pattern
            if (!empty($data->company_logo)) {
                $data->company_logo = base_url('companylogo/' . $data->company_logo);
            }
            
            if (!empty($data->offer_letter_file)) {
                $data->offer_letter_file = base_url($data->offer_letter_file);
            }
            return $this->respond([
                'status' => true,
                'message' => 'Offer letter fetched successfully.',
                'data' => $data
            ], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'Offer letter not found.'], 404);
        }
    } catch (\Exception $e) {
        return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    }
}   
    public function addExperienceLetter()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Token validation failed'], 401);
        }

        $data = $this->request->getJSON(true);

        // Required fields for experience letter
        $required = [
            'date',
            'employee_code',
            'employee_address',
            'company_name',
            'designation',
            'faithfully_name',
            'faithfully_designation',
            'employment_start_date',
            'employment_end_date'
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing required field: $field"], 400);
            }
        }

        $db = \Config\Database::connect();

        $insertData = [
            'date' => $data['date'],
            'employee_code' => $data['employee_code'],
            'employee_address' => $data['employee_address'],
            'company_name' => $data['company_name'],
            'designation' => $data['designation'],
            'employment_start_date' => $data['employment_start_date'],
            'employment_end_date' => $data['employment_end_date'],
            'faithfully_name' => $data['faithfully_name'],
            'faithfully_designation' => $data['faithfully_designation'],
            'status' => $data['status'] ?? 'Pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Optional fields
        if (isset($data['employee_full_name'])) {
            $insertData['employee_full_name'] = $data['employee_full_name'];
        }

        if (isset($data['work_experience'])) {
            $insertData['work_experience'] = $data['work_experience'];
        }

        if (isset($data['reason_for_leaving'])) {
            $insertData['reason_for_leaving'] = $data['reason_for_leaving'];
        }

        $db->table('tbl_experience_letter')->insert($insertData);

        return $this->respond([
            'status' => true,
            'message' => 'Experience letter added successfully',
            'insert_id' => $db->insertID()
        ]);
    }

    public function updateExperienceLetter()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $data = $this->request->getJSON(true);

        if (empty($data['id'])) {
            return $this->respond(['status' => false, 'message' => 'Experience letter ID is required'], 400);
        }

        $required = [
            'date',
            'employee_code',
            'employee_address',
            'company_name',
            'designation',
            'faithfully_name',
            'faithfully_designation',
            'employment_start_date',
            'employment_end_date'
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->respond(['status' => false, 'message' => "Missing required field: $field"], 400);
            }
        }

        $db = \Config\Database::connect();

        $updateData = [
            'date' => $data['date'],
            'employee_code' => $data['employee_code'],
            'employee_address' => $data['employee_address'],
            'company_name' => $data['company_name'],
            'designation' => $data['designation'],
            'employment_start_date' => $data['employment_start_date'],
            'employment_end_date' => $data['employment_end_date'],
            'faithfully_name' => $data['faithfully_name'],
            'faithfully_designation' => $data['faithfully_designation'],
            'status' => $data['status'] ?? 'Pending',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Optional fields
        if (isset($data['employee_full_name'])) {
            $updateData['employee_full_name'] = $data['employee_full_name'];
        }

        $db->table('tbl_experience_letter')
            ->where('id', $data['id'])
            ->update($updateData);

        return $this->respond([
            'status' => true,
            'message' => 'Experience letter updated successfully'
        ]);
    }

    public function getAllExperienceLetters()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // ✅ Validate JWT
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid token'], 401);
        }

        try {
            $db = \Config\Database::connect();

            // ✅ Join experience letters with employee and company tables
            $query = $db->table('tbl_experience_letter AS tel')
                ->select('
                tel.*, 
                r.first_name,
                r.middle, 
                r.last_name,
                tc.company_name as companyname, 
                tc.logo AS company_logo, 
                tc.address AS company_address, 
                tc.email AS company_email,
                d.designation_name
            ')
                ->join('tbl_register AS r', 'tel.employee_code = r.user_code', 'left')
                ->join('tbl_company AS tc', 'tel.company_name = tc.company_code', 'left')
                ->join('tbl_designation_mst AS d', 'tel.designation = d.designation_name', 'left')
                ->where('tel.is_active', 'Y')
                ->orderBy('tel.created_at', 'DESC')
                ->get()
                ->getResult();


            // ✅ Format the data for frontend
            $formattedData = [];
            //   echo"<pre>" ; print_r(value: $query);exit();
            foreach ($query as $letter) {

                $formattedLetter = [
                    'id' => $letter->id,
                    'date' => $letter->date,
                    'employee_code' => $letter->employee_code,
                    'employee_name' => trim($letter->first_name . ' ' . $letter->middle . ' ' . $letter->last_name),
                    'employee_address' => $letter->employee_address,
                    'company_name' => $letter->company_name,
                    'designation' => $letter->designation_name ?? $letter->designation,
                    'employment_start_date' => $letter->employment_start_date,
                    'employment_end_date' => $letter->employment_end_date,
                    'faithfully_name' => $letter->faithfully_name,
                    'faithfully_designation' => $letter->faithfully_designation,
                    'status' => $letter->status,
                    'created_at' => $letter->created_at
                ];

                // ✅ Convert file paths to full URLs if needed
                if (!empty($letter->company_logo)) {
                    $formattedLetter['company_logo'] = base_url($letter->company_logo);
                }

                if (!empty($letter->experience_letter_file)) {
                    $formattedLetter['experience_letter_file'] = base_url($letter->experience_letter_file);
                }

                $formattedData[] = $formattedLetter;
            }

            return $this->respond([
                'status' => true,
                'message' => 'Experience letters fetched successfully.',
                'data' => $formattedData
            ]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Unexpected error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getExperienceLetterById()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Invalid token'], 401);
        }

        $data = $this->request->getJSON(true);
        $id = $data['id'] ?? null;

        if (!$id) {
            return $this->respond(['status' => false, 'message' => 'Experience letter ID is required'], 400);
        }

        try {
            $db = \Config\Database::connect();

            $query = $db->table('tbl_experience_letter AS tel')
                ->select('
                tel.*, 
                r.first_name,
                r.middle, 
                r.last_name,
                tc.company_name as companyname, 
                tc.logo AS company_logo, 
                tc.address AS company_address,
                tc.company_code,
                d.designation_name
            ')
                ->join('tbl_register AS r', 'tel.employee_code = r.user_code', 'left')
                ->join('tbl_company AS tc', 'tel.company_name = tc.company_code', 'left')
                ->join('tbl_designation_mst AS d', 'tel.designation = d.designation_name', 'left')
                ->where('tel.id', $id)
                ->get();

            $experienceLetter = $query->getRow();

            if (!$experienceLetter) {
                return $this->respond(['status' => false, 'message' => 'Experience letter not found'], 404);
            }

            // Format company logo path like your existing pattern
            $companyLogo = null;
            if (!empty($experienceLetter->company_logo)) {
                $companyLogo = base_url('companylogo/' . $experienceLetter->company_logo);
            }

            // Format the response
            $formattedData = [
                'id' => $experienceLetter->id,
                'date' => $experienceLetter->date,
                'employee_code' => $experienceLetter->employee_code,
                'employee_name' => trim($experienceLetter->first_name . ' ' . $experienceLetter->middle . ' ' . $experienceLetter->last_name),
                'employee_address' => $experienceLetter->employee_address,
                'company_name' => $experienceLetter->company_name,
                'company_code' => $experienceLetter->company_code,
                'company_logo' => $companyLogo,
                'company_address' => $experienceLetter->company_address,
                'designation' => $experienceLetter->designation_name ?? $experienceLetter->designation,
                'employment_start_date' => $experienceLetter->employment_start_date,
                'employment_end_date' => $experienceLetter->employment_end_date,
                'faithfully_name' => $experienceLetter->faithfully_name,
                'faithfully_designation' => $experienceLetter->faithfully_designation,
                'status' => $experienceLetter->status,
                'work_experience' => $experienceLetter->work_experience,
                'reason_for_leaving' => $experienceLetter->reason_for_leaving,
                'created_at' => $experienceLetter->created_at
            ];

            return $this->respond([
                'status' => true,
                'message' => 'Experience letter fetched successfully',
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to fetch experience letter: ' . $e->getMessage()
            ], 500);
        }
    }


}
