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

class HolidayController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = Database::connect();
    }


    public function addHoliday()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true);

        // Generate unique holiday_code (e.g., HOLICPL001)
        $lastHoliday = $this->db->table('tbl_holiday_mst')
            ->select('holiday_code')
            ->like('holiday_code', 'HOLICPL')
            ->orderBy('holiday_code', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($lastHoliday && preg_match('/HOLICPL(\d+)/', $lastHoliday->holiday_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $newHolidayCode = 'HOLICPL' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Validation Rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'holiday_name' => 'required|max_length[255]',
            'holiday_date' => 'required|valid_date|is_unique[tbl_holiday_mst.holiday_date]', // Ensure date is unique
            'description' => 'permit_empty',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $insertData = [
            'holiday_code' => $newHolidayCode,
            'holiday_name' => $request['holiday_name'],
            'holiday_date' => $request['holiday_date'],
            'description' => $request['description'] ?? null,
            'is_active' => 'Y',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $createdBy,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $createdBy,
        ];

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_holiday_mst');
            if ($builder->insert($insertData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Holiday added successfully.',
                    'holiday_code' => $newHolidayCode
                ]);
            } else {
                $this->db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to add holiday to database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function updateHoliday()
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
            'holiday_code' => 'required|max_length[50]|is_not_unique[tbl_holiday_mst.holiday_code]', // Ensure code exists
            'holiday_name' => 'permit_empty|max_length[255]',
            'holiday_date' => 'permit_empty|valid_date|is_unique[tbl_holiday_mst.holiday_date,holiday_code,{holiday_code}]', // Unique, but ignore current record
            'description' => 'permit_empty',
            'is_active' => 'permit_empty|in_list[Y,N]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $holidayCode = $request['holiday_code'];
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updatedBy,
        ];

        if (isset($request['holiday_name'])) $updateData['holiday_name'] = $request['holiday_name'];
        if (isset($request['holiday_date'])) $updateData['holiday_date'] = $request['holiday_date'];
        if (isset($request['description'])) $updateData['description'] = $request['description'];
        if (isset($request['is_active'])) $updateData['is_active'] = $request['is_active'];

        // Ensure there's something to update besides timestamps
        if (count($updateData) <= 2) { // updated_at and updated_by are always there
            return $this->respond(['status' => false, 'message' => 'No fields provided for update.'], 400);
        }

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_holiday_mst');
            $builder->where('holiday_code', $holidayCode);

            if ($builder->update($updateData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Holiday updated successfully.',
                    'holiday_code' => $holidayCode
                ]);
            } else {
                $this->db->transRollback();
                // Check if the record actually existed but no changes were made
                if ($this->db->affectedRows() === 0) {
                    return $this->respond(['status' => false, 'message' => 'Holiday not found or no changes were made.'], 404);
                }
                return $this->respond(['status' => false, 'message' => 'Failed to update holiday in database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function getAllHolidays()
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
        $holiday_code = $request['holiday_code'] ?? null;
        $year = $request['year'] ?? null; // Optional: filter by year

        try {
            $builder = $this->db->table('tbl_holiday_mst');
            $builder->where('is_active', 'Y');

            if ($holiday_code) {
                $builder->where('holiday_code', $holiday_code);
            }
            
            if ($year) {
                // Validate year to prevent SQL injection or invalid dates
                if (!is_numeric($year) || strlen($year) !== 4) {
                    return $this->respond(['status' => false, 'message' => 'Invalid year format. Year must be a 4-digit number.'], 400);
                }
                $builder->where("YEAR(holiday_date)", $year);
            }

            $data = ($holiday_code) ? $builder->get()->getRow() : $builder->get()->getResult();

            if ($data) {
                return $this->respond(['status' => true, 'data' => $data]);
            } else {
                return $this->respond(['status' => false, 'message' => 'Holiday(s) not found.'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
