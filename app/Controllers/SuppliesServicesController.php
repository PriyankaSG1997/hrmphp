<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use Config\Database;


class SuppliesServicesController extends BaseController
{

    use ResponseTrait;
    /**
     * @var \CodeIgniter\Database\BaseConnection
     */
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }
    // ----------------------
    // TEA & SNACKS API
    // ----------------------
    public function addTeaSnacks()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? 'system';

        $input = $this->request->getJSON(true);

        if (!$input) {
            return $this->respond(['status' => false, 'message' => 'Invalid Data'], 422);
        }

        // Extract fields
        $date  = $input['date'] ?? null;
        $slot  = $input['time_slot'] ?? null;

        if (!$date || !$slot) {
            return $this->respond(['status' => false, 'message' => 'Date and Time Slot are required'], 422);
        }

        // 🔢 Generate tea_snacks_id like TSK001
        $last = $this->db->table('tbl_tea_snacks')
            ->select('tea_snacks_id')
            ->like('tea_snacks_id', 'TSK', 'after')
            ->orderBy('tea_snacks_id', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($last && preg_match('/TSK(\d+)/', $last->tea_snacks_id, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }

        $newId = 'TSK' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Current datetime
        $dt = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
        $currentTime = $dt->format('Y-m-d H:i:s');

        // Prepare data
        $data = [
            'tea_snacks_id' => $newId,
            'date'          => $date,
            'time_slot'     => $slot,
            'tea_qty'       => $input['tea_qty'] ?? 0,
            'coffee_qty'    => $input['coffee_qty'] ?? 0,
            'snack_type'    => $input['snack_type'] ?? null,
            'snack_qty'     => $input['snack_qty'] ?? 0,

            'created_by'    => $createdBy,
            'created_at'    => $currentTime,
            'updated_by'    => $createdBy,
            'updated_at'    => $currentTime
        ];

        // Insert
        $this->db->table('tbl_tea_snacks')->insert($data);

        return $this->respond([
            'status' => true,
            'message' => 'Tea, Coffee & Snacks Added Successfully',
            'tea_snacks_id' => $newId
        ]);
    }

    public function editTeaSnacks()
    {
        $request = $this->request->getJSON(true);

        if (!isset($request['tea_snacks_id'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Tea Snacks ID is required'
            ], 400);
        }

        $teaSnacksId = $request['tea_snacks_id'];

        // Data to update
        $data = [
            'date'        => $request['date'],
            'time_slot'   => $request['time_slot'],
            'tea_qty'     => $request['tea_qty'],
            'coffee_qty'  => $request['coffee_qty'],
            'snack_type'  => $request['snack_type'],
            'snack_qty'   => $request['snack_qty'],
            'updated_at'  => date('Y-m-d H:i:s'),
            'updated_by'  => $request['updated_by'] ?? 'SYSTEM'
        ];

        $builder = $this->db->table('tbl_tea_snacks');
        $builder->where('tea_snacks_id', $teaSnacksId);
        $updated = $builder->update($data);

        if ($updated) {
            return $this->respond([
                'status' => true,
                'message' => 'Tea & Snacks updated successfully.'
            ], 200);
        }

        return $this->respond([
            'status' => false,
            'message' => 'Failed to update Tea & Snacks.'
        ], 500);
    }


    public function getTeaSnacks()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getHeaderLine('Authorization');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = $this->request->getJSON(true);

        $date = $input['date'] ?? null;
        $timeSlot = $input['time_slot'] ?? null;
        $isActive = $input['is_active'] ?? 'Y';

        $builder = $this->db->table('tbl_tea_snacks');

        // Apply filters ONLY if provided
        if (!empty($date)) {
            $builder->where('date', $date);
        }

        if (!empty($timeSlot)) {
            $builder->where('time_slot', $timeSlot);
        }

        // Always apply is_active
        $builder->where('is_active', $isActive);

        $records = $builder->orderBy('tea_snacks_id', 'DESC')->get()->getResultArray();

        return $this->respond([
            'status' => true,
            'message' => 'Tea & Snacks list fetched successfully.',
            'data' => $records
        ]);
    }




    // ----------------------
    // HOUSEKEEPING API
    // ----------------------
    public function addHousekeeping()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // 🔒 JWT Check
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? 'system';

        // ✅ Accept JSON OR form-data
        $input = $this->request->getJSON(true); // returns array OR null

        if ($input) {
            // JSON request
            $housekeeping_date = $input['housekeeping_date'] ?? null;
            $staff_name = $input['staff_name'] ?? null;
        } else {
            // form-data request
            $housekeeping_date = $this->request->getPost('housekeeping_date');
            $staff_name = $this->request->getPost('staff_name');
        }

        // ❗ Validation
        if (!$housekeeping_date || !$staff_name) {
            return $this->respond(['status' => false, 'message' => 'Date and staff name required'], 422);
        }

        // 🔢 Generate housekeeping_id like HSK001
        $last = $this->db->table('tbl_housekeeping_records')
            ->select('housekeeping_id')
            ->like('housekeeping_id', 'HSK', 'after')
            ->orderBy('housekeeping_id', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($last && preg_match('/HSK(\d+)/', $last->housekeeping_id, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }

        $newId = 'HSK' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // ⏱ Current time (Asia/Kolkata)
        $dt = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
        $currentTime = $dt->format('Y-m-d H:i:s');

        // 📌 Prepare data
        $data = [
            'housekeeping_id'   => $newId,
            'housekeeping_date' => $housekeeping_date,
            'staff_name'        => $staff_name,
            'created_by'        => $createdBy,
            'updated_by'        => $createdBy,
            'created_at'        => $currentTime,
            'updated_at'        => $currentTime
        ];

        // Insert record
        $this->db->table(' tbl_housekeeping_records')->insert($data);

        return $this->respond([
            'status' => true,
            'message' => 'Housekeeping record added',
            'housekeeping_id' => $newId
        ]);
    }

    public function editHousekeeping()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // 🔒 JWT check
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $decoded = validatejwt($headers);
        $updatedBy = $decoded->user_id ?? 'system';

        // Accept JSON or form-data
        $input = $this->request->getJSON(true);

        if ($input) {
            // JSON request
            $housekeeping_id = $input['housekeeping_id'] ?? null;
            $housekeeping_date = $input['housekeeping_date'] ?? null;
            $staff_name = $input['staff_name'] ?? null;
        } else {
            // form-data request
            $housekeeping_id = $this->request->getPost('housekeeping_id');
            $housekeeping_date = $this->request->getPost('housekeeping_date');
            $staff_name = $this->request->getPost('staff_name');
        }

        // ❗ Validation
        if (!$housekeeping_id) {
            return $this->respond(['status' => false, 'message' => 'housekeeping_id required'], 422);
        }

        // Check if record exists
        $record = $this->db->table(' tbl_housekeeping_records')
            ->where('housekeeping_id', $housekeeping_id)
            ->get()
            ->getRow();

        if (!$record) {
            return $this->respond(['status' => false, 'message' => 'Record not found'], 404);
        }

        // Prepare update data
        $updateData = [];

        if ($housekeeping_date) $updateData['housekeeping_date'] = $housekeeping_date;
        if ($staff_name) $updateData['staff_name'] = $staff_name;

        // If nothing to update
        if (empty($updateData)) {
            return $this->respond(['status' => false, 'message' => 'No fields to update'], 422);
        }

        // Set updated values
        $dt = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
        $updateData['updated_by'] = $updatedBy;
        $updateData['updated_at'] = $dt->format('Y-m-d H:i:s');

        // Perform update
        $this->db->table(' tbl_housekeeping_records')
            ->where('housekeeping_id', $housekeeping_id)
            ->update($updateData);

        return $this->respond([
            'status' => true,
            'message' => 'Housekeeping record updated successfully',
            'updated_id' => $housekeeping_id
        ]);
    }


    public function getAllHousekeepings()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $records = $this->db->table('tbl_housekeeping_records')
                ->where('is_active', 'Y')
                ->orderBy('housekeeping_date', 'DESC')
                ->get()
                ->getResult();

            return $this->respond([
                'status' => true,
                'message' => 'Housekeeping records fetched successfully',
                'data' => $records
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error fetching records: ' . $e->getMessage()
            ], 500);
        }
    }



    // You can add Tea & Snacks API



}
