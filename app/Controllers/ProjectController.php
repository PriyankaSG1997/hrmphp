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

class ProjectController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = Database::connect();
    }


public function addProject()
{
    helper(['jwtvalidate_helper']);
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    if (!validatejwt($headers)) {
        return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
    }

    $decoded = validatejwt($headers);
    $createdBy = $decoded->user_id ?? 'system';
    $request = $this->request->getJSON(true);

    // Generate unique project_code
    $lastProject = $this->db->table('tbl_project_mst')
        ->select('project_code')
        ->like('project_code', 'PROJCPL')
        ->orderBy('project_code', 'DESC')
        ->get(1)
        ->getRow();

    $nextNumber = 1;
    if ($lastProject && preg_match('/PROJCPL(\d+)/', $lastProject->project_code, $matches)) {
        $nextNumber = intval($matches[1]) + 1;
    }
    $newProjectCode = 'PROJCPL' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    // Updated Validation Rules - Added client_code
    $validation = \Config\Services::validation();
    $validation->setRules([
        'project_name' => 'required|max_length[255]',
        'description' => 'permit_empty',
        'client_code' => 'required|max_length[50]', // Changed from client_name to client_code
        'client_name' => 'permit_empty|max_length[255]', // Still accept for backward compatibility
        'client_contact' => 'permit_empty|max_length[100]', // Still accept
        'start_date' => 'required|valid_date',
        'end_date' => 'required|valid_date',
        'status' => 'permit_empty|in_list[Planning,In Progress,UAT,Completed]',
        'budget' => 'permit_empty|decimal',
        'team_lead' => 'required|max_length[50]',
        'company_code' => 'required|max_length[50]',
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
    }

    // Check if client_code exists in tbl_client_mst
    $clientExists = $this->db->table('tbl_client_mst')->where('client_code', $request['client_code'])->countAllResults() > 0;
    if (!$clientExists) {
        return $this->respond(['status' => false, 'message' => ['client_code' => 'Provided client_code does not exist.']], 400);
    }

    // Check if team_lead exists in tbl_register
    $teamLeadExists = $this->db->table('tbl_register')->where('user_code', $request['team_lead'])->countAllResults() > 0;
    if (!$teamLeadExists) {
        return $this->respond(['status' => false, 'message' => ['team_lead' => 'Provided team_lead does not exist.']], 400);
    }

    // Check if company_code exists in tbl_company
    $companyExists = $this->db->table('tbl_company')->where('company_code', $request['company_code'])->countAllResults() > 0;
    if (!$companyExists) {
        return $this->respond(['status' => false, 'message' => ['company_code' => 'Provided company_code does not exist.']], 400);
    }

    $startDate = new DateTime($request['start_date']);
    $endDate = new DateTime($request['end_date']);

    if ($endDate < $startDate) {
        return $this->respond([
            'status' => false,
            'message' => ['end_date' => 'The end date must be on or after the start date.']
        ], 400);
    }

    // Get client details from client_code
    $clientDetails = $this->db->table('tbl_client_mst')
        ->select('client_name, contact_person, email, phone_number, location')
        ->where('client_code', $request['client_code'])
        ->get()
        ->getRow();

    $insertData = [
        'project_code' => $newProjectCode,
        'project_name' => $request['project_name'],
        'description' => $request['description'] ?? null,
        'client_code' => $request['client_code'], // Store client_code
        'client_name' => $clientDetails->client_name ?? ($request['client_name'] ?? null), // Fallback to provided name
        'client_contact' => $clientDetails->phone_number ?? ($request['client_contact'] ?? null), // Get from client or fallback
        'start_date' => $request['start_date'],
        'end_date' => $request['end_date'],
        'status' => $request['status'] ?? 'Planning', // Changed default to 'Planning'
        'budget' => $request['budget'] ?? null,
        'team_lead' => $request['team_lead'],
        'company_code' => $request['company_code'],
        'is_active' => 'Y',
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $createdBy,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $createdBy,
    ];

    $this->db->transStart();
    try {
        $builder = $this->db->table('tbl_project_mst');
        if ($builder->insert($insertData)) {
            $this->db->transComplete();
            return $this->respond([
                'status' => true,
                'message' => 'Project added successfully.',
                'project_code' => $newProjectCode
            ]);
        } else {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Failed to add project to database.'], 500);
        }
    } catch (DatabaseException $e) {
        $this->db->transRollback();
        return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        $this->db->transRollback();
        return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    }
}

public function updateProject()
{
    helper(['jwtvalidate_helper']);
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    if (!validatejwt($headers)) {
        return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
    }

    $decoded = validatejwt($headers);
    $updatedBy = $decoded->user_id ?? 'system';
    $request = $this->request->getJSON(true);

    // Updated Validation Rules
    $validation = \Config\Services::validation();
    $validation->setRules([
        'project_code' => 'required|max_length[50]|is_not_unique[tbl_project_mst.project_code]',
        'project_name' => 'permit_empty|max_length[255]',
        'description' => 'permit_empty',
        'client_code' => 'permit_empty|max_length[50]', // Added client_code
        'client_name' => 'permit_empty|max_length[255]',
        'client_contact' => 'permit_empty|max_length[100]',
        'start_date' => 'permit_empty|valid_date',
        'end_date' => 'permit_empty|valid_date',
        'status' => 'permit_empty|in_list[Planning,In Progress,UAT,Completed]',
        'budget' => 'permit_empty|decimal',
        'team_lead' => 'permit_empty|max_length[50]',
        'company_code' => 'permit_empty|max_length[50]',
        'is_active' => 'permit_empty|in_list[Y,N]',
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
    }

    $projectCode = $request['project_code'];
    $updateData = [
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $updatedBy,
    ];

    if (isset($request['project_name'])) $updateData['project_name'] = $request['project_name'];
    if (isset($request['description'])) $updateData['description'] = $request['description'];
    if (isset($request['start_date'])) $updateData['start_date'] = $request['start_date'];
    if (isset($request['end_date'])) $updateData['end_date'] = $request['end_date'];
    if (isset($request['status'])) $updateData['status'] = $request['status'];
    if (isset($request['budget'])) $updateData['budget'] = $request['budget'];
    if (isset($request['is_active'])) $updateData['is_active'] = $request['is_active'];

    if (isset($request['client_code'])) {
        // Check if client_code exists
        $clientExists = $this->db->table('tbl_client_mst')->where('client_code', $request['client_code'])->countAllResults() > 0;
        if (!$clientExists) {
            return $this->respond(['status' => false, 'message' => ['client_code' => 'Provided client_code does not exist.']], 400);
        }
        $updateData['client_code'] = $request['client_code'];
        
        // Update client_name and client_contact from the client record
        $clientDetails = $this->db->table('tbl_client_mst')
            ->select('client_name, contact_person, email, phone_number, location')
            ->where('client_code', $request['client_code'])
            ->get()
            ->getRow();
        
        if ($clientDetails) {
            $updateData['client_name'] = $clientDetails->client_name;
            $updateData['client_contact'] = $clientDetails->phone_number ?? null;
        }
    }
    
    // Allow manual override of client_name and client_contact if provided separately
    if (isset($request['client_name'])) $updateData['client_name'] = $request['client_name'];
    if (isset($request['client_contact'])) $updateData['client_contact'] = $request['client_contact'];

    if (isset($request['team_lead'])) {
        $teamLeadExists = $this->db->table('tbl_register')->where('user_code', $request['team_lead'])->countAllResults() > 0;
        if (!$teamLeadExists) {
            return $this->respond(['status' => false, 'message' => ['team_lead' => 'Provided team_lead does not exist.']], 400);
        }
        $updateData['team_lead'] = $request['team_lead'];
    }
    if (isset($request['company_code'])) {
        $companyExists = $this->db->table('tbl_company')->where('company_code', $request['company_code'])->countAllResults() > 0;
        if (!$companyExists) {
            return $this->respond(['status' => false, 'message' => ['company_code' => 'Provided company_code does not exist.']], 400);
        }
        $updateData['company_code'] = $request['company_code'];
    }

    // Date consistency checks (same as before)
    if (isset($updateData['start_date']) && isset($updateData['end_date'])) {
        $startDate = new DateTime($updateData['start_date']);
        $endDate = new DateTime($updateData['end_date']);
        if ($endDate < $startDate) {
            return $this->respond([
                'status' => false,
                'message' => ['end_date' => 'The end date must be on or after the start date.']
            ], 400);
        }
    } elseif (isset($updateData['start_date']) && !isset($updateData['end_date'])) {
        $existingProject = $this->db->table('tbl_project_mst')->select('end_date')->where('project_code', $projectCode)->get()->getRow();
        if ($existingProject && $existingProject->end_date) {
            $startDate = new DateTime($updateData['start_date']);
            $endDate = new DateTime($existingProject->end_date);
            if ($endDate < $startDate) {
                return $this->respond([
                    'status' => false,
                    'message' => ['start_date' => 'The new start date cannot be after the existing end date.']
                ], 400);
            }
        }
    } elseif (!isset($updateData['start_date']) && isset($updateData['end_date'])) {
        $existingProject = $this->db->table('tbl_project_mst')->select('start_date')->where('project_code', $projectCode)->get()->getRow();
        if ($existingProject && $existingProject->start_date) {
            $startDate = new DateTime($existingProject->start_date);
            $endDate = new DateTime($updateData['end_date']);
            if ($endDate < $startDate) {
                return $this->respond([
                    'status' => false,
                    'message' => ['end_date' => 'The new end date cannot be before the existing start date.']
                ], 400);
            }
        }
    }

    // Ensure there's something to update besides timestamps
    if (count($updateData) <= 2) {
        return $this->respond(['status' => false, 'message' => 'No fields provided for update.'], 400);
    }

    $this->db->transStart();
    try {
        $builder = $this->db->table('tbl_project_mst');
        $builder->where('project_code', $projectCode);

        if ($builder->update($updateData)) {
            $this->db->transComplete();
            return $this->respond([
                'status' => true,
                'message' => 'Project updated successfully.',
                'project_code' => $projectCode
            ]);
        } else {
            $this->db->transRollback();
            if ($this->db->affectedRows() === 0) {
                return $this->respond(['status' => false, 'message' => 'Project not found or no changes were made.'], 404);
            }
            return $this->respond(['status' => false, 'message' => 'Failed to update project in database.'], 500);
        }
    } catch (DatabaseException $e) {
        $this->db->transRollback();
        return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        $this->db->transRollback();
        return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    }
}

public function getAllProjects()
{
    helper(['jwtvalidate_helper']);
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    if (!validatejwt($headers)) {
        return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
    }

    $decoded = validatejwt($headers);
    if (!$decoded) {
        return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
    }

    $request = $this->request->getJSON(true);
    $project_code = $request['project_code'] ?? null;

    try {
        // Updated query to join with client table if needed for additional client details
        $builder = $this->db->table('tbl_project_mst AS tpm')
            ->select('tpm.*, 
                tc.contact_person AS client_contact_person,
                tc.email AS client_email,
                tc.location AS client_location,
                tc.phone_number AS client_phone_number,
                CONCAT(tr.first_name, " ", tr.last_name) AS team_lead_name,
                tc2.company_name')
            ->join('tbl_client_mst AS tc', 'tpm.client_code = tc.client_code', 'left')
            ->join('tbl_register AS tr', 'tpm.team_lead = tr.user_code', 'left')
            ->join('tbl_company AS tc2', 'tpm.company_code = tc2.company_code', 'left')
            ->where('tpm.is_active', 'Y');

        if ($project_code) {
            $data = $builder->where('tpm.project_code', $project_code)->get()->getRow();
            if ($data) {
                return $this->respond(['status' => true, 'data' => $data]);
            } else {
                return $this->respond(['status' => false, 'message' => 'Project not found.'], 404);
            }
        } else {
            $data = $builder->get()->getResult();
            return $this->respond(['status' => true, 'data' => $data]);
        }
    } catch (DatabaseException $e) {
        return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    }
}

    public function addTask()
    {
        helper(['form', 'url', 'jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? 'system';

        // Check Content-Type to determine how to get request data
        $contentType = $this->request->getHeaderLine('Content-Type');
        $isJson = strpos($contentType, 'application/json') !== false;
        $isMultipart = strpos($contentType, 'multipart/form-data') !== false;

        if ($isJson) {
            $requestData = $this->request->getJSON(true);
            if (!$requestData) {
                return $this->respond(['status' => false, 'message' => 'Invalid JSON input format.'], 400);
            }
        } elseif ($isMultipart) {
            $requestData = $this->request->getPost();
        } else {
            // Default to getPost if content type is not explicitly JSON or Multipart
            $requestData = $this->request->getPost();
        }

        // Generate unique task_code
        $lastTask = $this->db->table('tbl_task_mst')
            ->select('task_code')
            ->like('task_code', 'TASKCPL')
            ->orderBy('task_code', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($lastTask && preg_match('/TASKCPL(\d+)/', $lastTask->task_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $newTaskCode = 'TASKCPL' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Validation Rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'task_name' => 'required|max_length[255]',
            'description' => 'permit_empty',
            'project_code' => 'required|max_length[50]',
            'assigned_to' => 'required|max_length[50]',
            'start_date' => 'required|valid_date',
            'end_date' => 'required|valid_date',
            'priority' => 'permit_empty|in_list[High,Medium,Low]',
            'status' => 'permit_empty|in_list[Pending,In Progress,Completed,On Hold]',
            'progress' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
            // 'attachments[]' => 'permit_empty|uploaded[attachments]|max_size[attachments,2048]|ext_in[attachments,png,jpg,jpeg,pdf,doc,docx,xls,xlsx,zip]', // Array of files
        ]);

        // Validate based on the determined requestData
        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        // Access data from $requestData
        $projectCode = $requestData['project_code'] ?? null;
        $assignedTo = $requestData['assigned_to'] ?? null;
        $startDate = $requestData['start_date'] ?? null;
        $endDate = $requestData['end_date'] ?? null;


        // Check if project_code exists
        if (empty($projectCode)) {
            return $this->respond(['status' => false, 'message' => ['project_code' => 'project_code is required.']], 400);
        }
        $projectExists = $this->db->table('tbl_project_mst')->where('project_code', $projectCode)->countAllResults() > 0;
        if (!$projectExists) {
            return $this->respond(['status' => false, 'message' => ['project_code' => 'Provided project_code does not exist.']], 400);
        }

        // Check if assigned_to exists in tbl_register
        if (empty($assignedTo)) {
            return $this->respond(['status' => false, 'message' => ['assigned_to' => 'assigned_to is required.']], 400);
        }
        $assignedToExists = $this->db->table('tbl_register')->where('user_code', $assignedTo)->countAllResults() > 0;
        if (!$assignedToExists) {
            return $this->respond(['status' => false, 'message' => ['assigned_to' => 'Provided assigned_to user does not exist.']], 400);
        }

        // Date validation
        try {
            $dtStartDate = new DateTime($startDate);
            $dtEndDate = new DateTime($endDate);
            if ($dtEndDate < $dtStartDate) {
                return $this->respond([
                    'status' => false,
                    'message' => ['end_date' => 'The end date must be on or after the start date.']
                ], 400);
            }
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => ['date_format' => 'Invalid date format for start_date or end_date.']], 400);
        }


        $uploadedFiles = [];
        // Only process files if the request is multipart/form-data
        if ($isMultipart) {
            $files = $this->request->getFiles();
            if (isset($files['attachments'])) {
                $baseUploadDir = ROOTPATH . 'public/uploads/tasks/' . $newTaskCode . '/';
                if (!is_dir($baseUploadDir)) {
                    mkdir($baseUploadDir, 0777, true);
                }

                foreach ($files['attachments'] as $file) {
                    if ($file->isValid() && !$file->hasMoved()) {
                        $newName = $file->getRandomName();
                        $file->move($baseUploadDir, $newName);
                        $uploadedFiles[] = 'public/uploads/tasks/' . $newTaskCode . '/' . $newName;
                    } else {
                    }
                }
            }
        }


        $insertData = [
            'task_code' => $newTaskCode,
            'task_name' => $requestData['task_name'],
            'description' => $requestData['description'] ?? null,
            'project_code' => $projectCode,
            'assigned_to' => $assignedTo,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'priority' => $requestData['priority'] ?? 'Medium',
            'status' => $requestData['status'] ?? 'Pending',
            'progress' => $requestData['progress'] ?? 0,
            'attachments' => json_encode($uploadedFiles), // Store as JSON string
            'is_active' => 'Y',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $createdBy,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $createdBy,
        ];

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_task_mst');
            if ($builder->insert($insertData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Task added successfully.',
                    'task_code' => $newTaskCode
                ]);
            } else {
                $this->db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to add task to database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function updateTask()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $updatedBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true); // Assuming JSON body for updates

        // Validation Rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'task_code' => 'required|max_length[50]|is_not_unique[tbl_task_mst.task_code]',
            'task_name' => 'permit_empty|max_length[255]',
            'description' => 'permit_empty',
            'project_code' => 'permit_empty|max_length[50]',
            'assigned_to' => 'permit_empty|max_length[50]',
            'start_date' => 'permit_empty|valid_date',
            'end_date' => 'permit_empty|valid_date',
            'priority' => 'permit_empty|in_list[High,Medium,Low]',
            'status' => 'permit_empty|in_list[Pending,In Progress,Completed,On Hold]',
            'progress' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
            'attachments' => 'permit_empty|valid_json', // Expecting JSON string of paths
            'is_active' => 'permit_empty|in_list[Y,N]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $taskCode = $request['task_code'];
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updatedBy,
        ];

        if (isset($request['task_name'])) $updateData['task_name'] = $request['task_name'];
        if (isset($request['description'])) $updateData['description'] = $request['description'];
        if (isset($request['start_date'])) $updateData['start_date'] = $request['start_date'];
        if (isset($request['end_date'])) $updateData['end_date'] = $request['end_date'];
        if (isset($request['priority'])) $updateData['priority'] = $request['priority'];
        if (isset($request['status'])) $updateData['status'] = $request['status'];
        if (isset($request['progress'])) $updateData['progress'] = $request['progress'];
        if (isset($request['is_active'])) $updateData['is_active'] = $request['is_active'];

        if (isset($request['project_code'])) {
            $projectExists = $this->db->table('tbl_project_mst')->where('project_code', $request['project_code'])->countAllResults() > 0;
            if (!$projectExists) {
                return $this->respond(['status' => false, 'message' => ['project_code' => 'Provided project_code does not exist.']], 400);
            }
            $updateData['project_code'] = $request['project_code'];
        }
        if (isset($request['assigned_to'])) {
            $assignedToExists = $this->db->table('tbl_register')->where('user_code', $request['assigned_to'])->countAllResults() > 0;
            if (!$assignedToExists) {
                return $this->respond(['status' => false, 'message' => ['assigned_to' => 'Provided assigned_to user does not exist.']], 400);
            }
            $updateData['assigned_to'] = $request['assigned_to'];
        }

        if (isset($request['attachments'])) {
            // Ensure it's a valid JSON string or null
            if ($request['attachments'] === null || json_decode($request['attachments']) !== null) {
                $updateData['attachments'] = $request['attachments'];
            } else {
                return $this->respond(['status' => false, 'message' => ['attachments' => 'Attachments must be a valid JSON string or null.']], 400);
            }
        }

        // Date consistency check if both are provided for update
        if (isset($updateData['start_date']) && isset($updateData['end_date'])) {
            $startDate = new DateTime($updateData['start_date']);
            $endDate = new DateTime($updateData['end_date']);
            if ($endDate < $startDate) {
                return $this->respond([
                    'status' => false,
                    'message' => ['end_date' => 'The end date must be on or after the start date.']
                ], 400);
            }
        } elseif (isset($updateData['start_date']) && !isset($updateData['end_date'])) {
            // If only start_date is updated, check against existing end_date
            $existingTask = $this->db->table('tbl_task_mst')->select('end_date')->where('task_code', $taskCode)->get()->getRow();
            if ($existingTask && $existingTask->end_date) {
                $startDate = new DateTime($updateData['start_date']);
                $endDate = new DateTime($existingTask->end_date);
                if ($endDate < $startDate) {
                    return $this->respond([
                        'status' => false,
                        'message' => ['start_date' => 'The new start date cannot be after the existing end date.']
                    ], 400);
                }
            }
        } elseif (!isset($updateData['start_date']) && isset($updateData['end_date'])) {
            // If only end_date is updated, check against existing start_date
            $existingTask = $this->db->table('tbl_task_mst')->select('start_date')->where('task_code', $taskCode)->get()->getRow();
            if ($existingTask && $existingTask->start_date) {
                $startDate = new DateTime($existingTask->start_date);
                $endDate = new DateTime($updateData['end_date']);
                if ($endDate < $startDate) {
                    return $this->respond([
                        'status' => false,
                        'message' => ['end_date' => 'The new end date cannot be before the existing start date.']
                    ], 400);
                }
            }
        }

        // Ensure there's something to update besides timestamps
        if (count($updateData) <= 2) {
            return $this->respond(['status' => false, 'message' => 'No fields provided for update.'], 400);
        }

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_task_mst');
            $builder->where('task_code', $taskCode);

            if ($builder->update($updateData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Task updated successfully.',
                    'task_code' => $taskCode
                ]);
            } else {
                $this->db->transRollback();
                if ($this->db->affectedRows() === 0) {
                    return $this->respond(['status' => false, 'message' => 'Task not found or no changes were made.'], 404);
                }
                return $this->respond(['status' => false, 'message' => 'Failed to update task in database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function getAllTasks()
    {
        helper(['jwtvalidate_helper', 'url']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request = $this->request->getJSON(true);
        $task_code = $request['task_code'] ?? null;
        $project_code = $request['project_code'] ?? null;
        $assigned_to = $request['assigned_to'] ?? null;

        try {
            $builder = $this->db->table('tbl_task_mst AS ttm')
                ->select('ttm.*, 
              tpm.project_name, tpm.description AS project_description, 
              tpm.client_name, tpm.client_contact, tpm.start_date AS project_start_date, 
              tpm.end_date AS project_end_date, tpm.status AS project_status, 
              tpm.budget, tpm.team_lead, tpm.company_code,
              CONCAT(tr.first_name, " ", tr.last_name) AS assigned_to_name') // Add full name
                ->join('tbl_project_mst AS tpm', 'ttm.project_code = tpm.project_code', 'left')
                ->join('tbl_register AS tr', 'ttm.assigned_to = tr.user_code', 'left') // Join tbl_register
                ->where('ttm.is_active', 'Y')
                ->where('tpm.is_active', 'Y');

            if ($task_code) {
                $builder->where('ttm.task_code', $task_code);
            }
            if ($project_code) {
                $builder->where('ttm.project_code', $project_code);
            }
            if ($assigned_to) {
                $builder->where('ttm.assigned_to', $assigned_to);
            }

            $data = ($task_code) ? $builder->get()->getRow() : $builder->get()->getResult();

            if ($data) {
                // Ensure data is an array for consistent processing if only one row is returned
                $results = is_array($data) ? $data : [$data];

                foreach ($results as $record) {
                    if (!empty($record->attachments)) {
                        $attachmentPaths = json_decode($record->attachments, true);
                        if (is_array($attachmentPaths)) {
                            $fullUrls = [];
                            foreach ($attachmentPaths as $path) {
                                $fullUrls[] = base_url($path);
                            }
                            $record->attachments = $fullUrls;
                        } else {
                            $record->attachments = []; // Invalid JSON, treat as empty
                        }
                    } else {
                        $record->attachments = []; // No attachments
                    }
                }
                return $this->respond(['status' => true, 'data' => is_array($data) ? $results : $results[0]]);
            } else {
                return $this->respond(['status' => false, 'message' => 'Task(s) not found.'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
 public function getAllTasksforuser()
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
        return $this->respond(['status' => false, 'message' => 'User ID not found in token'], 400);
    }
    $request = $this->request->getJSON(true);
    $task_code = $request['task_code'] ?? null;
    $project_code = $request['project_code'] ?? null;
    $assigned_to = $request['assigned_to'] ?? null;
    try {
        $builder = $this->db->table('tbl_task_mst AS ttm')
            ->select('ttm.*, 
                tpm.project_name, tpm.description AS project_description, 
                tpm.client_name, tpm.client_contact, tpm.start_date AS project_start_date, 
                tpm.end_date AS project_end_date, tpm.status AS project_status, 
                tpm.budget, tpm.team_lead, tpm.company_code,
                CONCAT(tr.first_name, " ", tr.last_name) AS assigned_to_name')
            ->join('tbl_project_mst AS tpm', 'ttm.project_code = tpm.project_code', 'left')
            ->join('tbl_register AS tr', 'ttm.assigned_to = tr.user_code', 'left')
            ->where('ttm.is_active', 'Y')
            ->where('tpm.is_active', 'Y')
            ->where('ttm.assigned_to', $userCode);
        if ($task_code) {
            $builder->where('ttm.task_code', $task_code);
        }
        if ($project_code) {
            $builder->where('ttm.project_code', $project_code);
        }
        if ($assigned_to) {
            $builder->where('ttm.assigned_to', $assigned_to);
        }
        $today = date('Y-m-d');
        $builder->groupStart()
            ->where('ttm.start_date !=', $today)
            ->orGroupStart()
                ->where('ttm.start_date', $today) 
                ->where('ttm.status !=', 'Completed')
            ->groupEnd()
        ->groupEnd();
        $data = ($task_code) ? $builder->get()->getRow() : $builder->get()->getResult();
        $countBuilder = $this->db->table('tbl_task_mst')
            ->where('assigned_to', $userCode)
            ->where('is_active', 'Y')
            ->where('status !=', 'Completed')
            ->where('end_date >=', $today);
        $pendingCount = $countBuilder->countAllResults();
        if ($data) {
            $results = is_array($data) ? $data : [$data];
            foreach ($results as $record) {
                if (!empty($record->attachments)) {
                    $attachmentPaths = json_decode($record->attachments, true);
                    $record->attachments = is_array($attachmentPaths) ? array_map('base_url', $attachmentPaths) : [];
                } else {
                    $record->attachments = [];
                }
            }
            return $this->respond([
                'status' => true,
                'pending_count' => $pendingCount,
                'data' => is_array($data) ? $results : $results[0]
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'pending_count' => $pendingCount,
                'message' => 'Task(s) not found.'
            ], 404);
        }
    } catch (DatabaseException $e) {
        return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        return $this->respond(['status' => false, 'message' => 'Unexpected error: ' . $e->getMessage()], 500);
    }
}


}
