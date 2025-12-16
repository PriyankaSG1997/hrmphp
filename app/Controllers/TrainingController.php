<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use Exception;

class TrainingController extends ResourceController
{
    use ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = Database::connect();
    }

    public function addTraining()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $payload = validatejwt($headers);
        if (!$payload) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $createdBy = $payload->user_id ?? null;
        if (empty($createdBy)) {
            return $this->respond(['status' => false, 'message' => 'User ID not found in token payload.'], 401);
        }
        
        $requestData = $this->request->getJSON(true);
        $required = ['from_datetime', 'to_datetime', 'description', 'venue', 'branch_code', 'conducted_by'];
        foreach ($required as $field) {
            if (empty($requestData[$field])) {
                return $this->respond(['status' => false, 'message' => "$field is required"], 400);
            }
        }

        $this->db->transStart();
        try {
            $trainingData = [
                'from_datetime' => $requestData['from_datetime'],
                'to_datetime' => $requestData['to_datetime'],
                'description' => $requestData['description'],
                'venue' => $requestData['venue'],
                'branch_code' => $requestData['branch_code'],
                'conducted_by' => $requestData['conducted_by'],
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            
            $this->db->table('tbl_training')->insert($trainingData);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->respond(['status' => false, 'message' => 'Transaction failed. Please try again.'], 500);
            }

            return $this->respond(['status' => true, 'message' => 'Training added successfully.'], 200);

        } catch (Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
public function getOngoingTraining()
{
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');
    $payload = validatejwt($headers);
    if (!$payload) {
        return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
    }

    try {
        // ✅ Current datetime in Asia/Kolkata timezone
        $today = \CodeIgniter\I18n\Time::now('Asia/Kolkata', 'en_US')->toDateTimeString();
        $threeDaysLater = \CodeIgniter\I18n\Time::now('Asia/Kolkata', 'en_US')->addDays(3)->toDateTimeString();

        $builder = $this->db->table('tbl_training AS tt');
        $builder->select('
            tt.*,
            tbm.branch_name,
            tl.user_name AS created_by_name
        ');
        $builder->join('tbl_branch_mst AS tbm', 'tt.branch_code = tbm.branch_code', 'left');
        $builder->join('tbl_login AS tl', 'tt.created_by = tl.user_code_ref', 'left');

        // ✅ Condition: ongoing OR starting in next 3 days
        $builder->groupStart()
            ->where('tt.from_datetime <=', $today)
            ->where('tt.to_datetime >=', $today)
        ->groupEnd();
        $builder->orGroupStart()
            ->where('tt.from_datetime >=', $today)
            ->where('tt.from_datetime <=', $threeDaysLater)
        ->groupEnd();

        $builder->orderBy('tt.from_datetime', 'ASC');

        $query = $builder->get();
        $trainings = $query->getResult();

        return $this->respond([
            'status'  => true,
            'message' => 'Ongoing & upcoming training data retrieved successfully.',
            'count'   => count($trainings),
            'data'    => $trainings,
            'debug'   => [
                'today' => $today,
                'three_days_later' => $threeDaysLater
            ]
        ], 200);

    } catch (Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => 'An error occurred while retrieving training data: ' . $e->getMessage()
        ], 500);
    }
}

    public function updateTraining()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $payload = validatejwt($headers);
        if (!$payload) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $updatedBy = $payload->user_id ?? null;
        if (empty($updatedBy)) {
            return $this->respond(['status' => false, 'message' => 'User ID not found in token payload.'], 401);
        }
        
        $requestData = $this->request->getJSON(true);
        $id = $requestData['id'] ?? null;
        if (empty($id)) {
            return $this->respond(['status' => false, 'message' => 'id is required to update a record.'], 400);
        }

        $this->db->transStart();
        try {
            $record = $this->db->table('tbl_training')->where('id', $id)->get()->getRow();
            if (!$record) {
                return $this->respond(['status' => false, 'message' => 'Training record not found.'], 404);
            }

            $updateData = [];
            if (isset($requestData['from_datetime'])) $updateData['from_datetime'] = $requestData['from_datetime'];
            if (isset($requestData['to_datetime'])) $updateData['to_datetime'] = $requestData['to_datetime'];
            if (isset($requestData['description'])) $updateData['description'] = $requestData['description'];
            if (isset($requestData['venue'])) $updateData['venue'] = $requestData['venue'];
            if (isset($requestData['branch_code'])) $updateData['branch_code'] = $requestData['branch_code'];
            if (isset($requestData['conducted_by'])) $updateData['conducted_by'] = $requestData['conducted_by'];
            
            $updateData['updated_by'] = $updatedBy;
            $updateData['updated_at'] = date('Y-m-d H:i:s');

            if (empty($updateData)) {
                return $this->respond(['status' => false, 'message' => 'No data provided for update.'], 400);
            }

            $this->db->table('tbl_training')->where('id', $id)->update($updateData);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->respond(['status' => false, 'message' => 'Transaction failed. Please try again.'], 500);
            }

            return $this->respond(['status' => true, 'message' => 'Training updated successfully.'], 200);

        } catch (Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getAllTraining()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $payload = validatejwt($headers);
        if (!$payload) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        try {
            $builder = $this->db->table('tbl_training AS tt');
            $builder->select('
                tt.*,
                tbm.branch_name,
                tl.user_name AS created_by_name
            ');
            $builder->join('tbl_branch_mst AS tbm', 'tt.branch_code = tbm.branch_code', 'left');
            $builder->join('tbl_login AS tl', 'tt.created_by = tl.user_code_ref', 'left');
            
            $query = $builder->get();
            $trainings = $query->getResult();

            if ($trainings) {
                return $this->respond(['status' => true, 'message' => 'Training data retrieved successfully.', 'data' => $trainings], 200);
            } else {
                return $this->respond(['status' => true, 'message' => 'No training data found.', 'data' => []], 200);
            }

        } catch (Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'An error occurred while retrieving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getInternById()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $payload = validatejwt($headers);
        if (!$payload) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $requestData = $this->request->getJSON(true);
        $userCode = $requestData['user_code'] ?? null;
        if (empty($userCode)) {
            return $this->respond(['status' => false, 'message' => 'user_code is required.'], 400);
        }

        try {
            $builder = $this->db->table('tbl_register AS tr');
            $builder->select('
                tr.First_Name,
                tr.Last_Name,
                tr.Email,
                tr.Phone_no,
                tr.company_Code,
                tr.user_code,
                tr.joining_date,
                CONCAT(tr.First_Name, " ", tr.Last_Name) AS full_name,
                tdm.Designation_Name
            ');
            $builder->join('tbl_designation_mst AS tdm', 'tr.Designations = tdm.designation_code', 'left');
            $builder->where('tr.user_code', $userCode);

            $intern = $builder->get()->getRow();

            if ($intern) {
                return $this->respond(['status' => true, 'message' => 'Intern data retrieved successfully.', 'data' => $intern], 200);
            } else {
                return $this->respond(['status' => true, 'message' => 'No intern found with this user_code.', 'data' => []], 404);
            }

        } catch (Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'An error occurred while retrieving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllInterns()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $payload = validatejwt($headers);
        if (!$payload) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        try {
            $builder = $this->db->table('tbl_register AS tr');
            $builder->select('
                tr.First_Name,
                tr.Last_Name,
                tr.Email,
                tr.Phone_no,
                tr.company_Code,
                tr.user_code,
                tr.joining_date,
                CONCAT(tr.First_Name, " ", tr.Last_Name) AS full_name,
                tdm.Designation_Name
            ');
            $builder->join('tbl_designation_mst AS tdm', 'tr.Designations = tdm.designation_code', 'left');
            $builder->where('tr.Designations', 'DESGCPL028'); // Assuming 'DESGCPL028' is the designation code for interns.

            $interns = $builder->get()->getResult();

            if ($interns) {
                return $this->respond(['status' => true, 'message' => 'Intern data retrieved successfully.', 'data' => $interns], 200);
            } else {
                return $this->respond(['status' => true, 'message' => 'No interns found with designation DESGCPL028.', 'data' => []], 200);
            }

        } catch (Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'An error occurred while retrieving data: ' . $e->getMessage()
            ], 500);
        }
    }

public function addInternshipLetter()
{
    helper(['jwtvalidate_helper']);

    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    // Validate JWT
    $decoded = validatejwt($headers);
    if (!$decoded) {
        return $this->respond([
            'status' => false,
            'message' => 'Unauthorized or Invalid Token'
        ], 401);
    }

    $requestData = $this->request->getJSON(true);
    
    // Define all required fields (updated for new structure)
    $requiredFields = [
        'employee_name', 
        'employee_gender', 
        'project_title', 
        'company_code', 
        'software_used', 
        'platform_used', 
        'training_start_date', 
        'training_end_date',
        'signatory1_code',
        'signatory2_code'
    ];

    // Check if all required fields are present and not empty
    foreach ($requiredFields as $field) {
        if (!isset($requestData[$field]) || empty($requestData[$field])) {
            return $this->respond([
                'status' => false,
                'message' => "The field '{$field}' is required."
            ], 400);
        }
    }

    // Validate dates
    $startDate = strtotime($requestData['training_start_date']);
    $endDate = strtotime($requestData['training_end_date']);
    
    if ($endDate <= $startDate) {
        return $this->respond([
            'status' => false,
            'message' => "End date must be after start date."
        ], 400);
    }

    // Get company details for certificate number generation
    $company = $this->db->table('tbl_company')
        ->select('company_name') // Changed from company_short_name to company_name
        ->where('company_code', $requestData['company_code'])
        ->get()
        ->getRow();
    
    if (!$company) {
        return $this->respond([
            'status' => false,
            'message' => "Invalid company code."
        ], 400);
    }

    // Get signatory names from signature table
    $signatory1 = $this->db->table('tbl_signatureandstamp')
        ->select('name')
        ->where('signatureandstamp_code ', $requestData['signatory1_code'])
        ->get()
        ->getRow();
    
    $signatory2 = $this->db->table('tbl_signatureandstamp')
        ->select('name')
        ->where('signatureandstamp_code ', $requestData['signatory2_code'])
        ->get()
        ->getRow();
    
    if (!$signatory1 || !$signatory2) {
        return $this->respond([
            'status' => false,
            'message' => "Invalid signatory code."
        ], 400);
    }

    // Generate certificate number
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    $yearRange = $currentYear . '-' . substr($nextYear, -2);
    
    // Get count of internship certificates for this company in current year
    $countQuery = $this->db->table('tbl_internship_letters')
        ->where('company_code', $requestData['company_code'])
        ->where('YEAR(created_at)', $currentYear)
        ->countAllResults();
    
    $certificateCount = $countQuery + 1;
    
    // Create short name from company name (take first 3-5 characters)
    $companyShort = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $company->company_name), 0, 3));
    if (empty($companyShort)) {
        // Fallback to company code if company name doesn't have letters
        $companyShort = strtoupper(substr($requestData['company_code'], 0, 3));
    }
    
    $certificateNumber = sprintf(
        "%s/INTSP/%s/%02d",
        $companyShort,
        $yearRange,
        $certificateCount
    );

    // Prepare the data for insertion
    $data = [
        'employee_name' => $requestData['employee_name'],
        'employee_gender' => $requestData['employee_gender'],
        'project_title' => $requestData['project_title'],
        'company_code' => $requestData['company_code'],
        'software_used' => $requestData['software_used'],
        'platform_used' => $requestData['platform_used'],
        'training_start_date' => $requestData['training_start_date'],
        'training_end_date' => $requestData['training_end_date'],
        'certificate_no' => $certificateNumber,
        'signatory1_code' => $requestData['signatory1_code'],
        'signatory1_name' => $signatory1->name,
        'signatory2_code' => $requestData['signatory2_code'],
        'signatory2_name' => $signatory2->name,
        'is_active' => 'Y',
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $decoded->user_id ?? 0
    ];
    
    try {
        // Insert the data into the tbl_internship_letters table
        $this->db->table('tbl_internship_letters')->insert($data);
        
        // Get the last inserted ID
        $lastInsertId = $this->db->insertID();

        return $this->respond([
            'status' => true,
            'message' => 'Internship certificate added successfully.',
            'id' => $lastInsertId,
            'certificate_no' => $certificateNumber
        ], 200);

    } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        log_message('error', 'DatabaseException in addInternshipLetter: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        log_message('error', 'General Exception in addInternshipLetter: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ], 500);
    }
}
public function getAllInternshipLetters()
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
    try {
        $db = \Config\Database::connect();
        $letters = $db->table('tbl_internship_letters AS til')
            ->select('
                til.*, 
                tc.company_name,
                ss1.name as signatory1_name,
                ss2.name as signatory2_name,
                ss1.signature_img AS signatory1_signature,
                ss1.stamp_img AS signatory1_stamp,
                ss2.signature_img AS signatory2_signature,
                ss2.stamp_img AS signatory2_stamp

            ')  
            ->join('tbl_company AS tc', 'til.company_code = tc.company_code', 'left')
            ->join('tbl_signatureandstamp AS ss1', 'til.signatory1_code = ss1.signatureandstamp_code ', 'left')
            ->join('tbl_signatureandstamp AS ss2', 'til.signatory2_code = ss2.signatureandstamp_code ', 'left')
            ->where('til.is_active', 'Y')
            ->orderBy('til.created_at', 'DESC')
            ->get()
            ->getResult();

        if (!empty($letters)) {
            return $this->respond([
                'status' => true,
                'message' => 'Internship certificates fetched successfully.',
                'data' => $letters
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No internship certificates found.'
            ], 404);
        }
    } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        log_message('error', 'DatabaseException in getAllInternshipLetters: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        log_message('error', 'General Exception in getAllInternshipLetters: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ], 500);
    }
}


public function getInternshipLetterById()
{
    helper(['jwtvalidate_helper']);

    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    // ✅ Validate JWT
    $decoded = validatejwt($headers);
    if (!$decoded) {
        return $this->respond([
            'status' => false,
            'message' => 'Unauthorized or Invalid Token'
        ], 401);
    }

    $requestData = $this->request->getJSON(true);
    $id = $requestData['id'] ?? null;

    // ✅ Check if ID is provided
    if ($id === null) {
        return $this->respond([
            'status' => false,
            'message' => 'Internship certificate ID is required in the request body.'
        ], 400);
    }

    try {
        $db = \Config\Database::connect();

        // ✅ Fetch certificate with all related data
        $letter = $db->table('tbl_internship_letters AS til')
            ->select('
                til.*, 
                tc.company_name, 
                tc.logo AS company_logo, 
                tc.address AS company_address, 
                tc.email AS company_email,
                ss1.signature_img AS signatory1_signature,
                ss1.stamp_img AS signatory1_stamp,
                ss1.name AS signatory1_name,
                ss2.signature_img AS signatory2_signature,
                ss2.stamp_img AS signatory2_stamp,
                ss2.name AS signatory2_name,
            ')
            ->join('tbl_company AS tc', 'til.company_code = tc.company_code', 'left')
            ->join('tbl_signatureandstamp AS ss1', 'til.signatory1_code = ss1.signatureandstamp_code', 'left')
            ->join('tbl_signatureandstamp AS ss2', 'til.signatory2_code = ss2.signatureandstamp_code', 'left')
            ->where('til.id', $id)
            ->get()
            ->getRow();

        if ($letter) {
            // ✅ Convert logo to full URL if exists
            if (!empty($letter->company_logo)) {
                $letter->company_logo = base_url('companylogo/' . $letter->company_logo);
            }

            // ✅ Convert signatures to full URLs if exist
            if (!empty($letter->signatory1_signature)) {
                $letter->signatory1_signature = base_url('uploads/signatures/' . $letter->signatory1_signature);
            }

            if (!empty($letter->signatory2_signature)) {
                $letter->signatory2_signature = base_url('uploads/signatures/' . $letter->signatory2_signature);
            }

            // ✅ Convert stamps to full URLs if exist
            if (!empty($letter->signatory1_stamp)) {
                $letter->signatory1_stamp = base_url('uploads/stamps/' . $letter->signatory1_stamp);
            }

            if (!empty($letter->signatory2_stamp)) {
                $letter->signatory2_stamp = base_url('uploads/stamps/' . $letter->signatory2_stamp);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Internship certificate fetched successfully.',
                'data' => $letter
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No internship certificate found with the provided ID.'
            ], 404);
        }
    } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        log_message('error', 'DatabaseException in getInternshipLetterById: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        log_message('error', 'General Exception in getInternshipLetterById: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ], 500);
    }
}

public function updateInternshipLetter()
{
    helper(['jwtvalidate_helper']);

    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    // Validate JWT
    $decoded = validatejwt($headers);
    if (!$decoded) {
        return $this->respond([
            'status' => false,
            'message' => 'Unauthorized or Invalid Token'
        ], 401);
    }

    $requestData = $this->request->getJSON(true);
    $id = $requestData['id'] ?? null;
    
    if (!$id) {
        return $this->respond([
            'status' => false,
            'message' => 'ID is required for updating.'
        ], 400);
    }

    // Check if record exists
    $existing = $this->db->table('tbl_internship_letters')
        ->where('id', $id)
        ->where('is_active', 'Y')
        ->get()
        ->getRow();
    
    if (!$existing) {
        return $this->respond([
            'status' => false,
            'message' => 'Internship certificate not found.'
        ], 404);
    }

    // Prepare update data
    $updateData = [];
    
    // List of fields that can be updated
    $updatableFields = [
        'employee_name', 
        'employee_gender', 
        'project_title', 
        'software_used', 
        'platform_used', 
        'training_start_date', 
        'training_end_date',
        'signatory1_code',
        'signatory2_code'
    ];

    foreach ($updatableFields as $field) {
        if (isset($requestData[$field])) {
            $updateData[$field] = $requestData[$field];
        }
    }

    // Validate dates if both are being updated
    if (isset($updateData['training_start_date']) && isset($updateData['training_end_date'])) {
        $startDate = strtotime($updateData['training_start_date']);
        $endDate = strtotime($updateData['training_end_date']);
        
        if ($endDate <= $startDate) {
            return $this->respond([
                'status' => false,
                'message' => "End date must be after start date."
            ], 400);
        }
    }

    // Update signatory names if codes changed
    if (isset($updateData['signatory1_code'])) {
        $signatory1 = $this->db->table('tbl_signatureandstamp')
            ->select('name')
            ->where('signatureandstamp_code ', $updateData['signatory1_code'])
            ->get()
            ->getRow();
        
        if ($signatory1) {
            $updateData['signatory1_name'] = $signatory1->name;
        }
    }

    if (isset($updateData['signatory2_code'])) {
        $signatory2 = $this->db->table('tbl_signatureandstamp')
            ->select('name')
            ->where('signatureandstamp_code ', $updateData['signatory2_code'])
            ->get()
            ->getRow();
        
        if ($signatory2) {
            $updateData['signatory2_name'] = $signatory2->name;
        }
    }

    $updateData['updated_at'] = date('Y-m-d H:i:s');
    $updateData['updated_by'] = $decoded->user_id ?? 0;

    try {
        $this->db->table('tbl_internship_letters')
            ->where('id', $id)
            ->update($updateData);

        return $this->respond([
            'status' => true,
            'message' => 'Internship certificate updated successfully.'
        ], 200);

    } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        log_message('error', 'DatabaseException in updateInternshipLetter: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        log_message('error', 'General Exception in updateInternshipLetter: ' . $e->getMessage());
        return $this->respond([
            'status' => false,
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ], 500);
    }
}

}
