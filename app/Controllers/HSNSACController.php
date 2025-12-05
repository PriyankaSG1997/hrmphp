<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use Config\Services;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

class HSNSACController extends BaseController
{
    protected $db;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
        helper(['jwtvalidate']);
    }

    /**
     * Add new HSN/SAC
     */
    public function add_HSNSAC()
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

        // Generate unique HSN/SAC code
        $HSNSAC_code = 'HSN' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);

        $input = $this->request->getPost();

        // Validate required fields
        if (empty($input['HSNSAC_number']) || empty($input['sector']) || empty($input['company_code'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Company, HSN/SAC Number and Sector are required fields'
            ], 400);
        }

        // Check if HSN/SAC number already exists
        $existing = $this->db->table('tbl_hsnsac')
            ->where('HSNSAC_number', $input['HSNSAC_number'])
            ->countAllResults();

        if ($existing > 0) {
            return $this->respond([
                'status' => false,
                'message' => 'HSN/SAC Number already exists'
            ], 400);
        }

        $data = [
            'company_code' => $input['company_code'],
            'gst_number' => $input['gst_number'] ?? null,
            'HSNSAC_code' => $HSNSAC_code,
            'HSNSAC_number' => $input['HSNSAC_number'] ?? '',
            'sector' => $input['sector'] ?? '',
            'description' => $input['description'] ?? '',
            'is_active' => 'Y',
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            if ($this->db->table('tbl_hsnsac')->insert($data)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'HSN/SAC added successfully',
                    'HSNSAC_code' => $HSNSAC_code,
                    'data' => $data
                ], 201);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to add HSN/SAC'
                ], 500);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all HSN/SAC records
     */
    public function getallHSNSAC()
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
            $builder = $this->db->table('tbl_hsnsac');

            // Get search parameter if provided
            $search = $this->request->getGet('search');
            if (!empty($search)) {
                $builder->groupStart()
                    ->like('HSNSAC_number', $search)
                    ->orLike('sector', $search)
                    ->orLike('description', $search)
                    ->groupEnd();
            }

            // Get status filter if provided
            $status = $this->request->getGet('status');
            if ($status === 'active') {
                $builder->where('is_active', 'Y');
            } elseif ($status === 'inactive') {
                $builder->where('is_active', 'N');
            } else {
                // default
                $builder->where('is_active', 'Y');
            }


            // Order by latest first
            $builder->orderBy('created_at', 'DESC');

            $HSNSACs = $builder->get()->getResultArray();

            if ($HSNSACs) {
                return $this->respond([
                    'status' => true,
                    'data' => $HSNSACs,
                    'count' => count($HSNSACs)
                ]);
            } else {
                return $this->respond([
                    'status' => true,
                    'data' => [],
                    'count' => 0,
                    'message' => 'No HSN/SAC records found'
                ]);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to fetch HSN/SAC records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single HSN/SAC record by code
     */
    public function getHSNSAC()
    {
        helper(['jwtvalidate']);

        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $request = $this->request->getJSON(true);
        $HSNSAC_code = $request['HSNSAC_code'] ?? null;

        if (!$HSNSAC_code) {
            return $this->respond([
                'status' => false,
                'message' => 'HSN/SAC code is required'
            ], 400);
        }

        try {
            $HSNSAC = $this->db->table('tbl_hsnsac')
                ->where('HSNSAC_code', $HSNSAC_code)
                ->get()
                ->getRowArray();

            if ($HSNSAC) {
                return $this->respond([
                    'status' => true,
                    'data' => $HSNSAC
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'HSN/SAC not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit HSN/SAC record
     */
    public function editHSNSAC()
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
        $input = $this->request->getPost();
        $HSNSAC_code = $input['HSNSAC_code'] ?? null;

        if (!$HSNSAC_code) {
            return $this->respond([
                'status' => false,
                'message' => 'HSN/SAC code is required'
            ], 400);
        }

        // Check if HSN/SAC exists
        $existing = $this->db->table('tbl_hsnsac')
            ->where('HSNSAC_code', $HSNSAC_code)
            ->countAllResults();

        if ($existing === 0) {
            return $this->respond([
                'status' => false,
                'message' => 'HSN/SAC not found'
            ], 404);
        }

        // Check if new HSN/SAC number already exists (if being updated)
        if (isset($input['HSNSAC_number'])) {
            $duplicate = $this->db->table('tbl_hsnsac')
                ->where('HSNSAC_number', $input['HSNSAC_number'])
                ->where('HSNSAC_code !=', $HSNSAC_code)
                ->countAllResults();

            if ($duplicate > 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'HSN/SAC Number already exists'
                ], 400);
            }
        }

        $data = [];

        if (isset($input['HSNSAC_number']))
            $data['HSNSAC_number'] = $input['HSNSAC_number'];
        if (isset($input['sector']))
            $data['sector'] = $input['sector'];
        if (isset($input['description']))
            $data['description'] = $input['description'];
        if (isset($input['is_active']))
            $data['is_active'] = $input['is_active'];

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($data)) {
            return $this->respond([
                'status' => false,
                'message' => 'No fields provided for update.'
            ], 400);
        }

        try {
            if ($this->db->table('tbl_hsnsac')->where('HSNSAC_code', $HSNSAC_code)->update($data)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'HSN/SAC updated successfully',
                    'data' => $data
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to update HSN/SAC'
                ], 500);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete HSN/SAC record (soft delete)
     */
    public function deleteHSNSAC()
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
        $HSNSAC_code = $request['HSNSAC_code'] ?? null;

        if (!$HSNSAC_code) {
            return $this->respond([
                'status' => false,
                'message' => 'HSN/SAC code is required'
            ], 400);
        }

        try {
            // Check if HSN/SAC exists
            $HSNSAC = $this->db->table('tbl_hsnsac')
                ->where('HSNSAC_code', $HSNSAC_code)
                ->get()
                ->getRowArray();

            if (!$HSNSAC) {
                return $this->respond([
                    'status' => false,
                    'message' => 'HSN/SAC not found'
                ], 404);
            }

            // Soft delete by setting is_active to 'N'
            $data = [
                'is_active' => 'N',
                'updated_by' => $decodedToken->user_id ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($this->db->table('tbl_hsnsac')->where('HSNSAC_code', $HSNSAC_code)->update($data)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'HSN/SAC deleted successfully'
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to delete HSN/SAC'
                ], 500);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search HSN/SAC records
     */
    public function searchHSNSAC()
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

        $searchTerm = $this->request->getGet('q') ?? $this->request->getGet('search') ?? '';

        if (empty($searchTerm)) {
            return $this->respond([
                'status' => false,
                'message' => 'Search term is required'
            ], 400);
        }

        try {
            $HSNSACs = $this->db->table('tbl_hsnsac')
                ->groupStart()
                ->like('HSNSAC_number', $searchTerm)
                ->orLike('sector', $searchTerm)
                ->orLike('description', $searchTerm)
                ->groupEnd()
                ->where('is_active', 'Y')
                ->orderBy('HSNSAC_number', 'ASC')
                ->get()
                ->getResultArray();

            return $this->respond([
                'status' => true,
                'data' => $HSNSACs,
                'count' => count($HSNSACs)
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
