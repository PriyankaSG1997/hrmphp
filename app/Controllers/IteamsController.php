<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\HomeModel;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

class IteamsController extends BaseController
{
    protected $db;
    protected $homeModel;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->homeModel = new HomeModel();
        helper(['jwtvalidate']);
    }

    /**
     * Add new item
     */
    public function add_item()
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

        // Generate item code
        $item_code = 'ITM' . date('Ymd') . substr(str_shuffle('0123456789'), 0, 4);

        $input = $this->request->getJSON(true);

        // Validate required fields
        $requiredFields = ['vendor_code', 'item_name', 'unit', 'price'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                return $this->respond([
                    'status' => false,
                    'message' => "Field '$field' is required"
                ], 400);
            }
        }

        // Validate price
        if (!is_numeric($input['price']) || $input['price'] < 0) {
            return $this->respond([
                'status' => false,
                'message' => 'Price must be a valid positive number'
            ], 400);
        }

        $data = [
            'item_code' => $item_code,
            'vendor_code' => $input['vendor_code'],
            'item_name' => $input['item_name'],
            'description' => $input['description'] ?? '',
            'unit' => $input['unit'],
            'price' => $input['price'],
            'category' => $input['category'] ?? '',
            'is_active' => $input['is_active'] ?? true,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $db = \Config\Database::connect();

        // Check if item with same name and vendor already exists
        $existingItem = $db->table('tbl_items')
            ->where('item_name', $data['item_name'])
            ->where('vendor_code', $data['vendor_code'])
            ->where('is_active', true)
            ->get()
            ->getRowArray();

        if ($existingItem) {
            return $this->respond([
                'status' => false,
                'message' => 'Item with this name already exists for the selected vendor'
            ], 400);
        }

        if ($db->table('tbl_items')->insert($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Item added successfully',
                'item_code' => $item_code
            ], 201);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to add item'
            ], 500);
        }
    }

    /**
     * Get specific item by item_code
     */
    public function get_item()
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
        $item_code = $request['item_code'] ?? null;

        if (!$item_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Item code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $item = $db->table('tbl_items')
            ->where('item_code', $item_code)
            ->get()
            ->getRowArray();

        if ($item) {
            return $this->respond([
                'status' => true,
                'data' => $item
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Item not found'
            ], 404);
        }
    }

    /**
     * Get all items
     */
    public function get_all_items()
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

        // Get all active items with vendor information
        $items = $db->table('tbl_items i')
            ->select('i.*, v.name as vendor_name')
            ->join('tbl_vendor v', 'i.vendor_code = v.vendor_code', 'left')
            ->where('i.is_active', true)
            ->orderBy('i.item_name', 'ASC')
            ->get()
            ->getResultArray();

        if ($items) {
            return $this->respond([
                'status' => true,
                'data' => $items
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No active items found'
            ], 404);
        }
    }

    /**
     * Get items by vendor
     */
    public function get_items_by_vendor()
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
        $vendor_code = $request['vendor_code'] ?? null;

        if (!$vendor_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Vendor code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $items = $db->table('tbl_items')
            ->where('vendor_code', $vendor_code)
            ->where('is_active', true)
            ->orderBy('item_name', 'ASC')
            ->get()
            ->getResultArray();

        if ($items) {
            return $this->respond([
                'status' => true,
                'data' => $items
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No items found for this vendor'
            ], 404);
        }
    }

    /**
     * Update existing item
     */
    public function update_item()
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
        $input = $this->request->getJSON(true);
        $item_id = $input['item_id'] ?? null;

        if (!$item_id) {
            return $this->respond([
                'status' => false,
                'message' => 'Item ID is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $data = [];

        // Update fields
        if (isset($input['vendor_code']))
            $data['vendor_code'] = $input['vendor_code'];
        if (isset($input['item_name']))
            $data['item_name'] = $input['item_name'];
        if (isset($input['description']))
            $data['description'] = $input['description'];
        if (isset($input['unit']))
            $data['unit'] = $input['unit'];
        if (isset($input['price']))
            $data['price'] = $input['price'];
        if (isset($input['category']))
            $data['category'] = $input['category'];
        if (isset($input['is_active']))
            $data['is_active'] = $input['is_active'];

        // Validate price if provided
        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            return $this->respond([
                'status' => false,
                'message' => 'Price must be a valid positive number'
            ], 400);
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($data)) {
            return $this->respond([
                'status' => false,
                'message' => 'No fields provided for update.'
            ], 400);
        }

        // Check if item exists
        $item = $db->table('tbl_items')->where('item_id', $item_id)->get()->getRow();
        if (!$item) {
            return $this->respond([
                'status' => false,
                'message' => 'Item not found.'
            ], 404);
        }

        // Check for duplicate item name for the same vendor
        if (isset($data['item_name']) && isset($data['vendor_code'])) {
            $existingItem = $db->table('tbl_items')
                ->where('item_name', $data['item_name'])
                ->where('vendor_code', $data['vendor_code'])
                ->where('item_id !=', $item_id)
                ->where('is_active', true)
                ->get()
                ->getRowArray();

            if ($existingItem) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Item with this name already exists for the selected vendor'
                ], 400);
            }
        }

        if ($db->table('tbl_items')->where('item_id', $item_id)->update($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Item updated successfully'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update item'
            ], 500);
        }
    }

    /**
     * Delete item (soft delete)
     */
    public function delete_item()
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

        $input = $this->request->getJSON(true);
        $item_id = $request['item_id'] ?? null;

        if (!$item_id) {
            return $this->respond([
                'status' => false,
                'message' => 'Item ID is required'
            ], 400);
        }

        $db = \Config\Database::connect();

        // Check if item exists
        $item = $db->table('tbl_items')->where('item_id', $item_id)->get()->getRow();
        if (!$item) {
            return $this->respond([
                'status' => false,
                'message' => 'Item not found'
            ], 404);
        }

        // Soft delete by setting is_active to false
        $data = [
            'is_active' => false,
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($db->table('tbl_items')->where('item_id', $item_id)->update($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Item deleted successfully'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to delete item'
            ], 500);
        }
    }

    /**
     * Search items
     */
    public function search_items()
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
        $search_term = $request['search_term'] ?? '';

        $db = \Config\Database::connect();

        $items = $db->table('tbl_items i')
            ->select('i.*, v.name as vendor_name')
            ->join('tbl_vendor v', 'i.vendor_code = v.vendor_code', 'left')
            ->where('i.is_active', true)
            ->groupStart()
            ->like('i.item_name', $search_term)
            ->orLike('i.item_code', $search_term)
            ->orLike('i.description', $search_term)
            ->orLike('i.category', $search_term)
            ->orLike('v.name', $search_term)
            ->groupEnd()
            ->orderBy('i.item_name', 'ASC')
            ->get()
            ->getResultArray();

        if ($items) {
            return $this->respond([
                'status' => true,
                'data' => $items
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No items found matching your search'
            ], 404);
        }
    }
}