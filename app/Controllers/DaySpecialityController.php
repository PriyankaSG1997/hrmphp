<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;

class DaySpecialityController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = \Config\Database::connect();
    }

    // ============================================================
    // ADD SPECIAL DAY
    // ============================================================
    public function addDaySpeciality()
    {
        helper(['jwtvalidate_helper', 'form', 'filesystem']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // ✅ JWT validation
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? 'system';

        // ✅ Get form-data fields
        $show_on_date = $this->request->getPost('show_on_date');
        $title = $this->request->getPost('title');
        $description = $this->request->getPost('description');

        // ✅ Handle image upload (optional)
        $imageFile = $this->request->getFile('image');
        $imagePath = '';

        if ($imageFile && $imageFile->isValid() && !$imageFile->hasMoved()) {
            $newName = $imageFile->getRandomName();
            $uploadPath = ROOTPATH . 'public/uploads/day_speciality/';

            // Create folder if not exists
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $imageFile->move($uploadPath, $newName);
            $imagePath = 'public/uploads/day_speciality/' . $newName; // ✅ path saved in DB
        }

        // ✅ Generate unique specialday_code (SPCDAY001)
        $last = $this->db->table('tbl_day_speciality')
            ->select('specialday_code')
            ->like('specialday_code', 'SPCDAY', 'after')
            ->orderBy('specialday_code', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($last && preg_match('/SPCDAY(\d+)/', $last->specialday_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }

        $newCode = 'SPCDAY' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // ✅ Prepare insert data
        $data = [
            'specialday_code' => $newCode,
            'show_on_date' => $show_on_date,
            'title' => $title,
            'image' => $imagePath,
            'description' => $description,
            'created_by' => $createdBy,
            'updated_by' => $createdBy
        ];

        // ✅ Transactional insert
        $this->db->transStart();
        try {
            $this->db->table('tbl_day_speciality')->insert($data);
            $this->db->transComplete();

            return $this->respond([
                'status' => true,
                'message' => 'Day speciality added successfully.',
                'specialday_code' => $newCode,
                'image_path' => $imagePath
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }



    // ============================================================
    // UPDATE
    // ============================================================
    public function updateDaySpeciality()
    {
        helper(['jwtvalidate_helper', 'form', 'filesystem']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // ✅ JWT validation
        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        // log_message('info', 'Decoded JWT Data: ' . print_r($decoded, true));

        $updatedBy = $decoded->user_id ?? 'system';
        $specialday_code = $this->request->getPost('specialday_code');

        if (empty($specialday_code)) {
            return $this->respond(['status' => false, 'message' => 'specialday_code is required'], 400);
        }

        // ✅ Fetch existing record to handle old image removal
        $existing = $this->db->table('tbl_day_speciality')
            ->where('specialday_code', $specialday_code)
            ->get()
            ->getRow();

        if (!$existing) {
            return $this->respond(['status' => false, 'message' => 'Record not found.'], 404);
        }

        // ✅ Get new values (only update what is sent)
        $update = [
            'updated_by' => $updatedBy,
        ];

        if ($this->request->getPost('show_on_date')) {
            $update['show_on_date'] = $this->request->getPost('show_on_date');
        }
        if ($this->request->getPost('title')) {
            $update['title'] = $this->request->getPost('title');
        }
        if ($this->request->getPost('description')) {
            $update['description'] = $this->request->getPost('description');
        }

        // ✅ Handle image upload (optional)
        $imageFile = $this->request->getFile('image');
        if ($imageFile && $imageFile->isValid() && !$imageFile->hasMoved()) {
            $newName = $imageFile->getRandomName();
            $uploadPath = ROOTPATH . 'public/uploads/day_speciality/';

            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $imageFile->move($uploadPath, $newName);
            $newImagePath = 'public/uploads/day_speciality/' . $newName;

            // ✅ Delete old image file if exists
            if (!empty($existing->image)) {
                $oldImagePath = ROOTPATH . 'public/' . $existing->image;
                if (is_file($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            $update['image'] = $newImagePath;
        }

        // ✅ Perform update
        $this->db->transStart();
        try {
            $this->db->table('tbl_day_speciality')
                ->where('specialday_code', $specialday_code)
                ->update($update);
            $this->db->transComplete();

            return $this->respond([
                'status' => true,
                'message' => 'Day speciality updated successfully.',
                'specialday_code' => $specialday_code
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    // ============================================================
    // GET ALL / ONE
    // ============================================================
    public function getAllDaySpecialities()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $request = $this->request->getJSON(true);
        $code = $request['specialday_code'] ?? null;

        try {
            $builder = $this->db->table('tbl_day_speciality');
            $builder->where('is_active', 'Y');

            if ($code) {
                $builder->where('specialday_code', $code);
                $data = $builder->get()->getRow();
            } else {
                $data = $builder->orderBy('show_on_date', 'ASC')->get()->getResult();
            }

            if ($data) {
                return $this->respond(['status' => true, 'data' => $data]);
            } else {
                return $this->respond(['status' => false, 'message' => 'No records found.'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Unexpected error: ' . $e->getMessage()], 500);
        }
    }



    public function getTodaySpeciality()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        try {
            $builder = $this->db->table('tbl_day_speciality');
            $builder->where('is_active', 'Y');

            // Set Kolkata/Asia timezone and get current date
            date_default_timezone_set('Asia/Kolkata');
            $currentDate = date('Y-m-d');

            $builder->where('show_on_date', $currentDate);
            $data = $builder->get()->getRow();

            if ($data) {
                return $this->respond([
                    'status' => true,
                    'data' => $data,
                    'current_date' => $currentDate,
                    'timezone' => 'Asia/Kolkata'
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No speciality found for today.',
                    'current_date' => $currentDate,
                    'timezone' => 'Asia/Kolkata'
                ], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Unexpected error: ' . $e->getMessage()], 500);
        }
    }
}
