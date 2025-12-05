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

class SchemeController extends ResourceController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    private function generateSchemeId()
    {
        helper('text');
        return strtoupper(random_string('alnum', 6));
    }


    public function addScheme()
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
        if (!$created_by) {
            return $this->respond([
                'status' => false,
                'message' => 'User code not found in token'
            ], 400);
        }
        $data = $this->request->getJSON(true);

        $schemeId = $this->generateSchemeId();

        $insertData = [
            'scheme_id'     => $schemeId,
            'scheme_name'   => $data['name'] ?? '',
            'category_id'  => $data['category_id'] ?? '',
            'created_by'    => $created_by,
            'created_at'    => date('Y-m-d H:i:s')
        ];

        $builder = $this->db->table('tbl_scheme');
        $builder->insert($insertData);

        return $this->respond([
            'status' => true,
            'message' => 'Scheme added successfully',
            'scheme_id' => $schemeId
        ]);
    }

    public function editScheme()
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
        $updated_by = $decodedToken->user_id ?? null;
        if (!$updated_by) {
            return $this->respond([
                'status' => false,
                'message' => 'User code not found in token'
            ], 400);
        }
        $data = $this->request->getJSON(true);
        $scheme_id = $data['scheme_id'] ?? '';
        $updateData = [
            'scheme_name'   => $data['name'] ?? '',
            'is_active'     => $data['is_active'] ?? 'Y',
            'updated_by'    => $updated_by,
            'updated_at'    => date('Y-m-d H:i:s')
        ];

        $builder = $this->db->table('tbl_scheme');
        $builder->where('scheme_id', $scheme_id)->update($updateData);

        return $this->respond([
            'status' => true,
            'message' => 'Scheme updated successfully'
        ]);
    }

    public function getSchemeById()
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
        $scheme_id = $data['scheme_id'] ?? '';
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User code not found in token'
            ], 400);
        }
        $builder = $this->db->table('tbl_scheme');
        $scheme = $builder->where('scheme_id', $scheme_id)->get()->getRow();

        if ($scheme) {
            return $this->respond([
                'status' => true,
                'data' => $scheme
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Scheme not found'
            ], 404);
        }
    }

    public function getAllSchemes()
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
        $builder = $this->db->table('tbl_scheme');
        $builder->where('is_active', 'Y');
        $schemes = $builder->get()->getResult();

        return $this->respond([
            'status' => true,
            'data' => $schemes
        ]);
    }

    public function getcategories()
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
        $builder = $this->db->table('tbl_scheme_categories');
        $builder->where('is_active', 'Y');
        $schemes = $builder->get()->getResult();

        return $this->respond([
            'status' => true,
            'data' => $schemes
        ]);
    }

    public function getsubSchemebycategories()
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
        $categoryId = $requestData['category_id'] ?? null;

        if (!$categoryId) {
            return $this->respond([
                'status' => false,
                'message' => 'Category ID is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_scheme');

        $builder->select('scheme_id, scheme_name');
        $builder->where('category_id', $categoryId);
        $builder->where('is_active', 'Y');
        $builder->orderBy('scheme_name', 'ASC');

        $query = $builder->get();
        $result = $query->getResult();

        if (!empty($result)) {
            return $this->respond([
                'status' => true,
                'data' => $result
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No active schemes found for this category'
            ]);
        }
    }
}
