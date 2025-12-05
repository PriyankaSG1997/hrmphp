<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use Config\Services;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

class ArchiethosController extends BaseController
{
    protected $db;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function add_project()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;

        $input = $this->request->getJSON(true);
        $rules = [
            'project_name'      => 'required|min_length[3]',
            'client_name'       => 'required|min_length[3]',
            'type_of_project'   => 'required',
            'services'          => 'required',
            'total_budget'      => 'required|numeric',
        ];

        if (!$this->validate($rules, $input)) {
            return $this->respond(['status' => false, 'message' => 'Validation Error', 'errors' => $this->validator->getErrors()], 400);
        }

        $project_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $data = [
            'arch_project_code' => $project_code,
            'project_name'      => $input['project_name'],
            'client_name'       => $input['client_name'],
            'type_of_project'   => $input['type_of_project'],
            'services'          => $input['services'],
            'total_budget'      => $input['total_budget'],
            'is_active'         => 'Y',
            'created_by'        => $created_by,
            'created_at'        => date('Y-m-d H:i:s'),
        ];

        try {
            $this->db->table('tbl_archiethos_projects')->insert($data);
            return $this->respond([
                'status' => true,
                'message' => 'Project added successfully',
                'arch_project_code' => $project_code
            ], 201);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function update_project()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $updated_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);

        $project_code = $input['arch_project_code'] ?? null;
        if (empty($project_code)) {
            return $this->respond(['status' => false, 'message' => 'Project code is required'], 400);
        }

        $project = $this->db->table('tbl_archiethos_projects')
            ->where('arch_project_code', $project_code)
            ->get()
            ->getRowArray();

        if (!$project) {
            return $this->respond(['status' => false, 'message' => 'Project not found'], 404);
        }

        if ($project['is_active'] === 'N') {
            return $this->respond(['status' => false, 'message' => 'This project is inactive and cannot be updated'], 400);
        }

        $data = [];
        $inputFields = ['project_name', 'client_name', 'type_of_project', 'services', 'total_budget', 'is_active'];
        foreach ($inputFields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (empty($data)) {
            return $this->respond(['status' => false, 'message' => 'No data provided to update'], 400);
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->db->table('tbl_archiethos_projects')
                ->where('arch_project_code', $project_code)
                ->update($data);

            if ($this->db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Project updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Project not found or no change'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function get_all_projects()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $projects = $this->db->table('tbl_archiethos_projects')
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        return $this->respond(['status' => true, 'data' => $projects], 200);
    }

    public function get_project_by_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $project_code = $input['arch_project_code'] ?? null;

        if (!$project_code) {
            return $this->respond(['status' => false, 'message' => 'Project code is required'], 400);
        }

        $project = $this->db->table('tbl_archiethos_projects')
            ->where('arch_project_code', $project_code)
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$project) {
            return $this->respond(['status' => false, 'message' => 'Project not found or is inactive'], 404);
        }

        return $this->respond(['status' => true, 'data' => $project], 200);
    }

    public function add_task()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);
        $rules = [
            'project_code' => 'required',
        ];

        if (!$this->validate($rules, $input)) {
            return $this->respond(['status' => false, 'message' => 'Validation Error', 'errors' => $this->validator->getErrors()], 400);
        }

        // Set default task_status to 'Pending' if not provided
        $task_status = $input['task_status'] ?? 'PENDING';

        $task_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $data = [
            'arch_task_code'            => $task_code,
            'project_code'              => $input['project_code'],
            'task'        => $input['task'] ?? null,
            'description'          => $input['description'], // Corrected field name
            'assignee_site_supervisor'  => $input['assignee_site_supervisor'],
            'designer_user_code'        => $input['designer_user_code'],
            'due_date'                  => $input['due_date'],
            'priority'                  => $input['priority'],
            'task_status'               => $task_status,
            'is_active'                 => 'Y',
            'created_by'                => $created_by,
            'created_at'                => date('Y-m-d H:i:s'),
        ];

        try {
            $this->db->table('tbl_archiethos_project_task')->insert($data);
            return $this->respond([
                'status' => true,
                'message' => 'Task added successfully',
                'arch_task_code' => $task_code
            ], 201);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function update_task()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $updated_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);

        $task_code = $input['arch_task_code'] ?? null;
        if (empty($task_code)) {
            return $this->respond(['status' => false, 'message' => 'Task code is required'], 400);
        }

        $task = $this->db->table('tbl_archiethos_project_task')
            ->where('arch_task_code', $task_code)
            ->get()
            ->getRowArray();

        if (!$task) {
            return $this->respond(['status' => false, 'message' => 'Task not found'], 404);
        }

        if ($task['is_active'] === 'N') {
            return $this->respond(['status' => false, 'message' => 'This task is inactive and cannot be updated'], 400);
        }

        $data = [];
        $inputFields = ['project_code', 'task', 'task_description', 'description', 'assignee_site_supervisor', 'designer_user_code', 'due_date', 'priority', 'task_status', 'is_active'];
        foreach ($inputFields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (empty($data)) {
            return $this->respond(['status' => false, 'message' => 'No data provided to update'], 400);
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->db->table('tbl_archiethos_project_task')
                ->where('arch_task_code', $task_code)
                ->update($data);

            if ($this->db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Task updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Task not found or no change'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function get_all_tasks()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        try {
            $tasks = $this->db->table('tbl_archiethos_project_task t')
                ->select('
                t.*,
                p.arch_project_code,
                p.project_name,
                p.client_name,
                l.user_name AS designer_name
            ')
                ->join('tbl_archiethos_projects p', 'p.arch_project_code = t.project_code', 'left')
                ->join('tbl_login l', 'l.user_code_ref = t.designer_user_code', 'left')
                ->where('t.is_active', 'Y')
                ->get()
                ->getResultArray();

            return $this->respond([
                'status' => true,
                'data'   => $tasks
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }



    public function get_task_by_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $task_code = $input['arch_task_code'] ?? null;

        if (!$task_code) {
            return $this->respond(['status' => false, 'message' => 'Task code is required'], 400);
        }

        $task = $this->db->table('tbl_archiethos_project_task')
            ->where('arch_task_code', $task_code)
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$task) {
            return $this->respond(['status' => false, 'message' => 'Task not found or is inactive'], 404);
        }

        return $this->respond(['status' => true, 'data' => $task], 200);
    }

    public function get_tasks_by_project_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $project_code = $input['project_code'] ?? null;

        if (!$project_code) {
            return $this->respond(['status' => false, 'message' => 'Project code is required'], 400);
        }

        $tasks = $this->db->table('tbl_archiethos_project_task')
            ->where('project_code', $project_code)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        return $this->respond(['status' => true, 'data' => $tasks], 200);
    }

    public function add_project_status()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        // Required field validation for project_code
        if (empty($input['project_code'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Project code is required as per the database schema.'
            ], 400);
        }

        $project_status_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

        // Base required fields
        $data = [
            'project_status_code' => $project_status_code,
            'project_code'        => $input['project_code'],
            'updated_at'          => date('Y-m-d H:i:s'),
        ];

        // Optional fields – only add if present in request
        $optionalFields = [
            'initial_discussion',
            'site_visit',
            'client_requirement',
            'designing_process',
            'render_3d',
            'working_drawing',
            'execution',
            'handover'
        ];

        foreach ($optionalFields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        try {
            $this->db->table('project_status_tracking')->insert($data);
            return $this->respond([
                'status' => true,
                'message' => 'Project status added successfully',
                'project_status_code' => $project_status_code
            ], 201);
        } catch (DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function update_project_status()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $project_status_code = $input['project_status_code'] ?? null;

        if (empty($project_status_code)) {
            return $this->respond(['status' => false, 'message' => 'Project status code is required'], 400);
        }

        $data = [];
        $inputFields = [
            'initial_discussion',
            'site_visit',
            'client_requirement',
            'designing_process',
            'render_3d',
            'working_drawing',
            'execution',
            'handover'
        ];
        foreach ($inputFields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (empty($data)) {
            return $this->respond(['status' => false, 'message' => 'No data provided to update'], 400);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->db->table('project_status_tracking')
                ->where('project_status_code', $project_status_code)
                ->update($data);

            if ($this->db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Project status updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Project status not found or no change'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function get_all_project_status()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $status_data = $this->db->table('project_status_tracking pst')
            ->select('pst.*, p.arch_project_code, p.project_name, p.client_name') // add project fields you need
            ->join('tbl_archiethos_projects p', 'p.arch_project_code = pst.project_code', 'left')
            ->get()
            ->getResultArray();

        return $this->respond(['status' => true, 'data' => $status_data], 200);
    }


    public function get_project_status_by_project_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $project_code = $input['project_code'] ?? null;

        if (!$project_code) {
            return $this->respond(['status' => false, 'message' => 'Project code is required'], 400);
        }

        $status_data = $this->db->table('project_status_tracking')
            ->where('project_code', $project_code)
            ->get()
            ->getRowArray();

        if (!$status_data) {
            return $this->respond(['status' => false, 'message' => 'Project status not found for the given project code'], 404);
        }

        return $this->respond(['status' => true, 'data' => $status_data], 200);
    }

    public function add_site_visit()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        if (empty($input['project_code'])) {
            return $this->respond(['status' => false, 'message' => 'Project code is required'], 400);
        }

        $site_visit_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

        $data = [
            'site_visit_code'        => $site_visit_code,
            'project_code'           => $input['project_code'],
            'visit_date'             => $input['visit_date'] ?? null,
            'visit_time'             => $input['visit_time'] ?? null,
            'attendees'              => $input['attendees'] ?? null,
            'purpose'                => $input['purpose'] ?? null,
            'site_visit_description' => $input['site_visit_description'] ?? null,
            'created_at'             => date('Y-m-d H:i:s'),
            'created_by'             => $decodedToken->user_id ?? null,
            'is_active'              => 1
        ];

        try {
            $this->db->table('tbl_schedule_site_visit')->insert($data);
            return $this->respond([
                'status' => true,
                'message' => 'Site visit scheduled successfully',
                'site_visit_code' => $site_visit_code
            ], 201);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function update_site_visit()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $site_visit_code = $input['site_visit_code'] ?? null;

        if (empty($site_visit_code)) {
            return $this->respond(['status' => false, 'message' => 'Site visit code is required'], 400);
        }

        $fields = ['visit_date', 'visit_time', 'attendees', 'project_code', 'purpose', 'site_visit_description', 'is_active'];
        $data = [];

        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (empty($data)) {
            return $this->respond(['status' => false, 'message' => 'No data provided to update'], 400);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $decodedToken->user_id ?? null;

        try {
            $this->db->table('tbl_schedule_site_visit')
                ->where('site_visit_code', $site_visit_code)
                ->update($data);

            if ($this->db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Site visit updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Site visit not found or no change'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function get_all_site_visits()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $result = $this->db->table('tbl_schedule_site_visit sv')
            ->select('sv.*, p.arch_project_code, p.project_name, p.client_name')
            ->join('tbl_archiethos_projects p', 'p.arch_project_code = sv.project_code', 'left')
            ->get()
            ->getResultArray();

        return $this->respond(['status' => true, 'data' => $result], 200);
    }
    public function get_site_visits_by_project()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $project_code = $input['project_code'] ?? null;

        if (empty($project_code)) {
            return $this->respond(['status' => false, 'message' => 'Project code is required'], 400);
        }

        $result = $this->db->table('tbl_schedule_site_visit')
            ->where('project_code', $project_code)
            ->get()
            ->getResultArray();

        if (!$result) {
            return $this->respond(['status' => false, 'message' => 'No site visits found for given project code'], 404);
        }

        return $this->respond(['status' => true, 'data' => $result], 200);
    }

    public function add_vendor()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        if (empty($input['name']) || empty($input['contact_number'])) {
            return $this->respond(['status' => false, 'message' => 'Name and Contact number are required'], 400);
        }

        $vendor_code = substr(str_shuffle('VEND' . time() . rand(1000, 9999)), 0, 10);

        $data = [
            'vendor_code'         => $vendor_code,
            'name'                => $input['name'],
            'contact_number'      => $input['contact_number'],
            'email'               => $input['email'] ?? null,
            'services'            => $input['services'] ?? null,
            'pan_number'          => $input['pan_number'] ?? null,
            'aadhar_number'       => $input['aadhar_number'] ?? null,
            'other_contact_number' => $input['other_contact_number'] ?? null,
            'gstin'               => $input['gstin'] ?? null,
            'account_name'        => $input['account_name'] ?? null,
            'account_number'      => $input['account_number'] ?? null,
            'ifsc_code'           => $input['ifsc_code'] ?? null,
            'bank_name'           => $input['bank_name'] ?? null,
            'branch'              => $input['branch'] ?? null,
            'upi_mobile'          => $input['upi_mobile'] ?? null,     // ✅ new
            'upi_id'              => $input['upi_id'] ?? null,
            'payment_amount'      => $input['payment_amount'] ?? 0,
            'created_at'          => date('Y-m-d H:i:s'),
            'created_by'          => $decodedToken->user_id ?? 'SYSTEM',
            'is_active'           => 1
        ];

        try {
            $this->db->table('tbl_vendor')->insert($data);
            return $this->respond(['status' => true, 'message' => 'Vendor added successfully', 'vendor_code' => $vendor_code], 201);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function update_vendor()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $vendor_code = $input['vendor_code'] ?? null;

        if (empty($vendor_code)) {
            return $this->respond(['status' => false, 'message' => 'Vendor code is required'], 400);
        }

        $data = [];
        // $fields = ['name', 'contact_number', 'email', 'services', 'pan_number', 'aadhar_number', 'other_contact_number', 'gstin', 'account_number', 'ifsc_code', 'bank_name', 'branch', 'payment_amount', 'is_active'];
        $fields = [
            'name',
            'contact_number',
            'email',
            'services',
            'pan_number',
            'aadhar_number',
            'gstin',
            'account_name',
            'account_number',
            'ifsc_code',
            'bank_name',
            'branch',
            'upi_mobile',
            'upi_id',
            'payment_amount',
            'is_active'
        ];

        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (empty($data)) {
            return $this->respond(['status' => false, 'message' => 'No data provided to update'], 400);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $decodedToken->user_id ?? 'SYSTEM';

        try {
            $this->db->table('tbl_vendor')
                ->where('vendor_code', $vendor_code)
                ->update($data);

            if ($this->db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Vendor updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Vendor not found or no change'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function get_vendor_by_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $vendor_code = $input['vendor_code'] ?? null;

        if (!$vendor_code) {
            return $this->respond(['status' => false, 'message' => 'Vendor code is required'], 400);
        }

        $vendor = $this->db->table('tbl_vendor')->where('vendor_code', $vendor_code)->get()->getRowArray();

        if ($vendor) {
            return $this->respond(['status' => true, 'data' => $vendor], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'Vendor not found'], 404);
        }
    }

    public function get_all_vendors()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $vendors = $this->db->table('tbl_vendor')->where('is_active' ,'Y')->get()->getResultArray();
        return $this->respond(['status' => true, 'data' => $vendors], 200);
    }

    public function get_archiethos_emp()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $employees = $this->db->table('tbl_register')
            ->where('is_active', 'Y')
            ->where('company_Code', 'CMP004')
            ->get()
            ->getResultArray();

        return $this->respond(['status' => true, 'data' => $employees], 200);
    }

    public function get_task_by_usercode()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $user_code = $input['user_code'] ?? null;

        if (!$user_code) {
            return $this->respond(['status' => false, 'message' => 'User code is required'], 400);
        }

        $tasks = $this->db->table('tbl_archiethos_project_task')
            ->where('designer_user_code', $user_code)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        return $this->respond(['status' => true, 'data' => $tasks], 200);
    }
}
