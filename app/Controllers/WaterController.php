<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\HomeModel;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

class WaterController extends BaseController
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
     * Add new water supply record
     */
    public function add_water()
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
        
        $input = $this->request->getJSON(true);

        // Validate required fields
        $requiredFields = ['date', 'supplier_name'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                return $this->respond([
                    'status' => false,
                    'message' => "Field '$field' is required"
                ], 400);
            }
        }

        // Validate water items
        if (empty($input['water_items']) || !is_array($input['water_items'])) {
            return $this->respond([
                'status' => false,
                'message' => 'At least one water item is required'
            ], 400);
        }

        // Validate each water item
        foreach ($input['water_items'] as $index => $item) {
            if (empty($item['item_id'])) {
                return $this->respond([
                    'status' => false,
                    'message' => "Item ID is required for item " . ($index + 1)
                ], 400);
            }
            if (empty($item['qty']) || !is_numeric($item['qty']) || $item['qty'] <= 0) {
                return $this->respond([
                    'status' => false,
                    'message' => "Valid quantity is required for item " . ($index + 1)
                ], 400);
            }
        }

        $db = \Config\Database::connect();
        
        // Start transaction
        $db->transStart();

        try {
            // Insert main water record
            $waterData = [
                'date' => $input['date'],
                'supplier_name' => $input['supplier_name'],
                'created_by' => $created_by,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $db->table('tbl_waters')->insert($waterData);
            $water_id = $db->insertID();

            // Insert water items
            foreach ($input['water_items'] as $item) {
                // Get item details from tbl_items
                $itemDetails = $db->table('tbl_items')
                    ->select('item_name, price, unit')
                    ->where('item_id', $item['item_id'])
                    ->where('is_active', true)
                    ->get()
                    ->getRowArray();

                if (!$itemDetails) {
                    throw new \Exception("Item not found or inactive: " . $item['item_id']);
                }

                $waterItemData = [
                    'water_id' => $water_id,
                    'item_id' => $item['item_id'],
                    'item_name' => $itemDetails['item_name'],
                    'price' => $itemDetails['price'],
                    'unit' => $itemDetails['unit'],
                    'qty' => $item['qty'],
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $db->table('tbl_water_items')->insert($waterItemData);
            }

            $db->transComplete();

            if ($db->transStatus() === FALSE) {
                throw new \Exception('Transaction failed');
            }

            return $this->respond([
                'status' => true,
                'message' => 'Water supply record added successfully',
                'water_id' => $water_id
            ], 201);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond([
                'status' => false,
                'message' => 'Failed to add water supply record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific water record by water_id
     */
    public function get_water()
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

        $input = $this->request->getJSON(true);
        $water_id = $input['water_id'] ?? null;

        if (!$water_id) {
            return $this->respond([
                'status' => false,
                'message' => 'Water ID is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        
        // Get water record
        $water = $db->table('tbl_waters')
            ->where('water_id', $water_id)
            ->where('is_active', true)
            ->get()
            ->getRowArray();

        if (!$water) {
            return $this->respond([
                'status' => false,
                'message' => 'Water record not found'
            ], 404);
        }

        // Get water items
        $waterItems = $db->table('tbl_water_items')
            ->where('water_id', $water_id)
            ->get()
            ->getResultArray();

        $water['water_items'] = $waterItems;

        return $this->respond([
            'status' => true,
            'data' => $water
        ]);
    }

    /**
     * Get all water supply records
     */
    public function get_all_waters()
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
        
        // Get all active water records
        $waters = $db->table('tbl_waters')
            ->where('is_active', true)
            ->orderBy('date', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        if ($waters) {
            // Get water items for each record
            foreach ($waters as &$water) {
                $waterItems = $db->table('tbl_water_items')
                    ->where('water_id', $water['water_id'])
                    ->get()
                    ->getResultArray();
                $water['water_items'] = $waterItems;
            }

            return $this->respond([
                'status' => true,
                'data' => $waters
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No water supply records found'
            ], 404);
        }
    }

    /**
     * Get water records by employee_id
     */
    public function get_waters_by_employee_id()
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
        $employee_id = $input['employee_id'] ?? null;

        if (!$employee_id) {
            return $this->respond([
                'status' => false,
                'message' => 'Employee ID is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        
        // Get water records created by specific employee
        $waters = $db->table('tbl_waters')
            ->where('created_by', $employee_id)
            ->where('is_active', true)
            ->orderBy('date', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        if ($waters) {
            // Get water items for each record
            foreach ($waters as &$water) {
                $waterItems = $db->table('tbl_water_items')
                    ->where('water_id', $water['water_id'])
                    ->get()
                    ->getResultArray();
                $water['water_items'] = $waterItems;
            }

            return $this->respond([
                'status' => true,
                'data' => $waters
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No water supply records found for this employee'
            ], 404);
        }
    }

    /**
     * Update existing water supply record
     */
    public function update_water()
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
        $water_id = $input['water_id'] ?? null;

        if (!$water_id) {
            return $this->respond([
                'status' => false,
                'message' => 'Water ID is required'
            ], 400);
        }

        // Validate water items if provided
        if (isset($input['water_items']) && is_array($input['water_items'])) {
            foreach ($input['water_items'] as $index => $item) {
                if (empty($item['item_id'])) {
                    return $this->respond([
                        'status' => false,
                        'message' => "Item ID is required for item " . ($index + 1)
                    ], 400);
                }
                if (empty($item['qty']) || !is_numeric($item['qty']) || $item['qty'] <= 0) {
                    return $this->respond([
                        'status' => false,
                        'message' => "Valid quantity is required for item " . ($index + 1)
                    ], 400);
                }
            }
        }

        $db = \Config\Database::connect();
        
        // Start transaction
        $db->transStart();

        try {
            // Check if water record exists
            $water = $db->table('tbl_waters')
                ->where('water_id', $water_id)
                ->where('is_active', true)
                ->get()
                ->getRow();

            if (!$water) {
                throw new \Exception('Water record not found');
            }

            // Update main water record
            $waterData = [];
            
            if (isset($input['date'])) $waterData['date'] = $input['date'];
            if (isset($input['supplier_name'])) $waterData['supplier_name'] = $input['supplier_name'];
            
            if (!empty($waterData)) {
                $waterData['updated_by'] = $updated_by;
                $waterData['updated_at'] = date('Y-m-d H:i:s');
                
                $db->table('tbl_waters')
                    ->where('water_id', $water_id)
                    ->update($waterData);
            }

            // Update water items if provided
            if (isset($input['water_items']) && is_array($input['water_items'])) {
                // Delete existing water items
                $db->table('tbl_water_items')
                    ->where('water_id', $water_id)
                    ->delete();

                // Insert new water items
                foreach ($input['water_items'] as $item) {
                    // Get item details from tbl_items
                    $itemDetails = $db->table('tbl_items')
                        ->select('item_name, price, unit')
                        ->where('item_id', $item['item_id'])
                        ->where('is_active', true)
                        ->get()
                        ->getRowArray();

                    if (!$itemDetails) {
                        throw new \Exception("Item not found or inactive: " . $item['item_id']);
                    }

                    $waterItemData = [
                        'water_id' => $water_id,
                        'item_id' => $item['item_id'],
                        'item_name' => $itemDetails['item_name'],
                        'price' => $itemDetails['price'],
                        'unit' => $itemDetails['unit'],
                        'qty' => $item['qty'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];

                    $db->table('tbl_water_items')->insert($waterItemData);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === FALSE) {
                throw new \Exception('Transaction failed');
            }

            return $this->respond([
                'status' => true,
                'message' => 'Water supply record updated successfully'
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update water supply record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete water supply record (soft delete)
     */
    public function delete_water()
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
        $water_id = $input['water_id'] ?? null;

        if (!$water_id) {
            return $this->respond([
                'status' => false,
                'message' => 'Water ID is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        
        // Check if water record exists
        $water = $db->table('tbl_waters')
            ->where('water_id', $water_id)
            ->where('is_active', true)
            ->get()
            ->getRow();

        if (!$water) {
            return $this->respond([
                'status' => false,
                'message' => 'Water record not found'
            ], 404);
        }

        // Soft delete by setting is_active to false
        $data = [
            'is_active' => false,
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($db->table('tbl_waters')->where('water_id', $water_id)->update($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Water supply record deleted successfully'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to delete water supply record'
            ], 500);
        }
    }

    /**
     * Search water supply records
     */
    public function search_waters()
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
        $search_term = $input['search_term'] ?? '';

        $db = \Config\Database::connect();
        
        $waters = $db->table('tbl_waters w')
            ->select('w.*')
            ->where('w.is_active', true)
            ->groupStart()
                ->like('w.date', $search_term)
                ->orLike('w.supplier_name', $search_term)
                ->orWhereIn('w.water_id', function($builder) use ($search_term) {
                    $builder->select('water_id')
                            ->from('tbl_water_items')
                            ->groupStart()
                                ->like('item_name', $search_term)
                            ->groupEnd();
                })
            ->groupEnd()
            ->orderBy('w.date', 'DESC')
            ->orderBy('w.created_at', 'DESC')
            ->get()
            ->getResultArray();

        if ($waters) {
            // Get water items for each record
            foreach ($waters as &$water) {
                $waterItems = $db->table('tbl_water_items')
                    ->where('water_id', $water['water_id'])
                    ->get()
                    ->getResultArray();
                $water['water_items'] = $waterItems;
            }

            return $this->respond([
                'status' => true,
                'data' => $waters
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No water supply records found matching your search'
            ], 404);
        }
    }
}