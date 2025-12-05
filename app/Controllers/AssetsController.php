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

class AssetsController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->homeModel = new HomeModel();
        helper(['jwtvalidate']);
    }

    /**
     * Add new asset
     */
    public function add_assets()
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
        $asset_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 5);
        
        $input = $this->request->getPost();

        // Validate required fields - UPDATED: category, asset_no, asset_type are mandatory
        // description, purchase_price, current_value, purchase_date are optional
        $requiredFields = ['asset_no', 'category', 'asset_type'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                return $this->respond([
                    'status' => false,
                    'message' => "Field '$field' is required"
                ], 400);
            }
        }

        $data = [
            'asset_code'        => $asset_code,
            'asset_no'          => $input['asset_no'] ?? '', // CHANGED: from 'name' to 'asset_no'
            'asset_type'        => $input['asset_type'] ?? '', // NEW: asset_type field
            'description'       => $input['description'] ?? '', // OPTIONAL
            'purchase_price'    => $input['purchase_price'] ?? 0.00, // OPTIONAL
            'current_value'     => $input['current_value'] ?? 0.00, // OPTIONAL
            'purchase_date'     => $input['purchase_date'] ?? null, // OPTIONAL
            'category'          => $input['category'] ?? '', // MANDATORY
            'status'            => $input['status'] ?? 'Active',
            'is_active'         => 'Y',
            'created_by'        => $created_by
        ];

        // Validate numeric fields only if provided (they are optional now)
        if (isset($input['purchase_price']) && $input['purchase_price'] !== '') {
            if (!is_numeric($input['purchase_price']) || $input['purchase_price'] < 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Purchase price must be a valid positive number'
                ], 400);
            }
            $data['purchase_price'] = $input['purchase_price'];
        } else {
            $data['purchase_price'] = 0.00; // Default value for optional field
        }

        if (isset($input['current_value']) && $input['current_value'] !== '') {
            if (!is_numeric($input['current_value']) || $input['current_value'] < 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Current value must be a valid positive number'
                ], 400);
            }
            $data['current_value'] = $input['current_value'];
        } else {
            $data['current_value'] = 0.00; // Default value for optional field
        }

        $db = \Config\Database::connect();
        if ($db->table('tbl_assetss')->insert($data)) {
            return $this->respond([
                'status'        => true,
                'message'       => 'Asset added successfully',
                'asset_code'    => $asset_code
            ], 201);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to add asset'
            ], 500);
        }
    }

    /**
     * Get specific asset by asset_code
     */
    public function get_assets()
    {
        helper(['jwtvalidate', 'url']);

        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $request = $this->request->getJSON(true);
        $asset_code = $request['asset_code'] ?? null;

        if (!$asset_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $asset = $db->table('tbl_assetss')
            ->where('asset_code', $asset_code)
            ->get()
            ->getRowArray();

        if ($asset) {
            return $this->respond([
                'status' => true,
                'data'   => $asset
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Asset not found'
            ], 404);
        }
    }

    /**
     * Get all assets
     */
    public function getallAssets()
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
        $assets = $db->table('tbl_assetss')
            ->where('is_active', 'Y')
            ->orderBy('asset_no', 'ASC') // CHANGED: from 'name' to 'asset_no'
            ->get()
            ->getResultArray();

        if ($assets) {
            return $this->respond([
                'status' => true,
                'data'   => $assets
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No active assets found'
            ], 404);
        }
    }

    /**
     * Edit existing asset
     */
    public function editAssets()
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
        $asset_code = $input['asset_code'] ?? null;

        if (!$asset_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $data = [];

        // Update all fields including the new fields - UPDATED for new field structure
        if (isset($input['asset_no']))        $data['asset_no'] = $input['asset_no']; // CHANGED: from 'name' to 'asset_no'
        if (isset($input['asset_type']))      $data['asset_type'] = $input['asset_type']; // NEW: asset_type field
        if (isset($input['description']))     $data['description'] = $input['description'];
        if (isset($input['purchase_price']))  $data['purchase_price'] = $input['purchase_price'];
        if (isset($input['current_value']))   $data['current_value'] = $input['current_value'];
        if (isset($input['purchase_date']))   $data['purchase_date'] = $input['purchase_date'];
        if (isset($input['category']))        $data['category'] = $input['category'];
        if (isset($input['status']))          $data['status'] = $input['status'];

        // Validate numeric fields only if provided (they are optional now)
        if (isset($data['purchase_price']) && $data['purchase_price'] !== '') {
            if (!is_numeric($data['purchase_price']) || $data['purchase_price'] < 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Purchase price must be a valid positive number'
                ], 400);
            }
        } else if (isset($data['purchase_price']) && $data['purchase_price'] === '') {
            $data['purchase_price'] = 0.00; // Set default for empty optional field
        }

        if (isset($data['current_value']) && $data['current_value'] !== '') {
            if (!is_numeric($data['current_value']) || $data['current_value'] < 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Current value must be a valid positive number'
                ], 400);
            }
        } else if (isset($data['current_value']) && $data['current_value'] === '') {
            $data['current_value'] = 0.00; // Set default for empty optional field
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($data)) {
            return $this->respond([
                'status' => false,
                'message' => 'No fields provided for update.'
            ], 400);
        }

        $asset = $db->table('tbl_assetss')->where('asset_code', $asset_code)->get()->getRow();
        if (!$asset) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset not found.'
            ], 404);
        }

        if ($db->table('tbl_assetss')->where('asset_code', $asset_code)->update($data)) {
            return $this->respond([
                'status'  => true,
                'message' => 'Asset updated successfully'
            ]);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to update asset'
            ], 500);
        }
    }

    /**
     * Delete asset (soft delete)
     */
    public function deleteAssets()
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
        $asset_code = $request['asset_code'] ?? null;

        if (!$asset_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        
        // Check if asset exists
        $asset = $db->table('tbl_assetss')->where('asset_code', $asset_code)->get()->getRow();
        if (!$asset) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset not found'
            ], 404);
        }

        // Soft delete by setting is_active to 'N'
        $data = [
            'is_active' => 'N',
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($db->table('tbl_assetss')->where('asset_code', $asset_code)->update($data)) {
            return $this->respond([
                'status'  => true,
                'message' => 'Asset deleted successfully'
            ]);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to delete asset'
            ], 500);
        }
    }
}