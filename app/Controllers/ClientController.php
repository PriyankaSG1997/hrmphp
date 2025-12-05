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
use Config\Database;
use DateTimeZone;
use Config\App;

class ClientController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = Database::connect();
    }


    public function addClient()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true);

        // Generate unique client_code (e.g., CLICPL001)
        $lastClient = $this->db->table('tbl_client_mst')
            ->select('client_code')
            ->like('client_code', 'CLICPL')
            ->orderBy('client_code', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($lastClient && preg_match('/CLICPL(\d+)/', $lastClient->client_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $newClientCode = 'CLICPL' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Validation Rules based on tbl_client_mst structure
        $validation = \Config\Services::validation();
        $validation->setRules([
            'client_name' => 'required',
            'location' => 'permit_empty',
            'contact_person' => 'permit_empty',
            'email' => 'permit_empty|valid_email',
            'phone_number' => 'permit_empty|max_length[20]',
            'division_name' => 'permit_empty',
            'contact_designation' => 'required',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $insertData = [
            'client_code' => $newClientCode,
            'client_name' => $request['client_name'],
            'location' => $request['location'],
            'contact_person' => $request['contact_person'] ?? null,
            'email' => $request['email'] ?? null,
            'phone_number' => $request['phone_number'] ?? null,
            'division_name' => $request['division_name'] ?? null,
            'contact_designation' => $request['contact_designation'] ?? null,
            'is_active' => 'Y',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $createdBy,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $createdBy,
        ];

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_client_mst');
            if ($builder->insert($insertData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Client added successfully.',
                    'client_code' => $newClientCode
                ]);
            } else {
                $this->db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to add client to database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function updateClient()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $updatedBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true);

        // Validation Rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'client_code' => 'required|max_length[10]|is_not_unique[tbl_client_mst.client_code]', // Ensure code exists
            'client_name' => 'permit_empty',
            'location' => 'permit_empty',
            // 'company_incorporation_date' => 'permit_empty|valid_date',
            // 'zone' => 'permit_empty|max_length[50]',
            // 'proposal_land_type' => 'permit_empty|max_length[100]',
            // 'bid_type' => 'permit_empty|max_length[50]',
            // 'process_person' => 'permit_empty|max_length[100]',
            'contact_person' => 'permit_empty',
            'email' => 'permit_empty|valid_email',
            'phone_number' => 'permit_empty|max_length[20]',
            'division_name' => 'permit_empty',
            'is_active' => 'permit_empty|in_list[Y,N]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $clientCode = $request['client_code'];
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updatedBy,
        ];

        if (isset($request['client_name'])) $updateData['client_name'] = $request['client_name'];
        if (isset($request['contact_designation'])) $updateData['contact_designation'] = $request['contact_designation'];
        if (isset($request['location'])) $updateData['location'] = $request['location'];
        if (isset($request['company_incorporation_date'])) $updateData['company_incorporation_date'] = $request['company_incorporation_date'];
        if (isset($request['zone'])) $updateData['zone'] = $request['zone'];
        if (isset($request['proposal_land_type'])) $updateData['proposal_land_type'] = $request['proposal_land_type'];
        if (isset($request['bid_type'])) $updateData['bid_type'] = $request['bid_type'];
        if (isset($request['process_person'])) $updateData['process_person'] = $request['process_person'];
        if (isset($request['contact_person'])) $updateData['contact_person'] = $request['contact_person'];
        if (isset($request['email'])) $updateData['email'] = $request['email'];
        if (isset($request['phone_number'])) $updateData['phone_number'] = $request['phone_number'];
        if (isset($request['is_active'])) $updateData['is_active'] = $request['is_active'];
        if (isset($request['division_name'])) $updateData['division_name'] = $request['division_name'];

        // Ensure there's something to update besides timestamps
        if (count($updateData) <= 2) { // updated_at and updated_by are always there
            return $this->respond(['status' => false, 'message' => 'No fields provided for update.'], 400);
        }

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_client_mst');
            $builder->where('client_code', $clientCode);

            if ($builder->update($updateData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Client updated successfully.',
                    'client_code' => $clientCode
                ]);
            } else {
                $this->db->transRollback();
                if ($this->db->affectedRows() === 0) {
                    return $this->respond(['status' => false, 'message' => 'Client not found or no changes were made.'], 404);
                }
                return $this->respond(['status' => false, 'message' => 'Failed to update client in database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function getAllClients()
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
        $client_code = $request['client_code'] ?? null;

        try {
            $builder = $this->db->table('tbl_client_mst');
            $builder->where('is_active', 'Y');

            if ($client_code) {
                $data = $builder->where('client_code', $client_code)->get()->getRow();
                if ($data) {
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Client not found.'], 404);
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

    public function addInitialClientInteraction()
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
        $requestData = $this->request->getJSON(true);
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_initial_client_interactions');
        $meetingCode = 'MTC' . date('YmdHis');
        $data = [
            'meeting_code' => $meetingCode,
            'client_name' => $requestData['client_name'] ?? null,
            'client_code' => $requestData['client_code'] ?? null,
            'projected_investment' => $requestData['projected_investment'] ?? 0,
            'category_id' => $requestData['category_id'] ?? null,
            'schemes_ids' => $requestData['schemes_ids'] ?? null,
            'project_finance' => $requestData['project_finance'] ?? null,
            'Land_related_services' => $requestData['Land_related_services'] ?? null,
            'location' => $requestData['location'] ?? null,
            'meeting_date' => $requestData['meeting_date'] ?? null,
            'meeting_time' => $requestData['meeting_time'] ?? null,
            'contact_person' => $requestData['contact_person'] ?? null,
            'contact_number' => $requestData['contact_number'] ?? null,
            'marketing_executive_id' => $requestData['marketing_executive_id'] ?? null,
            'created_by' => $userCode
        ];

        if ($builder->insert($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Client meeting scheduled successfully.',
                'meeting_code' => $meetingCode
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to schedule client meeting.'
            ], 500);
        }
    }
    public function editInitialClientInteraction()
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

        $requestData = $this->request->getJSON(true);
        $userCode = $decodedToken->user_id ?? null;
        $meeting_code = $requestData['meeting_code'] ?? null;

        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }

        if (!$meeting_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Meeting code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_initial_client_interactions');

        $data = [
            'client_name' => $requestData['client_name'] ?? null,
            'client_code' => $requestData['client_code'] ?? null,
            'projected_investment' => $requestData['projected_investment'] ?? 0,
            'category_id' => $requestData['category_id'] ?? null,
            'schemes_ids' => $requestData['schemes_ids'] ?? null,
            'project_finance' => $requestData['project_finance'] ?? null,
            'Land_related_services' => $requestData['Land_related_services'] ?? null,
            'location' => $requestData['location'] ?? null,
            'meeting_date' => $requestData['meeting_date'] ?? null,
            'meeting_time' => $requestData['meeting_time'] ?? null,
            'contact_person' => $requestData['contact_person'] ?? null,
            'contact_number' => $requestData['contact_number'] ?? null,
            'marketing_executive_id' => $requestData['marketing_executive_id'] ?? null,
            'updated_by' => $userCode
        ];

        $builder->where('meeting_code', $meeting_code);
        if ($builder->update($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Client meeting updated successfully.'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update client meeting.'
            ], 500);
        }
    }
    // public function getAllClientsProcessing_person()
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
    //     $userCode = $decodedToken->user_id ?? null;
    //     if (!$userCode) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'User code not found in token'
    //         ], 400);
    //     }
    //     $db = \Config\Database::connect();
    //     $userQuery = $db->table('tbl_register')
    //         ->select('first_name, last_name')
    //         ->where('user_code', $userCode)
    //         ->get()
    //         ->getRowArray();
    //     $fullName = null;
    //     if ($userQuery) {
    //         $fullName = trim(($userQuery['first_name'] ?? '') . ' ' . ($userQuery['last_name'] ?? ''));
    //     }
    //     $builder = $db->table('tbl_initial_client_interactions ic');
    //     $builder->select('
    //     ic.*, 
    //     c.user_name AS created_by_name
    // ');
    //     $builder->join('tbl_login c', 'c.user_code_ref = ic.created_by', 'left');
    //     $builder->where('ic.marketing_executive_id', $userCode);
    //     $builder->where('ic.is_active', 'Y');
    //     $query = $builder->get();
    //     $results = $query->getResultArray();

    //     if (empty($results)) {
    //         return $this->respond([
    //             'status' => true,
    //             'message' => 'No client interactions found',
    //             'user_name' => $fullName,
    //             'data' => []
    //         ]);
    //     }
    //     $schemeTable = $db->table('tbl_scheme');
    //     $allSchemes = $schemeTable->select('scheme_id, scheme_name')->get()->getResultArray();
    //     $schemeMap = [];
    //     foreach ($allSchemes as $scheme) {
    //         $schemeMap[$scheme['scheme_id']] = $scheme['scheme_name'];
    //     }
    //     $catTable = $db->table('tbl_scheme_categories');
    //     $allCategories = $catTable->select('id, category_name')->get()->getResultArray();
    //     $categoryMap = [];
    //     foreach ($allCategories as $cat) {
    //         $categoryMap[$cat['id']] = $cat['category_name'];
    //     }
    //     foreach ($results as &$row) {
    //         $row['marketing_executive_name'] = $fullName;

    //         $catId = $row['category_id'] ?? null;
    //         $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;

    //         $schemesIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
    //         $row['schemes_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
    //             return $schemeMap[trim($id)] ?? null;
    //         }, $schemesIds));

    //         $projectFinanceIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
    //         $row['project_finance_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
    //             return $schemeMap[trim($id)] ?? null;
    //         }, $projectFinanceIds));

    //         $landServiceIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
    //         $row['land_related_services_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
    //             return $schemeMap[trim($id)] ?? null;
    //         }, $landServiceIds));
    //     }

    //     return $this->respond([
    //         'status' => true,
    //         'message' => 'Clients fetched successfully',
    //         'user_name' => $fullName,
    //         'data' => $results
    //     ]);
    // }
    public function getAllClientsProcessing_person()
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
                'message' => 'User code not found in token'
            ], 400);
        }

        $db = \Config\Database::connect();

        // Get user's full name
        $userQuery = $db->table('tbl_register')
            ->select('first_name, last_name')
            ->where('user_code', $userCode)
            ->get()
            ->getRowArray();
        $fullName = $userQuery ? trim(($userQuery['first_name'] ?? '') . ' ' . ($userQuery['last_name'] ?? '')) : null;

        // Get client interactions
        $builder = $db->table('tbl_initial_client_interactions ic');
        $builder->select('ic.*, c.user_name AS created_by_name');
        $builder->join('tbl_login c', 'c.user_code_ref = ic.created_by', 'left');
        $builder->where('ic.marketing_executive_id', $userCode);
        $builder->where('ic.is_active', 'Y');
        $results = $builder->get()->getResultArray();

        if (empty($results)) {
            return $this->respond([
                'status' => true,
                'message' => 'No client interactions found',
                'user_name' => $fullName,
                'data' => []
            ]);
        }

        // Fetch schemes
        $schemeMap = [];
        foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $scheme) {
            $schemeMap[$scheme['scheme_id']] = $scheme['scheme_name'];
        }

        // Fetch categories
        $categoryMap = [];
        foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $cat) {
            $categoryMap[$cat['id']] = $cat['category_name'];
        }

        // Fetch client master data for client_name and converts
        $clientMap = [];
        foreach ($db->table('tbl_client_mst')->select('client_code, client_name, converts')->get()->getResultArray() as $c) {
            $clientMap[$c['client_code']] = [
                'client_name' => $c['client_name'],
                'converts' => $c['converts']
            ];
        }

        // Process results
        foreach ($results as &$row) {
            $row['marketing_executive_name'] = $fullName;

            // Category
            $catId = $row['category_id'] ?? null;
            $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;

            // Schemes
            $schemesIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
            $row['schemes_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $schemesIds));

            // Project Finance
            $projectFinanceIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
            $row['project_finance_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $projectFinanceIds));

            // Land Services
            $landServiceIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
            $row['land_related_services_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $landServiceIds));

            // Client Name and Converts
            $clientCode = $row['client_code'] ?? null;
            if ($clientCode && isset($clientMap[$clientCode])) {
                $row['client_name'] = $clientMap[$clientCode]['client_name'];
                $row['client_converts'] = $clientMap[$clientCode]['converts'];
            } else {
                $row['client_name'] = null;
                $row['client_converts'] = null;
            }
        }

        return $this->respond([
            'status' => true,
            'message' => 'Clients fetched successfully',
            'user_name' => $fullName,
            'data' => $results
        ]);
    }

    // public function getAllClientstocreatedperson()
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
    //     $userCode = $decodedToken->user_id ?? null;
    //     if (!$userCode) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'User code not found in token'
    //         ], 400);
    //     }
    //     $db = \Config\Database::connect();
    //     $userQuery = $db->table('tbl_register')
    //         ->select('first_name, last_name')
    //         ->where('user_code', $userCode)
    //         ->get()
    //         ->getRowArray();

    //     $fullName = $userQuery ? trim(($userQuery['first_name'] ?? '') . ' ' . ($userQuery['last_name'] ?? '')) : null;
    //     $builder = $db->table('tbl_initial_client_interactions ic');
    //     $builder->select('ic.*');
    //     $builder->where('ic.created_by', $userCode);
    //     $builder->where('ic.is_active', 'Y');
    //     $query = $builder->get();
    //     $results = $query->getResultArray();
    //     if (empty($results)) {
    //         return $this->respond([
    //             'status' => true,
    //             'message' => 'No client interactions found',
    //             'user_name' => $fullName,
    //             'data' => []
    //         ]);
    //     }
    //     $allUsers = $db->table('tbl_register')
    //         ->select('user_code, first_name, last_name')
    //         ->get()
    //         ->getResultArray();
    //     $userNameMap = [];
    //     foreach ($allUsers as $user) {
    //         $userNameMap[$user['user_code']] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    //     }
    //     $schemeMap = [];
    //     foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $s) {
    //         $schemeMap[$s['scheme_id']] = $s['scheme_name'];
    //     }
    //     $categoryMap = [];
    //     foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $c) {
    //         $categoryMap[$c['id']] = $c['category_name'];
    //     }
    //     foreach ($results as &$row) {
    //         $row['marketing_executive_name'] = $row['marketing_executive_id'] ? ($userNameMap[$row['marketing_executive_id']] ?? null) : null;
    //         $row['created_by_name'] = $row['created_by'] ? ($userNameMap[$row['created_by']] ?? null) : null;
    //         $catId = $row['category_id'] ?? null;
    //         $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;
    //         $schemesIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
    //         $row['schemes_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $schemesIds));
    //         $projectFinanceIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
    //         $row['project_finance_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $projectFinanceIds));
    //         $landServiceIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
    //         $row['land_related_services_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $landServiceIds));
    //     }

    //     return $this->respond([
    //         'status' => true,
    //         'message' => 'Clients fetched successfully',
    //         'user_name' => $fullName,
    //         'data' => $results
    //     ]);
    // }
    public function getAllClientstocreatedperson()
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
                'message' => 'User code not found in token'
            ], 400);
        }

        $db = \Config\Database::connect();

        // ✅ Get logged-in user full name
        $userQuery = $db->table('tbl_register')
            ->select('first_name, last_name')
            ->where('user_code', $userCode)
            ->get()
            ->getRowArray();

        $fullName = $userQuery ? trim(($userQuery['first_name'] ?? '') . ' ' . ($userQuery['last_name'] ?? '')) : null;

        // ✅ Get client interactions created by the user
        $builder = $db->table('tbl_initial_client_interactions ic');
        $builder->select('ic.*');
        $builder->where('ic.created_by', $userCode);
        $builder->where('ic.is_active', 'Y');
        $results = $builder->get()->getResultArray();

        if (empty($results)) {
            return $this->respond([
                'status' => true,
                'message' => 'No client interactions found',
                'user_name' => $fullName,
                'data' => []
            ]);
        }

        // ✅ Map all user names
        $allUsers = $db->table('tbl_register')
            ->select('user_code, first_name, last_name')
            ->get()
            ->getResultArray();
        $userNameMap = [];
        foreach ($allUsers as $user) {
            $userNameMap[$user['user_code']] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        }

        // ✅ Map all schemes
        $schemeMap = [];
        foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $s) {
            $schemeMap[$s['scheme_id']] = $s['scheme_name'];
        }

        // ✅ Map all categories
        $categoryMap = [];
        foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $c) {
            $categoryMap[$c['id']] = $c['category_name'];
        }

        // ✅ Map all clients (client_code → client_name, converts)
        $clientMap = [];
        $clientList = $db->table('tbl_client_mst')
            ->select('client_code, client_name, converts')
            ->get()
            ->getResultArray();
        foreach ($clientList as $c) {
            $clientMap[$c['client_code']] = [
                'client_name' => $c['client_name'],
                'converts' => $c['converts']
            ];
        }

        // ✅ Attach mapped details to each result
        foreach ($results as &$row) {
            $row['marketing_executive_name'] = $row['marketing_executive_id'] ? ($userNameMap[$row['marketing_executive_id']] ?? null) : null;
            $row['created_by_name'] = $row['created_by'] ? ($userNameMap[$row['created_by']] ?? null) : null;

            // Category name
            $catId = $row['category_id'] ?? null;
            $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;

            // Scheme details
            $schemesIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
            $row['schemes_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $schemesIds));

            // Project finance details
            $projectFinanceIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
            $row['project_finance_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $projectFinanceIds));

            // Land services details
            $landServiceIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
            $row['land_related_services_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $landServiceIds));

            // ✅ Add client name & converts
            $clientCode = $row['client_code'] ?? null;
            $row['client_name'] = $clientCode ? ($clientMap[$clientCode]['client_name'] ?? null) : null;
            $row['converts'] = $clientCode ? ($clientMap[$clientCode]['converts'] ?? null) : null;
        }

        return $this->respond([
            'status' => true,
            'message' => 'Clients fetched successfully',
            'user_name' => $fullName,
            'data' => $results
        ]);
    }

    //    public function getAllClientsProcessing_persontoteamlead()
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

    //     $teamLeadCode = $decodedToken->user_id ?? null;
    //     if (!$teamLeadCode) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'User code not found in token'
    //         ], 400);
    //     }
    //     $db = \Config\Database::connect();
    //     try {
    //         $teamMembers = $db->table('tbl_register')
    //             ->select('user_code, first_name, last_name')
    //             ->where('team_lead_ref_code', $teamLeadCode)
    //             ->get()
    //             ->getResultArray();
    //         if (empty($teamMembers)) {
    //             return $this->respond([
    //                 'status' => true,
    //                 'message' => 'No team members found under this team lead',
    //                 'data' => []
    //             ]);
    //         }
    //         $teamMemberCodes = array_column($teamMembers, 'user_code');
    //         $teamMemberNames = [];
    //         foreach ($teamMembers as $member) {
    //             $teamMemberNames[$member['user_code']] = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    //         }
    //         $builder = $db->table('tbl_initial_client_interactions');
    //         $builder->select('*');
    //         $builder->whereIn('marketing_executive_id', $teamMemberCodes);
    //         $builder->where('is_active', 'Y');
    //         $results = $builder->get()->getResultArray();
    //         if (empty($results)) {
    //             return $this->respond([
    //                 'status' => true,
    //                 'message' => 'No client interactions found for team members',
    //                 'data' => []
    //             ]);
    //         }
    //         $schemeMap = [];
    //         foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $s) {
    //             $schemeMap[$s['scheme_id']] = $s['scheme_name'];
    //         }
    //         $categoryMap = [];
    //         foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $c) {
    //             $categoryMap[$c['id']] = $c['category_name'];
    //         }
    //         $registerUsers = $db->table('tbl_register')
    //             ->select('user_code, first_name, last_name')
    //             ->get()
    //             ->getResultArray();
    //         $registerNameMap = [];
    //         foreach ($registerUsers as $user) {
    //             $registerNameMap[$user['user_code']] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    //         }
    //         foreach ($results as &$row) {
    //             $mId = $row['marketing_executive_id'] ?? null;
    //             $row['marketing_executive_name'] = $teamMemberNames[$mId] ?? null;
    //             $catId = $row['category_id'] ?? null;
    //             $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;
    //             $schemeIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
    //             $row['schemes_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $schemeIds));
    //             $pfIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
    //             $row['project_finance_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $pfIds));
    //             $landIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
    //             $row['land_related_services_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $landIds));
    //             $createdBy = $row['created_by'] ?? null;
    //             $row['created_by_name'] = $createdBy ? ($registerNameMap[$createdBy] ?? null) : null;
    //         }
    //         return $this->respond([
    //             'status' => true,
    //             'message' => 'Clients fetched successfully',
    //             'data' => $results
    //         ]);

    //     } catch (\Exception $e) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Error fetching clients',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function getAllClientsProcessing_persontoteamlead()
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
        $teamLeadCode = $decodedToken->user_id ?? null;
        if (!$teamLeadCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User code not found in token'
            ], 400);
        }
        $db = \Config\Database::connect();
        try {
            $teamMembers = $db->table('tbl_register')
                ->select('user_code, first_name, last_name')
                ->where('team_lead_ref_code', $teamLeadCode)
                ->get()
                ->getResultArray();
            if (empty($teamMembers)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'No team members found under this team lead',
                    'data' => []
                ]);
            }
            $teamMemberCodes = array_column($teamMembers, 'user_code');
            $teamMemberNames = [];
            foreach ($teamMembers as $member) {
                $teamMemberNames[$member['user_code']] = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
            }
            $builder = $db->table('tbl_initial_client_interactions');
            $builder->select('*');
            $builder->whereIn('marketing_executive_id', $teamMemberCodes);
            $builder->where('is_active', 'Y');
            $results = $builder->get()->getResultArray();
            if (empty($results)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'No client interactions found for team members',
                    'data' => []
                ]);
            }
            $schemeMap = [];
            foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $s) {
                $schemeMap[$s['scheme_id']] = $s['scheme_name'];
            }
            $categoryMap = [];
            foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $c) {
                $categoryMap[$c['id']] = $c['category_name'];
            }
            $registerUsers = $db->table('tbl_register')
                ->select('user_code, first_name, last_name')
                ->get()
                ->getResultArray();
            $registerNameMap = [];
            foreach ($registerUsers as $user) {
                $registerNameMap[$user['user_code']] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            }
            $clientMap = [];
            $clientMap = [];
            foreach ($db->table('tbl_client_mst')->select('client_code, client_name, converts')->get()->getResultArray() as $c) {
                $clientMap[$c['client_code']] = [
                    'client_name' => $c['client_name'],
                    'converts' => $c['converts']
                ];
            }

            foreach ($results as &$row) {
                $mId = $row['marketing_executive_id'] ?? null;
                $row['marketing_executive_name'] = $teamMemberNames[$mId] ?? null;
                $catId = $row['category_id'] ?? null;
                $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;
                $schemeIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
                $row['schemes_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $schemeIds));
                $pfIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
                $row['project_finance_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $pfIds));
                $landIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
                $row['land_related_services_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $landIds));
                $createdBy = $row['created_by'] ?? null;
                $row['created_by_name'] = $createdBy ? ($registerNameMap[$createdBy] ?? null) : null;
                $clientCode = $row['client_code'] ?? null;
                $row['client_name'] = $clientCode ? ($clientMap[$clientCode] ?? null) : null;
                // $row['client_converts'] = $clientMap[$clientCode]['converts'];
            }

            return $this->respond([
                'status' => true,
                'message' => 'Clients fetched successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error fetching clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function getAllClientsProcessing_forHOD()
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

    //     $hodCode = $decodedToken->user_id ?? null;
    //     if (!$hodCode) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'User code not found in token'
    //         ], 400);
    //     }

    //     $db = \Config\Database::connect();

    //     try {
    //         // 🔹 Step 1: Get all Team Leads under this HOD
    //         $teamLeads = $db->table('tbl_register')
    //             ->select('user_code, first_name, last_name')
    //             ->where('hod_ref_code', $hodCode)
    //             ->get()
    //             ->getResultArray();

    //         if (empty($teamLeads)) {
    //             return $this->respond([
    //                 'status' => true,
    //                 'message' => 'No team leads found under this HOD',
    //                 'data' => []
    //             ]);
    //         }

    //         // Map of team leads
    //         $teamLeadCodes = array_column($teamLeads, 'user_code');
    //         $teamLeadNames = [];
    //         foreach ($teamLeads as $lead) {
    //             $teamLeadNames[$lead['user_code']] = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
    //         }

    //         // 🔹 Step 2: Get team members under those team leads
    //         $teamMembers = $db->table('tbl_register')
    //             ->select('user_code, team_lead_ref_code, first_name, last_name')
    //             ->whereIn('team_lead_ref_code', $teamLeadCodes)
    //             ->get()
    //             ->getResultArray();

    //         if (empty($teamMembers)) {
    //             return $this->respond([
    //                 'status' => true,
    //                 'message' => 'No team members found under these team leads',
    //                 'data' => []
    //             ]);
    //         }

    //         $teamMemberCodes = array_column($teamMembers, 'user_code');
    //         $teamMemberNames = [];
    //         foreach ($teamMembers as $member) {
    //             $teamMemberNames[$member['user_code']] = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    //         }

    //         // 🔹 Step 3: Fetch client interaction records of these team members
    //         $builder = $db->table('tbl_initial_client_interactions');
    //         $builder->select('*');
    //         $builder->whereIn('marketing_executive_id', $teamMemberCodes);
    //         $builder->where('is_active', 'Y');
    //         $results = $builder->get()->getResultArray();

    //         if (empty($results)) {
    //             return $this->respond([
    //                 'status' => true,
    //                 'message' => 'No client interactions found for team members under this HOD',
    //                 'data' => []
    //             ]);
    //         }

    //         // 🔹 Step 4: Prepare lookups for schemes, categories, and users
    //         $schemeMap = [];
    //         foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $s) {
    //             $schemeMap[$s['scheme_id']] = $s['scheme_name'];
    //         }

    //         $categoryMap = [];
    //         foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $c) {
    //             $categoryMap[$c['id']] = $c['category_name'];
    //         }

    //         $registerUsers = $db->table('tbl_register')
    //             ->select('user_code, first_name, last_name')
    //             ->get()
    //             ->getResultArray();

    //         $registerNameMap = [];
    //         foreach ($registerUsers as $user) {
    //             $registerNameMap[$user['user_code']] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    //         }

    //         // 🔹 Step 5: Format data with names
    //         foreach ($results as &$row) {
    //             $mId = $row['marketing_executive_id'] ?? null;
    //             $row['marketing_executive_name'] = $teamMemberNames[$mId] ?? null;

    //             // Get team lead name for this marketing executive
    //             $teamLeadRef = null;
    //             foreach ($teamMembers as $m) {
    //                 if ($m['user_code'] === $mId) {
    //                     $teamLeadRef = $m['team_lead_ref_code'];
    //                     break;
    //                 }
    //             }
    //             $row['team_lead_name'] = $teamLeadRef ? ($teamLeadNames[$teamLeadRef] ?? null) : null;

    //             $catId = $row['category_id'] ?? null;
    //             $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;

    //             $schemeIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
    //             $row['schemes_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $schemeIds));

    //             $pfIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
    //             $row['project_finance_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $pfIds));

    //             $landIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
    //             $row['land_related_services_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $landIds));

    //             $createdBy = $row['created_by'] ?? null;
    //             $row['created_by_name'] = $createdBy ? ($registerNameMap[$createdBy] ?? null) : null;
    //         }

    //         // ✅ Final Response
    //         return $this->respond([
    //             'status' => true,
    //             'message' => 'Client interactions fetched successfully for HOD',
    //             'data' => $results
    //         ]);
    //     } catch (\Exception $e) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Error fetching data',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function getAllClientsProcessing_forHOD()
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

        try {
            $teamLeads = $db->table('tbl_register')
                ->select('user_code, first_name, last_name')
                ->where('hod_ref_code', $hodCode)
                ->get()
                ->getResultArray();

            if (empty($teamLeads)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'No team leads found under this HOD',
                    'data' => []
                ]);
            }
            $teamLeadCodes = array_column($teamLeads, 'user_code');
            $teamLeadNames = [];
            foreach ($teamLeads as $lead) {
                $teamLeadNames[$lead['user_code']] = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
            }
            $teamMembers = $db->table('tbl_register')
                ->select('user_code, team_lead_ref_code, first_name, last_name')
                ->whereIn('team_lead_ref_code', $teamLeadCodes)
                ->get()
                ->getResultArray();
            if (empty($teamMembers)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'No team members found under these team leads',
                    'data' => []
                ]);
            }
            $teamMemberCodes = array_column($teamMembers, 'user_code');
            $teamMemberNames = [];
            foreach ($teamMembers as $member) {
                $teamMemberNames[$member['user_code']] = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
            }
            $builder = $db->table('tbl_initial_client_interactions');
            $builder->select('*');
            $builder->whereIn('marketing_executive_id', $teamMemberCodes);
            $builder->where('is_active', 'Y');
            $results = $builder->get()->getResultArray();
            if (empty($results)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'No client interactions found for team members under this HOD',
                    'data' => []
                ]);
            }
            $schemeMap = [];
            foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $s) {
                $schemeMap[$s['scheme_id']] = $s['scheme_name'];
            }
            $categoryMap = [];
            foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $c) {
                $categoryMap[$c['id']] = $c['category_name'];
            }
            $registerUsers = $db->table('tbl_register')
                ->select('user_code, first_name, last_name')
                ->get()
                ->getResultArray();
            $registerNameMap = [];
            foreach ($registerUsers as $user) {
                $registerNameMap[$user['user_code']] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            }
            $clientMap = [];
            foreach ($db->table('tbl_client_mst')->select('client_code, client_name, converts')->get()->getResultArray() as $c) {
                $clientMap[$c['client_code']] = [
                    'client_name' => $c['client_name'],
                    'converts' => $c['converts']
                ];
            }
            foreach ($results as &$row) {
                $mId = $row['marketing_executive_id'] ?? null;
                $row['marketing_executive_name'] = $teamMemberNames[$mId] ?? null;
                $teamLeadRef = null;
                foreach ($teamMembers as $m) {
                    if ($m['user_code'] === $mId) {
                        $teamLeadRef = $m['team_lead_ref_code'];
                        break;
                    }
                }
                $row['team_lead_name'] = $teamLeadRef ? ($teamLeadNames[$teamLeadRef] ?? null) : null;
                $catId = $row['category_id'] ?? null;
                $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;
                $schemeIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
                $row['schemes_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $schemeIds));
                $pfIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
                $row['project_finance_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $pfIds));
                $landIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
                $row['land_related_services_details'] = array_filter(array_map(fn($id) => $schemeMap[trim($id)] ?? null, $landIds));
                $createdBy = $row['created_by'] ?? null;
                $row['created_by_name'] = $createdBy ? ($registerNameMap[$createdBy] ?? null) : null;
                $clientCode = $row['client_code'] ?? null;
                if ($clientCode && isset($clientMap[$clientCode])) {
                    $row['client_name'] = $clientMap[$clientCode]['client_name'];
                    $row['converts'] = $clientMap[$clientCode]['converts'];
                } else {
                    $row['client_name'] = $clientCode;
                    $row['converts'] = null;
                }
            }
            return $this->respond([
                'status' => true,
                'message' => 'Client interactions fetched successfully for HOD',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error fetching data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function getAllClientsProcessing()
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

    //     $db = \Config\Database::connect();
    //     $requestData = $this->request->getJSON(true);
    //     $page  = $requestData['page'] ?? 1;
    //     $limit = $requestData['limit'] ?? 10;
    //     $offset = ($page - 1) * $limit;
    //     $totalBuilder = $db->table('tbl_initial_client_interactions ic');
    //     $totalBuilder->where('ic.is_active', 'Y');
    //     $totalRecords = $totalBuilder->countAllResults();

    //     // 🔹 Fetch paginated client interactions
    //     $builder = $db->table('tbl_initial_client_interactions ic');
    //     $builder->select('
    //     ic.*, 
    //     l.user_name AS marketing_executive_name,
    //     c.user_name AS created_by_name
    // ');
    //     $builder->join('tbl_login l', 'l.user_code_ref = ic.marketing_executive_id', 'left');
    //     $builder->join('tbl_login c', 'c.user_code_ref = ic.created_by', 'left');
    //     $builder->where('ic.is_active', 'Y');
    //     $builder->orderBy('ic.created_at', 'DESC');
    //     $builder->limit($limit, $offset);
    //     $query = $builder->get();
    //     $results = $query->getResultArray();

    //     if (empty($results)) {
    //         return $this->respond([
    //             'status' => true,
    //             'message' => 'No client interactions found',
    //             'data' => [],
    //             'pagination' => [
    //                 'total' => $totalRecords,
    //                 'page' => $page,
    //                 'limit' => $limit,
    //                 'total_pages' => ceil($totalRecords / $limit)
    //             ]
    //         ]);
    //     }

    //     // 🔹 Schemes & Categories mapping
    //     $schemeTable = $db->table('tbl_scheme');
    //     $allSchemes = $schemeTable->select('scheme_id, scheme_name')->get()->getResultArray();
    //     $schemeMap = [];
    //     foreach ($allSchemes as $scheme) {
    //         $schemeMap[$scheme['scheme_id']] = $scheme['scheme_name'];
    //     }

    //     $catTable = $db->table('tbl_scheme_categories');
    //     $allCategories = $catTable->select('id, category_name')->get()->getResultArray();
    //     $categoryMap = [];
    //     foreach ($allCategories as $cat) {
    //         $categoryMap[$cat['id']] = $cat['category_name'];
    //     }

    //     // 🔹 Add readable scheme/category info
    //     foreach ($results as &$row) {
    //         $catId = $row['category_id'] ?? null;
    //         $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;

    //         $schemesIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
    //         $row['schemes_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
    //             return $schemeMap[trim($id)] ?? null;
    //         }, $schemesIds));

    //         $projectFinanceIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
    //         $row['project_finance_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
    //             return $schemeMap[trim($id)] ?? null;
    //         }, $projectFinanceIds));

    //         $landServiceIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
    //         $row['land_related_services_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
    //             return $schemeMap[trim($id)] ?? null;
    //         }, $landServiceIds));
    //     }

    //     // 🔹 Final Response
    //     return $this->respond([
    //         'status' => true,
    //         'message' => 'Clients fetched successfully',
    //         'data' => $results,
    //         'pagination' => [
    //             'total' => $totalRecords,
    //             'page' => $page,
    //             'limit' => $limit,
    //             'total_pages' => ceil($totalRecords / $limit)
    //         ]
    //     ]);
    // }
    public function getAllClientsProcessing()
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

        $db = \Config\Database::connect();
        $requestData = $this->request->getJSON(true);

        $page  = $requestData['page'] ?? 1;
        $limit = $requestData['limit'] ?? 10;
        $offset = ($page - 1) * $limit;

        // 🔹 Count total records
        $totalBuilder = $db->table('tbl_initial_client_interactions ic');
        $totalBuilder->where('ic.is_active', 'Y');
        $totalRecords = $totalBuilder->countAllResults();

        // 🔹 Fetch paginated client interactions
        $builder = $db->table('tbl_initial_client_interactions ic');
        $builder->select('
        ic.*, 
        l.user_name AS marketing_executive_name,
        c.user_name AS created_by_name
    ');
        $builder->join('tbl_login l', 'l.user_code_ref = ic.marketing_executive_id', 'left');
        $builder->join('tbl_login c', 'c.user_code_ref = ic.created_by', 'left');
        $builder->where('ic.is_active', 'Y');
        $builder->orderBy('ic.created_at', 'DESC');
        $builder->limit($limit, $offset);
        $results = $builder->get()->getResultArray();

        if (empty($results)) {
            return $this->respond([
                'status' => true,
                'message' => 'No client interactions found',
                'data' => [],
                'pagination' => [
                    'total' => $totalRecords,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($totalRecords / $limit)
                ]
            ]);
        }

        // 🔹 Build Schemes Map
        $schemeMap = [];
        foreach ($db->table('tbl_scheme')->select('scheme_id, scheme_name')->get()->getResultArray() as $s) {
            $schemeMap[$s['scheme_id']] = $s['scheme_name'];
        }

        // 🔹 Build Categories Map
        $categoryMap = [];
        foreach ($db->table('tbl_scheme_categories')->select('id, category_name')->get()->getResultArray() as $cat) {
            $categoryMap[$cat['id']] = $cat['category_name'];
        }

        // 🔹 Build Client Map (for client_name & converts)
        $clientMap = [];
        foreach ($db->table('tbl_client_mst')->select('client_code, client_name, converts')->get()->getResultArray() as $c) {
            $clientMap[$c['client_code']] = [
                'client_name' => $c['client_name'],
                'converts' => $c['converts']
            ];
        }

        // 🔹 Attach readable info
        foreach ($results as &$row) {
            // Category Name
            $catId = $row['category_id'] ?? null;
            $row['category_name'] = $catId ? ($categoryMap[$catId] ?? null) : null;

            // Schemes Details
            $schemesIds = isset($row['schemes_ids']) ? explode(',', $row['schemes_ids']) : [];
            $row['schemes_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
                return $schemeMap[trim($id)] ?? null;
            }, $schemesIds));

            // Project Finance Details
            $projectFinanceIds = isset($row['project_finance']) ? explode(',', $row['project_finance']) : [];
            $row['project_finance_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
                return $schemeMap[trim($id)] ?? null;
            }, $projectFinanceIds));

            // Land Related Services
            $landServiceIds = isset($row['Land_related_services']) ? explode(',', $row['Land_related_services']) : [];
            $row['land_related_services_details'] = array_filter(array_map(function ($id) use ($schemeMap) {
                return $schemeMap[trim($id)] ?? null;
            }, $landServiceIds));

            // ✅ Add client name & converts
            $clientCode = $row['client_code'] ?? null;
            $row['client_name'] = $clientCode ? ($clientMap[$clientCode]['client_name'] ?? null) : null;
            $row['converts'] = $clientCode ? ($clientMap[$clientCode]['converts'] ?? null) : null;
        }

        // 🔹 Final Response
        return $this->respond([
            'status' => true,
            'message' => 'Clients fetched successfully',
            'data' => $results,
            'pagination' => [
                'total' => $totalRecords,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalRecords / $limit)
            ]
        ]);
    }

    public function client_convert()
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
                'message' => 'User code not found in token'
            ], 400);
        }

        $db = \Config\Database::connect();
        $requestData = $this->request->getJSON(true);

        $clientCode = $requestData['client_code'] ?? null;
        $status = $requestData['status'] ?? null;

        if (!$clientCode || !$status) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid input: client_code and status (Y/N) required.'
            ], 400);
        }
        $updateData = [
            'converts'   => $status,
            'updated_by' => $userCode,
            'updated_at' => date('Y-m-d H:i:s'),
            'kyc' => $input['kyc'] ?? null,
            'mpcb' => $input['mpcb'] ?? null,
            'udyam' => $input['udyam'] ?? null,
            'land' => $input['land'] ?? null,
            'incorporation' => $input['incorporation'] ?? null
        ];
        $processing_code = null;
        if ($status === 'Y') {
            $processing_code = 'PROC' . date('Ymd') . mt_rand(1000, 9999);
            $updateData['processing_code'] = $processing_code;
        }
        $builderClient = $db->table('tbl_client_mst');
        $builderClient->where('client_code', $clientCode);
        $updated = $builderClient->update($updateData);

        if (!$updated) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update client data. Check client_code.'
            ], 500);
        }

        if ($status === 'Y' && $processing_code) {
            $builderProcessing = $db->table('tbl_processing_from');
            $insertData = [
                'processing_code' => $processing_code,
                'client_code'     => $clientCode,
                'is_active'       => 'Y',
                'created_by'      => $userCode,
                'created_at'      => date('Y-m-d H:i:s'),
            ];

            if (!$builderProcessing->insert($insertData)) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Client updated but failed to insert processing data.'
                ], 500);
            }
        }

        return $this->respond([
            'status' => true,
            'message' => ($status === 'Y')
                ? 'Client converted and processing code inserted successfully.'
                : 'Client conversion status updated successfully.',
            'processing_code' => $processing_code
        ]);
    }

    public function getconvertclientlist()
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
                'message' => 'User code not found in token'
            ], 400);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_client_mst');
        $builder->where('converts', 'Y');
        $builder->where('is_active', 'Y');
        $query = $builder->get();
        $result = $query->getResult();
        return $this->response->setJSON([
            'status' => true,
            'message' => 'Converted clients fetched successfully.',
            'data' => $result
        ]);
    }

    public function updatemeetingstatus()
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
                'message' => 'User code not found in token'
            ], 400);
        }
        $requestData = $this->request->getJSON(true);
        $meeting_code = $requestData['meeting_code'] ?? null;
        $status = $requestData['status'] ?? null;
        if (!$meeting_code || !$status) {
            return $this->respond([
                'status' => false,
                'message' => 'Meeting code and status are required.'
            ], 400);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_initial_client_interactions');
        $builder->where('meeting_code', $meeting_code);

        if ($builder->update([
            'meeting_status' => $status,
            'updated_by' => $userCode,
            'reason' => $requestData['reason'] ?? null,
            'followUpDate' => $requestData['followUpDate'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ])) {
            return $this->respond([
                'status' => true,
                'message' => 'Meeting status updated successfully.'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update meeting status. Check meeting code.'
            ], 500);
        }
    }

    public function markMeetingReached()
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
                'message' => 'User code not found in token'
            ], 400);
        }

        $requestData = $this->request->getJSON(true);
        $meetingCode = $requestData['meeting_code'] ?? null;
        $latitude = $requestData['latitude'] ?? null;
        $longitude = $requestData['longitude'] ?? null;

        if (!$meetingCode || !$latitude || !$longitude) {
            return $this->respond([
                'status' => false,
                'message' => 'Meeting code, latitude and longitude are required.'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_client_meeting_reached');

        $insertData = [
            'meeting_code' => $meetingCode,
            'marketing_executive_id' => $userCode,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'reached_at' => (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
            'created_by' => $userCode
        ];

        if ($builder->insert($insertData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Meeting location recorded successfully.',
                'data' => $insertData
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to record meeting location.'
            ], 500);
        }
    }

    // public function getMeetingReachedByCode()
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

    //     $requestData  = $this->request->getJSON(true);
    //     $meetingCode  = $requestData['meeting_code'] ?? null;
    //     $reachedAt    = $requestData['reached_at'] ?? null;

    //     $db = \Config\Database::connect();
    //     $builder = $db->table('tbl_client_meeting_reached');

    //     // Apply filters if provided
    //     if ($meetingCode) {
    //         $builder->where('meeting_code', $meetingCode);
    //     }

    //     if ($reachedAt) {
    //         $builder->where('DATE(reached_at)', $reachedAt);
    //     }

    //     $query = $builder->get();

    //     if ($query->getNumRows() > 0) {
    //         return $this->respond([
    //             'status' => true,
    //             'message' => 'Meeting reached record(s) found.',
    //             'data' => $query->getResult()
    //         ]);
    //     } else {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'No record found for given filter.'
    //         ], 404);
    //     }
    // }
    public function getMeetingReachedByCode()
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

        $requestData  = $this->request->getJSON(true);
        $meetingCode  = $requestData['meeting_code'] ?? null;
        $reachedAt    = $requestData['reached_at'] ?? null;
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_client_meeting_reached');
        if ($meetingCode) {
            $builder->where('meeting_code', $meetingCode);
        }

        if ($reachedAt) {
            $builder->where('DATE(reached_at)', $reachedAt);
        }

        $query = $builder->get();
        $records = $query->getResultArray();

        if (empty($records)) {
            return $this->respond([
                'status' => false,
                'message' => 'No record found for given filter.'
            ], 404);
        }
        $executiveIds = array_unique(array_column($records, 'marketing_executive_id'));
        $executives = $db->table('tbl_register')
            ->select('user_code, first_name, last_name')
            ->whereIn('user_code', $executiveIds)
            ->get()
            ->getResultArray();
        $executiveMap = [];
        foreach ($executives as $ex) {
            $executiveMap[$ex['user_code']] = trim(($ex['first_name'] ?? '') . ' ' . ($ex['last_name'] ?? ''));
        }
        foreach ($records as &$record) {
            $record['executive_name'] = $executiveMap[$record['marketing_executive_id']] ?? null;
        }
        return $this->respond([
            'status' => true,
            'message' => 'Meeting reached record(s) found.',
            'data' => $records
        ], 200);
    }

    public function getclientsby_user()
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
                'message' => 'User code not found in token'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_client_mst');
        $builder->where('created_by', $userCode);
        $builder->where('is_active', 'Y');
        $query = $builder->get();
        $results = $query->getResult();

        return $this->respond([
            'status' => true,
            'message' => 'Clients fetched successfully for the user.',
            'data' => $results
        ]);
    }

    public function getclientlistforteamlead()
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

        $teamLeadCode = $decodedToken->user_id ?? null;
        if (!$teamLeadCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User code not found in token'
            ], 400);
        }

        $db = \Config\Database::connect();

        // 🔹 Step 1: Get team members under this Team Lead
        $teamMembers = $db->table('tbl_register')
            ->select('user_code, First_Name, Last_Name')
            ->where('team_lead_ref_code', $teamLeadCode)
            ->get()
            ->getResultArray();

        if (empty($teamMembers)) {
            return $this->respond([
                'status' => true,
                'message' => 'No team members found under this team lead',
                'data' => []
            ]);
        }

        $teamMemberCodes = array_column($teamMembers, 'user_code');

        // 🔹 Step 2: Fetch clients along with team member name & team leader name
        $builder = $db->table('tbl_client_mst cm');
        $builder->select('cm.*, tm.First_Name as member_first_name, tm.Last_Name as member_last_name, 
                      tl.First_Name as lead_first_name, tl.Last_Name as lead_last_name');
        $builder->join('tbl_register tm', 'tm.user_code = cm.created_by', 'left');
        $builder->join('tbl_register tl', 'tl.user_code = tm.team_lead_ref_code', 'left');
        $builder->whereIn('cm.created_by', $teamMemberCodes);
        $builder->where('cm.is_active', 'Y');
        $query = $builder->get();
        $results = $query->getResultArray();

        if (empty($results)) {
            return $this->respond([
                'status' => true,
                'message' => 'No clients found for team members',
                'data' => []
            ]);
        }

        return $this->respond([
            'status' => true,
            'message' => 'Clients fetched successfully for team members',
            'data' => $results
        ]);
    }



    public function getclientlistforhod_ref_code()
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
        $teamLeads = $db->table('tbl_register')
            ->select('user_code')
            ->where('hod_ref_code', $hodCode)
            ->get()
            ->getResultArray();

        if (empty($teamLeads)) {
            return $this->respond([
                'status' => true,
                'message' => 'No team leads found under this HOD',
                'data' => []
            ]);
        }
        $teamLeadCodes = array_column($teamLeads, 'user_code');
        $teamMembers = $db->table('tbl_register')
            ->select('user_code, team_lead_ref_code, First_Name, Last_Name')
            ->whereIn('team_lead_ref_code', $teamLeadCodes)
            ->get()
            ->getResultArray();

        if (empty($teamMembers)) {
            return $this->respond([
                'status' => true,
                'message' => 'No team members found under the team leads of this HOD',
                'data' => []
            ]);
        }
        $teamMemberCodes = array_column($teamMembers, 'user_code');
        $builder = $db->table('tbl_client_mst cm');
        $builder->select('cm.*,
                      tm.First_Name as member_first_name, tm.Last_Name as member_last_name,
                      tl.First_Name as lead_first_name, tl.Last_Name as lead_last_name');
        $builder->join('tbl_register tm', 'tm.user_code = cm.created_by', 'left');
        $builder->join('tbl_register tl', 'tl.user_code = tm.team_lead_ref_code', 'left');
        $builder->whereIn('cm.created_by', $teamMemberCodes);
        $builder->where('cm.is_active', 'Y');
        $query = $builder->get();
        $results = $query->getResultArray();
        if (empty($results)) {
            return $this->respond([
                'status' => true,
                'message' => 'No clients found for team members',
                'data' => []
            ]);
        }

        return $this->respond([
            'status' => true,
            'message' => 'Clients fetched successfully for team members',
            'data' => $results
        ]);
    }

    public function supportstafReached()
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
                'message' => 'User code not found in token'
            ], 400);
        }

        $requestData = $this->request->getJSON(true);
        $task_code = $requestData['task_code'] ?? null;
        $latitude = $requestData['latitude'] ?? null;
        $longitude = $requestData['longitude'] ?? null;

        if (!$task_code || !$latitude || !$longitude) {
            return $this->respond([
                'status' => false,
                'message' => 'Meeting code, latitude and longitude are required.'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_supportstaf_location');

        $insertData = [
            'task_code' => $task_code,
            'user_code' => $userCode,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'reached_at' => (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
            'created_by' => $userCode
        ];

        if ($builder->insert($insertData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Meeting location recorded successfully.',
                'data' => $insertData
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to record meeting location.'
            ], 500);
        }
    }


    public function getMeetingReachedbysupportstaf()
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

        $requestData  = $this->request->getJSON(true);
        $task_code  = $requestData['task_code'] ?? null;
        $reachedAt    = $requestData['reached_at'] ?? null;
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_supportstaf_location');
        if ($task_code) {
            $builder->where('task_code', $task_code);
        }

        if ($reachedAt) {
            $builder->where('DATE(reached_at)', $reachedAt);
        }

        $query = $builder->get();
        $records = $query->getResultArray();

        if (empty($records)) {
            return $this->respond([
                'status' => false,
                'message' => 'No record found for given filter.'
            ], 404);
        }
        $executiveIds = array_unique(array_column($records, 'user_code'));
        $executives = $db->table('tbl_register')
            ->select('user_code, first_name, last_name')
            ->whereIn('user_code', $executiveIds)
            ->get()
            ->getResultArray();
        $executiveMap = [];
        foreach ($executives as $ex) {
            $executiveMap[$ex['user_code']] = trim(($ex['first_name'] ?? '') . ' ' . ($ex['last_name'] ?? ''));
        }
        foreach ($records as &$record) {
            $record['name'] = $executiveMap[$record['user_code']] ?? null;
        }
        return $this->respond([
            'status' => true,
            'message' => 'Meeting reached record(s) found.',
            'data' => $records
        ], 200);
    }

    public function getfollowUpDaterecords()
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
        $userCode = $data['user_code'] ?? null;

        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User code missing in token'
            ], 400);
        }
        $db = \Config\Database::connect();
        try {
            $today = (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
            $dateAfter5Days = (new \DateTime('+5 days', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
            $builder = $db->table('tbl_initial_client_interactions');
            $builder->groupStart()
                ->where('created_by', $userCode)
                ->groupEnd();
            $builder->where('is_active', 'Y');
            $builder->where('followUpDate IS NOT NULL', null, false);
            $builder->where('DATE(followUpDate) >=', $today);
            $builder->where('DATE(followUpDate) <=', $dateAfter5Days);
            $query = $builder->get();
            $results = $query->getResultArray();
            return $this->respond([
                'status' => true,
                'message' => 'Follow-up records for next 5 days fetched successfully.',
                'data' => $results
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error fetching records',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getconvertclinetornotstatus()
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

        $requestData = $this->request->getJSON(true);
        $clientCode = $requestData['client_code'] ?? null;

        if (!$clientCode) {
            return $this->respond([
                'status' => false,
                'message' => 'client_code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_client_mst');
        $builder->select('converts');
        $builder->where('client_code', $clientCode);
        $result = $builder->get()->getRow();

        if (!$result) {
            return $this->respond([
                'status' => false,
                'message' => 'Client not found'
            ], 404);
        }

        return $this->respond([
            'status' => true,
            'client_code' => $clientCode,
            'converts' => $result->converts
        ]);
    }
}
