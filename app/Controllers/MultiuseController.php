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
class MultiuseController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    use ResponseTrait;


    // public function add_accesslevel()
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
    //     $request = $this->request->getJSON(true);
    //     $url = $request['url'] ?? null;
    //     $name = $request['name'] ?? null;
    //     if (!$url || !$name) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'URL and Name are required'
    //         ], 400);
    //     }
    //     $url_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 5);
    //     $data = [
    //         'url'         => $url,
    //         'url_code'    => $url_code,
    //         'name'        => $name,
    //         'description' => $request['description'] ?? null,
    //         'created_by'  => $userCode
    //     ];
    //     $db = \Config\Database::connect();
    //     try {
    //         $db->table('tbl_access_level')->insert($data);

    //         return $this->respond([
    //             'status'  => true,
    //             'message' => 'Access level added successfully',
    //             'data'    => $data
    //         ], 201);
    //     } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
    //         return $this->respond([
    //             'status'  => false,
    //             'message' => 'Database error',
    //             'error'   => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function add_accesslevel()
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
        $request = $this->request->getJSON(true);
        $url  = $request['url'] ?? null;
        $name = $request['name'] ?? null;
        if (!$url || !$name) {
            return $this->respond([
                'status' => false,
                'message' => 'URL and Name are required'
            ], 400);
        }
        $url_code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        $deptFields = ['DEPTM001', 'DEPTM002', 'DEPTM003', 'DEPTM004', 'DEPTM005', 'DEPTM006', 'DEPTM007', 'DEPTM008', 'DEPTM009'];
        $deptData = [];
        foreach ($deptFields as $dept) {
            $deptData[$dept] = !empty($request[$dept]) && strtoupper($request[$dept]) === 'Y' ? 'Y' : null;
        }
        $data = array_merge([
            'url'         => $url,
            'url_code'    => $url_code,
            'name'        => $name,
            'description' => $request['description'] ?? null,
            'created_by'  => $userCode,
        ], $deptData);
        $db = \Config\Database::connect();
        try {
            $db->table('tbl_access_level')->insert($data);

            return $this->respond([
                'status'  => true,
                'message' => 'Access level added successfully',
                'data'    => $data
            ], 201);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update_accesslevel()
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
        $request  = $this->request->getJSON(true);
        $url_code = $request['url_code'] ?? null;
        if (!$url_code) {
            return $this->respond([
                'status' => false,
                'message' => 'url_code is required for update'
            ], 400);
        }
        $db = \Config\Database::connect();
        $exists = $db->table('tbl_access_level')
            ->where('url_code', $url_code)
            ->get()
            ->getRowArray();

        if (!$exists) {
            return $this->respond([
                'status' => false,
                'message' => 'Access level not found for given url_code'
            ], 404);
        }
        $data = [];
        if (isset($request['url']))         $data['url'] = $request['url'];
        if (isset($request['name']))        $data['name'] = $request['name'];
        if (isset($request['description'])) $data['description'] = $request['description'];
        $deptFields = ['DEPTM001', 'DEPTM002', 'DEPTM003', 'DEPTM004', 'DEPTM005', 'DEPTM006', 'DEPTM007', 'DEPTM008', 'DEPTM009'];
        foreach ($deptFields as $dept) {
            if (array_key_exists($dept, $request)) {
                $data[$dept] = strtoupper($request[$dept]) === 'Y' ? 'Y' : null;
            }
        }
        $data['updated_by'] = $userCode;
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $db->table('tbl_access_level')
                ->where('url_code', $url_code)
                ->update($data);

            return $this->respond([
                'status'  => true,
                'message' => 'Access level updated successfully',
                'data'    => $data
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function get_accesslevelsbydepartmentcode()
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
        $userCode = $request['user_id'] ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User code not found in token'
            ], 400);
        }

        $db = \Config\Database::connect();

        try {
            // ✅ Step 1: Get department_code from tbl_register
            $user = $db->table('tbl_register')
                ->select('department_code')
                ->where('user_code', $userCode)
                ->get()
                ->getRow();

            if (!$user || empty($user->department_code)) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Department code not found for user'
                ], 404);
            }

            $departmentCode = $user->department_code;

            // ✅ Step 2: Get user-specific access (tbl_access_level_by_user)
            $userAccessRow = $db->table('tbl_access_level_by_user')
                ->select('url_code')
                ->where('user_ref_code', $userCode)
                ->get()
                ->getRow();

            $userUrlCodes = [];
            if ($userAccessRow && !empty($userAccessRow->url_code)) {
                $userUrlCodes = array_filter(array_map('trim', explode(',', $userAccessRow->url_code)));
            }

            // ✅ Step 3: Get department-based access (tbl_access_level)
            // We dynamically check the column matching department_code (e.g., DEPTM001)
            $deptAccess = $db->table('tbl_access_level')
                ->select('url_code, url, name, description')
                ->where($departmentCode, 'Y')  // column name is same as department_code
                ->get()
                ->getResultArray();

            // ✅ Step 4: Merge both sources of access levels
            $allAccess = $deptAccess;

            if (!empty($userUrlCodes)) {
                $userAccess = $db->table('tbl_access_level')
                    ->select('url_code, url, name, description')
                    ->whereIn('url_code', $userUrlCodes)
                    ->get()
                    ->getResultArray();

                // Merge user-specific with department access (remove duplicates)
                $combined = array_merge($deptAccess, $userAccess);
                $allAccess = array_values(array_unique($combined, SORT_REGULAR));
            }

            // ✅ Step 5: Return final combined access data
            return $this->respond([
                'status' => true,
                'message' => 'Access levels fetched successfully',
                'department_code' => $departmentCode,
                'data' => $allAccess
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit_accesslevel()
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
        $request = $this->request->getJSON(true);
        $id   = $request['id'] ?? null;
        $url  = $request['url'] ?? null;
        $name = $request['name'] ?? null;

        if (!$id || !$url || !$name) {
            return $this->respond([
                'status' => false,
                'message' => 'ID, URL, and Name are required'
            ], 400);
        }
        $updateData = [
            'url'         => $url,
            'name'        => $name,
            'description' => $request['description'] ?? null,
            'updated_by'  => $userCode
        ];
        $db = \Config\Database::connect();
        try {
            $builder = $db->table('tbl_access_level');
            $builder->where('id', $id);
            $builder->update($updateData);

            if ($db->affectedRows() > 0) {
                return $this->respond([
                    'status'  => true,
                    'message' => 'Access level updated successfully',
                    'data'    => $updateData
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No record updated. Check if the ID exists.'
                ], 404);
            }
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function update_accesslevel_status()
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
        $id = $request['id'] ?? null;
        $isActive = $request['is_active'] ?? null;

        if (is_null($id) || is_null($isActive)) {
            return $this->respond([
                'status' => false,
                'message' => 'ID and is_active value are required'
            ], 400);
        }

        $db = \Config\Database::connect();
        try {
            $updated = $db->table('tbl_access_level')
                ->where('id', $id)
                ->update(['is_active' => $isActive]);

            if ($updated) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Access level status updated successfully'
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No record found or nothing updated'
                ], 404);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function get_accesslevels()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $accessLevels = $db->table('tbl_access_level')
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();

            return $this->respond([
                'status' => true,
                'data'   => $accessLevels
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function get_accesslevelsby_usercode()
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
        try {
            $row = $db->table('tbl_access_level_by_user')
                ->select('url_code')
                ->where('user_ref_code', $userCode)
                ->get()
                ->getRow();
            if (!$row || empty($row->url_code)) {
                return $this->respond([
                    'status' => true,
                    'data' => []
                ], 200);
            }
            $urlCodes = array_filter(array_map('trim', explode(',', $row->url_code)));
            if (empty($urlCodes)) {
                return $this->respond([
                    'status' => true,
                    'data' => []
                ], 200);
            }
            $accessLevels = $db->table('tbl_access_level')
                ->select('url_code, url')
                ->whereIn('url_code', $urlCodes)
                ->get()
                ->getResultArray();

            return $this->respond([
                'status' => true,
                'data' => $accessLevels
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function add_accesslevelby_usercode()
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
        $createdBy = $decodedToken->user_id ?? null;
        $request = $this->request->getJSON(true);
        $userCode = trim($request['user_code'] ?? '');
        $urlCodes = trim($request['url_code'] ?? '');
        if (empty($userCode) || empty($urlCodes)) {
            return $this->respond([
                'status' => false,
                'message' => 'Both user_code and url_code are required'
            ], 400);
        }
        $urlCodeArray = array_filter(array_map('trim', explode(',', $urlCodes)));
        $db = \Config\Database::connect();
        try {
            $existingRow = $db->table('tbl_access_level_by_user')
                ->where('user_ref_code', $userCode)
                ->get()
                ->getRow();
            if ($existingRow) {
                $existingUrlCodes = array_filter(array_map('trim', explode(',', $existingRow->url_code)));
                $mergedUrlCodes = array_unique(array_merge($existingUrlCodes, $urlCodeArray));
                $db->table('tbl_access_level_by_user')
                    ->where('user_ref_code', $userCode)
                    ->update([
                        'url_code'    => implode(',', $mergedUrlCodes),
                        'updated_at'  => date('Y-m-d H:i:s'),
                        'updated_by'  => $createdBy
                    ]);
            } else {
                $data = [
                    'user_ref_code' => $userCode,
                    'url_code'      => implode(',', $urlCodeArray),
                    'created_at'    => date('Y-m-d H:i:s'),
                    'created_by'    => $createdBy
                ];
                $db->table('tbl_access_level_by_user')->insert($data);
            }
            return $this->respond([
                'status'  => true,
                'message' => 'Access level(s) saved successfully'
            ], 201);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function edit_accesslevelby_usercode()
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

        $updatedBy = $decodedToken->user_id ?? null;
        $request = $this->request->getJSON(true);

        $userCode = trim($request['user_code'] ?? '');
        $urlCodes = trim($request['url_code'] ?? '');

        if (empty($userCode) || empty($urlCodes)) {
            return $this->respond([
                'status' => false,
                'message' => 'Both user_code and url_code are required'
            ], 400);
        }

        $urlCodeArray = array_filter(array_map('trim', explode(',', $urlCodes)));
        $db = \Config\Database::connect();

        try {
            $builder = $db->table('tbl_access_level_by_user');
            $existingRow = $builder
                ->where('user_ref_code', $userCode)
                ->get()
                ->getRow();

            if (!$existingRow) {
                return $this->respond([
                    'status' => false,
                    'message' => 'User access record not found'
                ], 404);
            }

            $builder->where('user_ref_code', $userCode)->update([
                'url_code'   => implode(',', $urlCodeArray),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $updatedBy
            ]);

            return $this->respond([
                'status' => true,
                'message' => 'Access level updated successfully'
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function add_refcodes()
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
        $value = trim($request['value'] ?? '');
        $description = trim($request['description'] ?? '');
        $type = trim($request['type'] ?? '');
        if (empty($value)) {
            return $this->respond([
                'status' => false,
                'message' => 'Value is required'
            ], 400);
        }
        $prefix = strtoupper(substr($value, 0, 3));
        $unique = false;
        $db = \Config\Database::connect();
        $maxAttempts = 10;
        $attempt = 0;
        $ref_code = '';
        while (!$unique && $attempt < $maxAttempts) {
            $rand = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 3);
            $ref_code = $prefix . '_' . $rand;
            $exists = $db->table('tbl_ref_code')->where('ref_code', $ref_code)->countAllResults();
            if ($exists == 0) {
                $unique = true;
            }
            $attempt++;
        }
        if (!$unique) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to generate unique ref_code'
            ], 500);
        }
        $data = [
            'ref_code'    => $ref_code,
            'value'       => $value,
            'description' => $description,
            'created_by' => $decodedToken->user_id ?? null,
            'type'        => $type,
        ];
        try {
            $db->table('tbl_ref_code')->insert($data);
            return $this->respond([
                'status'  => true,
                'message' => 'Reference code added successfully',
                'data'    => $data
            ], 201);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function getroles()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $roles = $db->table('tbl_ref_code')
                ->select('ref_code, type, value, description')
                ->where('type', 'Role')
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $roles
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getrefcode_bytype()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $request = $this->request->getJSON(true);
        $type = trim($request['type'] ?? '');
        if (empty($type)) {
            return $this->respond([
                'status' => false,
                'message' => 'Type is required'
            ], 400);
        }
        $db = \Config\Database::connect();
        try {
            $records = $db->table('tbl_ref_code')
                ->select('ref_code, type, value, description')
                ->where('type', $type)
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $records
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function get_usercode_accesslevel()
    {
        $request = $this->request->getJSON(true);
        $userCode = trim($request['user_code'] ?? '');

        $db = \Config\Database::connect();
        try {
            $row = $db->table('tbl_access_level_by_user')
                ->select('url_code')
                ->where('user_ref_code', $userCode)
                ->get()
                ->getRow();
            if (!$row || empty($row->url_code)) {
                return $this->respond([
                    'status' => true,
                    'data' => []
                ], 200);
            }
            $urlCodes = array_filter(array_map('trim', explode(',', $row->url_code)));
            if (empty($urlCodes)) {
                return $this->respond([
                    'status' => true,
                    'data' => []
                ], 200);
            }
            $accessLevels = $db->table('tbl_access_level')
                ->select('url_code, url')
                ->whereIn('url_code', $urlCodes)
                ->get()
                ->getResultArray();

            return $this->respond([
                'status' => true,
                'data' => $accessLevels
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getdepartment()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $department = $db->table('tbl_department')
                ->select('department_name, department_code, description')
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $department
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getbranch()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $branch = $db->table('tbl_branch_mst')
                ->select('*')
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $branch
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getprocessingdocs()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $request = $this->request->getJSON(true);
        $type = $request['type'] ?? '';
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $docs = $db->table('tbl_ref_code')
                ->select('ref_code, type, value, description')
                ->where('type', $type)
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $docs
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getmarketingemployee()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $request = $this->request->getJSON(true);
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $docs = $db->table('tbl_login')
                ->select('user_name,user_code_ref')
                ->where('designations_code', 'DESGCPL021')
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $docs
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addTravelCost()
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
        $required = ['date', 'location', 'description', 'km', 'rate', 'transport_total', 'other_expenses', 'total'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->respond([
                    'status' => false,
                    'message' => "Missing required field: {$field}"
                ], 400);
            }
        }
        $userCode = $decodedToken->user_id ?? null;
        $datePart = date('Ymd');
        $randomPart = mt_rand(1000, 9999);
        $travel_code = "TRV" . $datePart . $randomPart;
        $insertData = [
            'travel_ref_code' => $travel_code,
            'travel_date'     => $data['date'],
            'location'        => $data['location'],
            'description'     => $data['description'],
            'km'              => $data['km'],
            'user_code'       => $userCode,
            'rate'            => $data['rate'],
            'transport_total' => $data['transport_total'],
            'other_expenses'  => $data['other_expenses'],
            'total'           => $data['total'],
            'status'          => 'Pending',
            'created_at'      => date('Y-m-d H:i:s'),
            'created_by'      => $decodedToken->user_id ?? null

        ];
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_travel_cost');
        $builder->insert($insertData);
        if ($db->affectedRows() > 0) {
            return $this->respond([
                'status' => true,
                'message' => 'Travel cost record added successfully'
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to insert travel cost record'
            ], 500);
        }
    }

    public function add_outstation_travel_exp()
    {
        helper(['jwtvalidate', 'filesystem']);
        $authHeader   = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status'  => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $data     = $this->request->getPost(); // for multipart/form-data
        $required = ['from_date', 'to_date', 'purpose'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->respond([
                    'status'  => false,
                    'message' => "Missing required field: $field"
                ], 400);
            }
        }

        $datePart    = date('Ymd');
        $randomPart  = mt_rand(1000, 9999);
        $out_trv_code = "OTC" . $datePart . $randomPart;
        $userCode    = $decodedToken->user_id ?? null;

        $db = \Config\Database::connect();
        $db->transStart();

        // 🔹 Insert main travel record
        $expData = [
            'out_trv_code' => $out_trv_code,
            'user_code'    => $userCode,
            'from_date'    => $data['from_date'],
            'to_date'      => $data['to_date'],
            'Destination'  => $data['Destination'] ?? '',
            'purpose'      => $data['purpose'],
            'status'       => 'Pending',
            'created_at'   => date('Y-m-d H:i:s'),
            'created_by'   => $userCode
        ];
        $db->table('tbl_outstation_travel_exp')->insert($expData);

        // 🔹 Insert related expenses (other_exp) with images
        if (!empty($data['other_exp']) && is_array($data['other_exp'])) {
            foreach ($data['other_exp'] as $index => $expItem) {
                $imagePath = null;

                // Check if file is uploaded for this expense
                $file = $this->request->getFile("other_exp.$index.exp_image");
                if ($file && $file->isValid() && !$file->hasMoved()) {
                    $newName    = "REL" . $datePart . mt_rand(1000, 9999) . '_' . time() . '.' . $file->getExtension();
                    $uploadPath = FCPATH . 'public/uploads/';
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    $file->move($uploadPath, $newName);
                    $imagePath = 'public/uploads/' . $newName;
                }

                $relationData = [
                    'out_trv_rel_code' => "REL" . $datePart . mt_rand(1000, 9999),
                    'out_trv_ref_code' => $out_trv_code,
                    'exp_type'         => $expItem['exp_type'] ?? '',
                    'Description'      => $expItem['Description'] ?? '',
                    'no_of_person'     => $expItem['no_of_person'] ?? 0,
                    'amount'           => $expItem['amount'] ?? 0,
                    'exp_image'        => $imagePath
                ];
                $db->table('tbl_outstation_travel_relation')->insert($relationData);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to add outstation travel expense'
            ], 500);
        }

        return $this->respond([
            'status'       => true,
            'message'      => 'Outstation travel expense added successfully',
            'out_trv_code' => $out_trv_code
        ], 200);
    }


    public function getTravelCostList()
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
        $records = $db->table('tbl_travel_cost')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => true,
            'message' => 'Travel cost list fetched successfully',
            'data' => $records
        ], 200);
    }
    public function getTravelCostListforuser()
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
        $db = \Config\Database::connect();
        $records = $db->table('tbl_travel_cost')
            ->where('user_code', $userCode)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => true,
            'message' => 'Travel cost list fetched successfully',
            'data' => $records
        ], 200);
    }
    public function getOutstationTravelExpList()
    {
        helper(['jwtvalidate', 'url']);
        $authHeader   = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond([
                'status'  => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $db = \Config\Database::connect();
        $expList = $db->table('tbl_outstation_travel_exp')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($expList as &$exp) {
            $related = $db->table('tbl_outstation_travel_relation')
                ->where('out_trv_ref_code', $exp['out_trv_code'])
                ->get()
                ->getResultArray();

            // ✅ Add full image URL
            foreach ($related as &$rel) {
                if (!empty($rel['exp_image'])) {
                    $rel['exp_image'] = base_url($rel['exp_image']);
                }
            }

            $exp['other_exp'] = $related;
        }

        return $this->respond([
            'status'  => true,
            'message' => 'Outstation travel expense list fetched successfully',
            'data'    => $expList
        ], 200);
    }
    public function getOutstationTravelExpListforuser()
    {
        helper(['jwtvalidate', 'url']);
        $authHeader   = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond([
                'status'  => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $db       = \Config\Database::connect();
        $userCode = $decodedToken->user_id ?? null;

        $expList = $db->table('tbl_outstation_travel_exp')
            ->where('user_code', $userCode)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($expList as &$exp) {
            $related = $db->table('tbl_outstation_travel_relation')
                ->where('out_trv_ref_code', $exp['out_trv_code'])
                ->get()
                ->getResultArray();

            // ✅ Add full image URL
            foreach ($related as &$rel) {
                if (!empty($rel['exp_image'])) {
                    $rel['exp_image'] = base_url($rel['exp_image']);
                }
            }

            $exp['other_exp'] = $related;
        }

        return $this->respond([
            'status'  => true,
            'message' => 'Outstation travel expense list fetched successfully',
            'data'    => $expList
        ], 200);
    }


    public function edit_outstation_travel_exp()
    {
        helper(['jwtvalidate', 'filesystem']);
        $authHeader   = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status'  => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $data = $this->request->getPost(); // 🔹 for multipart/form-data

        $required = ['out_trv_code', 'from_date', 'to_date', 'purpose'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->respond([
                    'status'  => false,
                    'message' => "Missing required field: $field"
                ], 400);
            }
        }

        $out_trv_code = $data['out_trv_code'];
        $userCode     = $decodedToken->user_id ?? null;
        $datePart     = date('Ymd');

        $db = \Config\Database::connect();
        $db->transStart();

        // 🔹 Update main record
        $updateData = [
            'from_date'   => $data['from_date'],
            'to_date'     => $data['to_date'],
            'purpose'     => $data['purpose'],
            'status'      => $data['status'] ?? 'Pending',
            'Destination' => $data['Destination'] ?? '',
            'updated_at'  => date('Y-m-d H:i:s'),
            'updated_by'  => $userCode
        ];

        $db->table('tbl_outstation_travel_exp')
            ->where('out_trv_code', $out_trv_code)
            ->update($updateData);

        // 🔹 Delete old related expenses
        $db->table('tbl_outstation_travel_relation')
            ->where('out_trv_ref_code', $out_trv_code)
            ->delete();

        // 🔹 Insert updated related expenses
        if (!empty($data['other_exp']) && is_array($data['other_exp'])) {
            foreach ($data['other_exp'] as $index => $expItem) {
                $imagePath = null;

                // Check file upload
                $file = $this->request->getFile("other_exp.$index.exp_image");
                if ($file && $file->isValid() && !$file->hasMoved()) {
                    $newName    = "REL" . $datePart . mt_rand(1000, 9999) . '_' . time() . '.' . $file->getExtension();
                    $uploadPath = FCPATH . 'public/uploads/';
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    $file->move($uploadPath, $newName);
                    $imagePath = 'public/uploads/' . $newName;
                }

                $relationData = [
                    'out_trv_rel_code' => "REL" . $datePart . mt_rand(1000, 9999),
                    'out_trv_ref_code' => $out_trv_code,
                    'exp_type'         => $expItem['exp_type'] ?? '',
                    'Description'      => $expItem['Description'] ?? '',
                    'no_of_person'     => $expItem['no_of_person'] ?? 0,
                    'amount'           => $expItem['amount'] ?? 0,
                    'exp_image'        => $imagePath // ✅ keep same column name as ADD
                ];

                $db->table('tbl_outstation_travel_relation')->insert($relationData);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to update outstation travel expense'
            ], 500);
        }

        return $this->respond([
            'status'  => true,
            'message' => 'Outstation travel expense updated successfully'
        ], 200);
    }


    public function editTravelCost()
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
        $travel_ref_code = $data['travel_ref_code'];
        $userCode = $decodedToken->user_id ?? null;

        $db = \Config\Database::connect();

        $existing = $db->table('tbl_travel_cost')
            ->where('travel_ref_code', $travel_ref_code)
            ->get()
            ->getRowArray();
        if (!$existing) {
            return $this->respond([
                'status' => false,
                'message' => 'Travel cost record not found'
            ], 404);
        }

        $updateData = [
            'travel_date'     => $data['date'],
            'location'        => $data['location'],
            'description'     => $data['description'],
            'km'              => $data['km'],
            'rate'            => $data['rate'],
            'transport_total' => $data['transport_total'],
            'other_expenses'  => $data['other_expenses'],
            'status'           => $data['status'],
            'total'           => $data['total'],
            'updated_at'      => date('Y-m-d H:i:s'),
            'updated_by'      => $userCode
        ];
        $db->table('tbl_travel_cost')
            ->where('travel_ref_code', $travel_ref_code)
            ->update($updateData);

        if ($db->affectedRows() > 0) {
            return $this->respond([
                'status' => true,
                'message' => 'Travel cost record updated successfully'
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No changes made or update failed'
            ], 500);
        }
    }

    public function add_vacancy()
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
        $data = $this->request->getJSON(true);

        $requiredFields = [
            'Job_title',
            'Department',
            'Location',
            'Employment_type',
            'Vacancy_count',
            'Experience_required',
            'Qualification',
            'Required_Skills',
            'Salary_range',
            'Application_deadline',
            'Job_Description',
            'Key_Responsibilities'
        ];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->respond([
                    'status' => false,
                    'message' => "Missing or empty field: {$field}"
                ], 400);
            }
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_vacancy');
        $datePart   = date('Ymd');
        $randomPart = mt_rand(1000, 9999);
        $vacancy_code = "VCANY" . $datePart . $randomPart;
        $insertData = [
            'vacancy_code'         => $vacancy_code,
            'Job_title'            => $data['Job_title'],
            'Department'           => $data['Department'],
            'Location'             => $data['Location'],
            'Employment_type'      => $data['Employment_type'],
            'Vacancy_count'        => (int)$data['Vacancy_count'],
            'Experience_required'  => $data['Experience_required'],
            'Qualification'        => $data['Qualification'],
            'Required_Skills'      => $data['Required_Skills'],
            'Salary_range'         => $data['Salary_range'],
            'Application_deadline' => $data['Application_deadline'],
            'Job_Description'      => $data['Job_Description'],
            'Key_Responsibilities' => $data['Key_Responsibilities'],
            'Status'               => 'Y',
            'created_at'           => date('Y-m-d H:i:s'),
            'created_by'           => $userCode
        ];

        if ($builder->insert($insertData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Vacancy added successfully'
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to add vacancy'
            ], 500);
        }
    }
    public function edit_vacancy()
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
        $data = $this->request->getJSON(true);

        if (!isset($data['vacancy_code']) || $data['vacancy_code'] === '') {
            return $this->respond([
                'status' => false,
                'message' => 'Missing or empty field: vacancy_code'
            ], 400);
        }



        $db = \Config\Database::connect();
        $builder = $db->table('tbl_vacancy');
        $updateData = [
            'Job_title'            => $data['Job_title'],
            'Department'           => $data['Department'],
            'Location'             => $data['Location'],
            'Employment_type'      => $data['Employment_type'],
            'Vacancy_count'        => (int)$data['Vacancy_count'],
            'Experience_required'  => $data['Experience_required'],
            'Qualification'        => $data['Qualification'],
            'Required_Skills'      => $data['Required_Skills'],
            'Salary_range'         => $data['Salary_range'],
            'Application_deadline' => $data['Application_deadline'],
            'Job_Description'      => $data['Job_Description'],
            'Key_Responsibilities' => $data['Key_Responsibilities'],
            'updated_at'           => date('Y-m-d H:i:s'),
            'updated_by'           => $userCode,
            'status'               => $data['status'],
        ];

        $builder->where('vacancy_code', $data['vacancy_code']);
        if ($builder->update($updateData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Vacancy updated successfully'
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update vacancy'
            ], 500);
        }
    }

    public function getallvacancy()
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
        $records = $db->table('tbl_vacancy')
            ->where('status', 'Y')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => true,
            'message' => 'list fetched successfully',
            'data' => $records
        ], 200);
    }

    public function getallvacancyaftertoday()
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
        $today = date('Y-m-d');

        $records = $db->table('tbl_vacancy')
            ->where('status', 'Y')
            ->where('Application_deadline >=', $today)
            ->get()
            ->getResultArray();

        return $this->respond([
            'status'  => true,
            'message' => 'List fetched successfully',
            'count'   => count($records),   // ✅ total vacancies
            'data'    => $records
        ], 200);
    }

    public function addachievement()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond([
                'status' => false,
                'message' => 'Unauthorized or Invalid Token'
            ], 401);
        }
        $userCode = $decoded->user_id ?? null;
        $data = $this->request->getJSON(true);
        $employee_code       = $data['employee_code'] ?? null;
        $day_of_achievement  = $data['day_of_achievement'] ?? null;
        $description         = $data['description'] ?? null;
        if (!$employee_code || !$day_of_achievement) {
            return $this->respond([
                'status' => false,
                'message' => 'Employee and Day of Achievement are required.'
            ], 400);
        }
        $db = \Config\Database::connect();
        try {
            $achievement_code = 'ACH' . date('YmdHis') . rand(1000, 9999);
            $insertData = [
                'achievement_code'     => $achievement_code,
                'employee_code'        => $employee_code,
                'day_of_achievement'   => $day_of_achievement,
                'description'          => $description,
                'created_by'           => $userCode,
                'created_at'           => date('Y-m-d H:i:s'),
            ];
            $insert = $db->table('tbl_achievement')->insert($insertData);
            if (!$insert) {
                $error = $db->error();
                throw new \Exception('Failed to insert achievement record: ' . json_encode($error));
            }
            return $this->respond([
                'status' => true,
                'message' => 'Achievement added successfully.',
                'achievement_code' => $achievement_code
            ], 200);
        } catch (\Throwable $e) {
            log_message('error', 'Error in addachievement: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Error adding achievement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function updateachievement()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $userCode = $decoded->user_id ?? null;
        $data = $this->request->getJSON(true);
        $achievement_code = $data['achievement_code'] ?? null;

        if (!$achievement_code) {
            return $this->respond(['status' => false, 'message' => 'Achievement code is required.'], 400);
        }

        $updateData = array_filter([
            'employee_code'      => $data['employee_code'] ?? null,
            'day_of_achievement' => $data['day_of_achievement'] ?? null,
            'description'        => $data['description'] ?? null,
            'updated_by'         => $userCode,
            'updated_at'         => date('Y-m-d H:i:s')
        ]);

        $db = \Config\Database::connect();

        try {
            $update = $db->table('tbl_achievement')
                ->where('achievement_code', $achievement_code)
                ->update($updateData);

            if (!$update) {
                throw new \Exception('Failed to update achievement');
            }

            return $this->respond([
                'status' => true,
                'message' => 'Achievement updated successfully.'
            ], 200);
        } catch (\Throwable $e) {
            log_message('error', 'Error in updateachievement: ' . $e->getMessage());
            return $this->respond(['status' => false, 'message' => 'Error updating achievement', 'error' => $e->getMessage()], 500);
        }
    }

    // ✅ 3. GET ACHIEVEMENT BY ID
    public function getAchievementById()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $data = $this->request->getJSON(true);
        $achievement_code = $data['achievement_code'] ?? null;

        if (!$achievement_code) {
            return $this->respond(['status' => false, 'message' => 'Achievement code is required.'], 400);
        }

        $db = \Config\Database::connect();
        $achievement = $db->table('tbl_achievement a')
            ->select('a.*, CONCAT(r.First_Name, " ", r.Last_Name) as employee_name')
            ->join('tbl_register r', 'a.employee_code = r.user_code', 'left')
            ->where('a.achievement_code', $achievement_code)
            ->get()
            ->getRow();

        if ($achievement) {
            return $this->respond([
                'status' => true,
                'message' => 'Achievement details fetched successfully.',
                'data' => $achievement
            ]);
        }

        return $this->respond([
            'status' => false,
            'message' => 'Achievement not found.'
        ], 404);
    }

    public function getAllAchievements()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $db = \Config\Database::connect();
        $achievements = $db->table('tbl_achievement a')
            ->select('a.*, CONCAT(r.First_Name, " ", r.Last_Name) as employee_name')
            ->join('tbl_register r', 'a.employee_code = r.user_code', 'left')
            ->orderBy('a.day_of_achievement', 'DESC')
            ->get()
            ->getResult();

        return $this->respond([
            'status' => true,
            'message' => 'All achievements fetched successfully.',
            'data' => $achievements
        ]);
    }

    public function getTodayAchievements()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $today = (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
        $db = \Config\Database::connect();

        $achievements = $db->table('tbl_achievement a')
            ->select('a.*, CONCAT(r.First_Name, " ", r.Last_Name) as employee_name')
            ->join('tbl_register r', 'a.employee_code = r.user_code', 'left')
            ->where('a.day_of_achievement', $today)
            ->get()
            ->getResult();

        return $this->respond([
            'status' => true,
            'message' => 'Today’s achievements fetched successfully.',
            'data' => $achievements
        ]);
    }


    public function deleteRecord()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // ✅ JWT validation
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $input = $this->request->getJSON(true);

        if (empty($input['table']) || empty($input['id_field']) || empty($input['id'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing required fields: table, id_field, or id'
            ], 400);
        }

        $table = $input['table'];
        $idField = $input['id_field'];
        $id = $input['id'];

        $model = new HomeModel();

        // ✅ Build condition dynamically
        $where = [$idField => $id];

        $deleted = $model->softDeleteRecord($table, $where);

        if ($deleted) {
            return $this->respond([
                'status' => true,
                'message' => 'Record deleted successfully'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to delete record or record not found'
            ]);
        }
    }

    public function softDeleteWithChildren()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        // Required inputs
        $parentTable = $input['parent_table'];
        $childTable = $input['child_table'];
        $keyField = $input['key_field'];    // debitnote_code or creditnote_code
        $keyValue = $input['key_value'];

        if (!$parentTable || !$childTable || !$keyField || !$keyValue) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing parameters'
            ], 400);
        }

        $db = \Config\Database::connect();

        // Soft delete parent
        $db->table($parentTable)
            ->where($keyField, $keyValue)
            ->update(['is_active' => 'N']);

        // Soft delete children
        $db->table($childTable)
            ->where($keyField, $keyValue)
            ->update(['is_active' => 'N']);

        return $this->respond([
            'status' => true,
            'message' => 'Record deleted successfully'
        ]);
    }
}
