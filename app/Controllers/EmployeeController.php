<?php

namespace App\Controllers;

use DateTime;
use DateInterval;
use DatePeriod;
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

class EmployeeController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = Database::connect();
    }

    public function registerEmployee()
    {
        $request = $this->request->getJSON(true);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        // print_r($request);die;
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? null;

        if (!$createdBy) {
            return $this->respond(['status' => false, 'message' => 'Invalid user ID in token'], 400);
        }

        $db = \Config\Database::connect();

        // Generate next user_code
        $lastRow = $db->table('tbl_register')
            ->select('user_code')
            ->like('user_code', 'SKDCPL')
            ->orderBy('user_code', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        $nextNumber = 1;
        if ($lastRow && preg_match('/SKDCPL(\d+)/', $lastRow->user_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $newUserCode = 'SKDCPL' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Required fields validation
        $required = ['First_Name', 'Last_Name', 'Email', 'Phone_no', 'company_Code', 'password'];
        foreach ($required as $field) {
            if (empty($request[$field])) {
                return $this->respond(['status' => false, 'message' => "$field is required"], 400);
            }
        }

        $db->transStart();
        try {
            // Insert into tbl_register
            $registerData = [
                'First_Name' => $request['First_Name'],
                'Last_Name' => $request['Last_Name'],
                'Email' => $request['Email'],
                'Phone_no' => $request['Phone_no'],
                'company_Code' => $request['company_Code'],
                'branch_code' => $request['branch_code'] ?? null,
                'department_code' => $request['department_code'] ?? null,
                'Designations' => $request['Designations'] ?? null,
                'joining_date' => $request['joining_date'] ?? null,
                'Middle' => $request['Middle'] ?? null,
                // 'TL_Status' => $request['TL_Status'] ?? null,
                'user_code' => $newUserCode,
                'role_ref_code' => $request['role_ref_code'] ?? null,
                'team_lead_ref_code' => $request['team_lead_ref_code'] ?? null,
                'hod_ref_code' => $request['hod_ref_code'] ?? null,
                'Note' => $request['Note'] ?? null,
                'created_by' => $createdBy
            ];
            $db->table('tbl_register')->insert($registerData);

            // Insert into tbl_login
            $loginData = [
                'user_name' => trim($request['First_Name'] . ' ' . $request['Last_Name']),
                'email' => $request['Email'],
                'password' => password_hash($request['password'], PASSWORD_BCRYPT),
                'user_code_ref' => $newUserCode,
                'role_ref_code' => $request['role_ref_code'] ?? null,
                'is_verified' => 'Y',
                'designations_code' => $request['Designations'] ?? null,
                'is_active' => 'Y',
                'created_by' => $createdBy
            ];
            $db->table('tbl_login')->insert($loginData);

            // Determine access levels
            $accesslevelData = "";
            if ($request['role_ref_code'] == 'EMP_htv') {
                $accesslevelData = "H9KAW,KPVa6,bU5Y7,025ah,YjCHN,6oxyS,Cp3nm,b90GW,MEXrl,2gUm7,1DkHq,PNJWt,uvSOZ";
            }
            if ($request['role_ref_code'] == 'ADM_3w7') {
                $accesslevelData = "FpfDG,H9KAW,Bkdv4,2Tm0a,KPVa6,k45PS,bU5Y7,xT9RX,NZXC9,uN0bD,mUQev,hySYA,BXARb,uCiHD,eQdRZ,bSDsm,PSba2,flgZW,6oxyS,7qjAU,PV4RQ,MtePJ,3iMFx,8fg9o,LBH1I,b90GW,7YPbI,MEXrl,cDjfg,2gUm7,oBgyO,C6Sal,i90yD,8bWdS,uVQwg,PNJWt,0Nmjr,zgqRE,MPBTr,n0NS9,2QChI,A3dGh,RaFqH,Mx7Sk,a143d,iL6uO";
            }
            if ($request['role_ref_code'] == 'HRM_4w7') {
                $accesslevelData = "FpfDG,H9KAW,Bkdv4,2Tm0a,KPVa6,k45PS,bU5Y7,xT9RX,NZXC9,uN0bD,mUQev,hySYA,025ah,BXARb,uCiHD,bSDsm,PSba2,VSq2A,6oxyS,Cp3nm,7qjAU,PV4RQ,MtePJ,3iMFx,8fg9o,b90GW,MEXrl,cDjfg,2gUm7,1DkHq,oBgyO,C6Sal,i90yD,PNJWt,zgqRE,MPBTr,n0NS9,O62CH,O81I4,RaFqH,uvSOZ";
            }
            if ($request['Designations'] == 'DESGCPL003') {
                $accesslevelData = "bU5Y7,xT9RX,025ah,uCiHD,bSDsm,PSba2,Cp3nm,7qjAU,b90GW,MEXrl,cDjfg,PNJWt,MPBTr,O62CH,O81I4,8OTjs,A3dGh,83Bkx,mxpdz,uvSOZ";
            }
            if ($request['Designations'] == 'DESGCPL004') {
                $accesslevelData = "KPVa6,k45PS,bU5Y7,025ah,BXARb,uCiHD,YjCHN,6oxyS,Cp3nm,MEXrl,2gUm7,1DkHq,PNJWt,O62CH,O81I4,8OTjs,A3dGh,83Bkx,f7BCP,mxpdz,uvSOZ";
            }
            if ($request['department_code'] == 'DEPTM003' || $request['Designations'] == 'DESGCPL021') {
                $accesslevelData = "025ah,uCiHD,eQdRZ,6oxyS,b90GW,MEXrl,PNJWt,m9i6A,O62CH,O81I4,8OTjs,83Bkx,mxpdz,uvSOZ";
            }
            if ($request['department_code'] == 'DEPTM002') {
                $accesslevelData = "83Bkx,H9KAW,KPVa6,bU5Y7,025ah,YjCHN,6oxyS,Cp3nm,b90GW,MEXrl,2gUm7,1DkHq,PNJWt,uvSOZ";
            }

            // Insert access levels
            $accesslevel = [
                'user_ref_code' => $newUserCode,
                'url_code' => $accesslevelData,
                'created_by' => $createdBy,
            ];
            // print_r($accesslevel);die;
            $db->table('tbl_access_level_by_user')->insert($accesslevel);
            if ($db->affectedRows() <= 0) {
                return $this->respond([
                    'status' => false,
                    'message' => 'tbl_access_level_by_user insert failed',
                    'error' => $db->error(),
                    'query' => (string) $db->getLastQuery()
                ], 500);
            }
            $db->transComplete();

            if ($db->transStatus() === false) {
                $error = $db->error();
                $msg = isset($error['message']) && $error['message'] ? $error['message'] : 'Unknown DB error';
                return $this->respond(['status' => false, 'message' => 'Registration failed due to database error: ' . $msg], 500);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Employee registered successfully',
                'user_code_ref' => $newUserCode
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Exception during registration: ' . $e->getMessage()], 500);
        }
    }



    public function add()
    {
        helper(['form', 'url', 'jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }
        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }
        $userCodeRef = $this->request->getPost('user_code_ref');
        if (empty($userCodeRef)) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required for file uploads.'], 400);
        }
        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_code_ref' => 'required',
            'aadhar_number' => 'permit_empty|numeric|exact_length[12]',
            'pan_card_no' => 'permit_empty|alpha_numeric|exact_length[10]',

        ]);
        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }
        $baseUploadDir = ROOTPATH . 'public/uploads/employees/' . $userCodeRef . '/';
        $certUploadDir = $baseUploadDir . 'certificates/';
        $fileFields = [
            'adhar_card_file' => 'adhar/',
            'pan_card_file' => 'pan/',
            'photo_file' => 'photo/',
            'resume_file' => 'resume/'
        ];
        if (!is_dir($certUploadDir)) {
            mkdir($certUploadDir, 0777, true);
        }
        foreach ($fileFields as $sub) {
            $dir = $baseUploadDir . $sub;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        $uploaded = [];
        foreach ($fileFields as $fileField => $subDir) {
            $file = $this->request->getFile($fileField);
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $newName = $file->getRandomName();
                $file->move($baseUploadDir . $subDir, $newName);
                $uploaded[$fileField] = 'public/uploads/employees/' . $userCodeRef . '/' . $subDir . $newName;
            } else {
                $uploaded[$fileField] = null;
            }
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_employee_details');
        $insertData = [
            'user_code_ref' => $userCodeRef,
            'date_of_birth' => $this->request->getPost('date_of_birth') ?? null,
            'Gender' => $this->request->getPost('Gender') ?? null,
            'aadhar_number' => $this->request->getPost('aadhar_number') ?? null,
            'pan_card_no' => $this->request->getPost('pan_card_no') ?? null,
            'blood_group' => $this->request->getPost('blood_group') ?? null,
            'contact_information' => $this->request->getPost('contact_information') ?? null,
            'current_address' => $this->request->getPost('current_address') ?? null,
            'permanent_address' => $this->request->getPost('permanent_address') ?? null,
            'phone_number' => $this->request->getPost('phone_number') ?? null,
            'whatsapp_no' => $this->request->getPost('whatsapp_no') ?? null,
            'marital_status' => $this->request->getPost('marital_status') ?? null,
            'personal_email' => $this->request->getPost('personal_email') ?? null,
            'reporting_to' => $this->request->getPost('reporting_to') ?? null,
            // education & previous organization fields
            'graduation' => $this->request->getPost('graduation') ?? null,
            'graduationdegree' => $this->request->getPost('graduationdegree') ?? null,
            'graduationunivercity' => $this->request->getPost('graduationunivercity') ?? null,
            'graduationspecialization' => $this->request->getPost('graduationspecialization') ?? null,
            'graduation_year_of_completion' => $this->request->getPost('graduation_year_of_completion') ?? null,
            'post_graduation' => $this->request->getPost('post_graduation') ?? null,
            'post_univercity' => $this->request->getPost('post_univercity') ?? null,
            'postgraduationspecialization' => $this->request->getPost('postgraduationspecialization') ?? null,
            'post_graduation_year_of_completion' => $this->request->getPost('post_graduation_year_of_completion') ?? null,
            'previous_organization_name' => $this->request->getPost('previous_organization_name') ?? null,
            'previous_organization_designation' => $this->request->getPost('previous_organization_designation') ?? null,
            'reason_of_leaving' => $this->request->getPost('reason_of_leaving') ?? null,
            'previous_organization_start_year' => $this->request->getPost('previous_organization_start_year') ?? null,
            'previous_organization_end_year' => $this->request->getPost('previous_organization_end_year') ?? null,
            // 'other_certificates' => $this->request->getPost('other_certificates') ?? null, // optional text if you send names
            'emergency_contact_number' => $this->request->getPost('emergency_contact_number') ?? null,
            'emergency_contact_person_name' => $this->request->getPost('emergency_contact_person_name') ?? null,
            'emergency_contact_person_relation' => $this->request->getPost('emergency_contact_person_relation') ?? null,
            'adhar_card_file' => $uploaded['adhar_card_file'],
            'pan_card_file' => $uploaded['pan_card_file'],
            'photo_file' => $uploaded['photo_file'],
            'resume_file' => $uploaded['resume_file'],
            'is_active' => 'Y',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $decoded->user_id ?? 'system',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $decoded->user_id ?? 'system',
            'whatsapp_permission' => $this->request->getPost('whatsapp_permission')
        ];
        $db->transStart();
        try {
            $inserted = $builder->insert($insertData);
            if (!$inserted) {
                $err = $db->error();
                $db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to save employee details: ' . ($err['message'] ?? 'Unknown DB error')], 500);
            }
            $certFiles = $this->request->getFiles();
            if (!empty($certFiles) && isset($certFiles['other_certificates'])) {
                $certArray = $certFiles['other_certificates'];
                $certBuilder = $db->table('tbl_other_certificates_employee');
                foreach ($certArray as $certFile) {
                    if ($certFile && $certFile->isValid() && !$certFile->hasMoved()) {
                        $newName = $certFile->getRandomName();
                        $certFile->move($certUploadDir, $newName);
                        $certInsert = [
                            'user_code_ref' => $userCodeRef,
                            'file_name' => $certFile->getClientName(), // original name
                            'file_path' => 'public/uploads/employees/' . $userCodeRef . '/certificates/' . $newName,
                            'created_at' => date('Y-m-d H:i:s'),
                            'created_by' => $decoded->user_id ?? 'system'
                        ];
                        $certBuilder->insert($certInsert);
                    }
                }
            }
            $db->transComplete();

            if ($db->transStatus() === false) {
                $err = $db->error();
                return $this->respond(['status' => false, 'message' => 'Registration failed due to database error: ' . ($err['message'] ?? 'Unknown DB error')], 500);
            }
            return $this->respond(['status' => true, 'message' => 'Employee details saved successfully.']);
        } catch (DatabaseException $e) {
            $db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }




    public function addInsuranceDetails()
    {
        helper('jwtvalidate_helper');

        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $createdBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true) ?? [];

        // ✅ Validate only insurance fields
        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_code_ref' => 'required|max_length[50]',
            'insurance_id_no' => 'required|max_length[100]',
            'insurance_amt' => 'required|decimal'
        ]);

        if (!$validation->run($request)) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        // ✅ Generate next insurance code
        $lastInsurance = $this->db->table('tbl_insurance_details')
            ->select('insurance_code')
            ->like('insurance_code', 'INSCPL')
            ->orderBy('insurance_code', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($lastInsurance && preg_match('/INSCPL(\d+)/', $lastInsurance->insurance_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $newInsuranceCode = 'INSCPL' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // ✅ Insert only required fields
        $data = [
            'user_code_ref' => $request['user_code_ref'],
            'insurance_code' => $newInsuranceCode,
            'insurance_id_no' => $request['insurance_id_no'],
            'insurance_amt' => $request['insurance_amt'],
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ];

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $builder = $db->table('tbl_insurance_details');
            if ($builder->insert($data)) {
                $db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Insurance details saved successfully.'
                ]);
            } else {
                $db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to save insurance details'], 500);
            }
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    public function addBankDetails()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $createdBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true);

        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_code_ref' => 'required|max_length[50]',
            'account_holder_name' => 'required|max_length[150]',
            'bank_name' => 'required|max_length[150]',
            'account_number' => 'required|numeric|max_length[30]',
            'account_type' => 'permit_empty|max_length[50]',
            'branch_name' => 'permit_empty|max_length[150]',
            // 'IFSC_code' => 'required|alpha_numeric|max_length[20]',
            // 'upi_id' => 'permit_empty|alpha_numeric|max_length[50]',
            'IFSC_code' => 'required|regex_match[/^[A-Z]{4}0[A-Z0-9]{6}$/]|max_length[11]',
            'upi_id' => 'permit_empty|regex_match[/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9]+$/]|max_length[50]',
            'upi_mobile_no' => 'permit_empty|numeric|exact_length[10]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $insertData = [
            'user_code_ref' => $request['user_code_ref'],
            'account_holder_name' => $request['account_holder_name'],
            'bank_name' => $request['bank_name'],
            'account_number' => $request['account_number'],
            'account_type' => $request['account_type'] ?? null,
            'branch_name' => $request['branch_name'] ?? null,
            'IFSC_code' => $request['IFSC_code'],
            'upi_id' => $request['upi_id'] ?? null,
            'upi_mobile_no' => $request['upi_mobile_no'] ?? null,
            'is_active' => 'Y',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $createdBy,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $createdBy,
        ];

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_bank_details');
            if ($builder->insert($insertData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Bank details saved successfully.',
                    'user_code_ref' => $request['user_code_ref']
                ]);
            } else {
                $this->db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to save bank details to database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    // public function getAllEmployeeDetails()
    // {
    //     helper(['jwtvalidate_helper', 'url']);
    //     $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    //     if (!$headers) {
    //         return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
    //     }

    //     $decoded = validatejwt($headers);
    //     if (!$decoded) {
    //         return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
    //     }

    //     try {
    //         $employees = $this->db->table('tbl_employee_details AS ted')
    //             ->select('
    //                 ted.*, 
    //                 tr.First_Name, tr.Last_Name, tr.Email AS register_email, tr.Phone_no AS register_phone_no,
    //                 tr.company_Code, tr.role_ref_code, tr.team_lead_ref_code, tr.Note AS register_note,
    //                 tl.user_name, tl.email AS login_email, tl.is_verified, tl.is_active AS login_is_active,
    //                 tc.company_name, tc.address AS company_address, tc.email AS company_email,
    //                 tc.pf_deduction, tc.contact_number, tc.website, tc.gst_number, tc.gst,
    //                 tc.logo AS company_logo
    //             ')
    //             ->join('tbl_register AS tr', 'ted.user_code_ref = tr.user_code', 'left')
    //             ->join('tbl_login AS tl', 'ted.user_code_ref = tl.user_code_ref', 'left')
    //             ->join('tbl_company AS tc', 'tr.company_Code = tc.company_code', 'left')
    //             ->where('ted.is_active', 'Y')
    //             ->get()
    //             ->getResult();

    //         foreach ($employees as $employee) {
    //             if (!empty($employee->adhar_card_file)) {
    //                 $employee->adhar_card_file = base_url($employee->adhar_card_file);
    //             }
    //             if (!empty($employee->pan_card_file)) {
    //                 $employee->pan_card_file = base_url($employee->pan_card_file);
    //             }
    //             if (!empty($employee->photo_file)) {
    //                 $employee->photo_file = base_url($employee->photo_file);
    //             }
    //             if (!empty($employee->company_logo)) {
    //                 $employee->company_logo = base_url($employee->company_logo);
    //             }
    //         }

    //         return $this->respond(['status' => true, 'data' => $employees]);
    //     } catch (DatabaseException $e) {
    //         return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    //     } catch (\Exception $e) {
    //         return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    //     }
    // }
    public function getAllEmployeeDetails()
    {
        helper(['jwtvalidate_helper', 'url']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        try {
            $employees = $this->db->table('tbl_employee_details AS ted')
                ->select('
                ted.*, 
                tr.First_Name, tr.Last_Name, tr.Email AS register_email, tr.Phone_no AS register_phone_no,
                tr.company_Code, tr.role_ref_code, tr.team_lead_ref_code, tr.Note AS register_note,
                tl.user_name, tl.email AS login_email, tl.is_verified, tl.is_active AS login_is_active,
                tc.company_name, tc.address AS company_address, tc.email AS company_email,
                tc.pf_deduction, tc.contact_number, tc.website, tc.gst_number, tc.gst,
                tc.logo AS company_logo
            ')
                ->join('tbl_register AS tr', 'ted.user_code_ref = tr.user_code', 'left')
                ->join('tbl_login AS tl', 'ted.user_code_ref = tl.user_code_ref', 'left')
                ->join('tbl_company AS tc', 'tr.company_Code = tc.company_code', 'left')
                ->where('ted.is_active', 'Y')
                ->where('tl.is_active', 'Y')   // ✅ only active logins
                ->get()
                ->getResult();

            foreach ($employees as $employee) {
                if (!empty($employee->adhar_card_file)) {
                    $employee->adhar_card_file = base_url($employee->adhar_card_file);
                }
                if (!empty($employee->pan_card_file)) {
                    $employee->pan_card_file = base_url($employee->pan_card_file);
                }
                if (!empty($employee->photo_file)) {
                    $employee->photo_file = base_url($employee->photo_file);
                }
                if (!empty($employee->company_logo)) {
                    $employee->company_logo = base_url($employee->company_logo);
                }
            }

            return $this->respond(['status' => true, 'data' => $employees]);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function getAllEmployeeDetailsforreport()
    {
        helper(['jwtvalidate_helper', 'url']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        try {
            $employees = $this->db->table('tbl_employee_details AS ted')
                ->select('
                ted.*, 
                tr.First_Name, tr.Last_Name, tr.Email AS register_email, tr.Phone_no AS register_phone_no,
                tr.company_Code, tr.role_ref_code, tr.team_lead_ref_code, tr.Note AS register_note,
                tl.user_name, tl.email AS login_email, tl.is_verified, tl.is_active AS login_is_active,
                tc.company_name, tc.address AS company_address, tc.email AS company_email,
                tc.pf_deduction, tc.contact_number, tc.website, tc.gst_number, tc.gst,
                tc.logo AS company_logo,
                td.designation_name,
                tdept.department_name,   -- ✅ Added department name
                tdept.department_code    -- (optional) include department_code if needed
            ')
                ->join('tbl_register AS tr', 'ted.user_code_ref = tr.user_code', 'left')
                ->join('tbl_login AS tl', 'ted.user_code_ref = tl.user_code_ref', 'left')
                ->join('tbl_company AS tc', 'tr.company_Code = tc.company_code', 'left')
                ->join('tbl_designation_mst AS td', 'tr.Designations = td.designation_code', 'left')
                ->join('tbl_department AS tdept', 'tr.department_code = tdept.department_code', 'left')
                ->where('ted.is_active', 'Y')
                ->where('tl.is_active', 'Y')   // ✅ only active logins
                ->get()
                ->getResult();

            foreach ($employees as $employee) {
                if (!empty($employee->adhar_card_file)) {
                    $employee->adhar_card_file = base_url($employee->adhar_card_file);
                }
                if (!empty($employee->pan_card_file)) {
                    $employee->pan_card_file = base_url($employee->pan_card_file);
                }
                if (!empty($employee->photo_file)) {
                    $employee->photo_file = base_url($employee->photo_file);
                }
                if (!empty($employee->company_logo)) {
                    $employee->company_logo = base_url($employee->company_logo);
                }
            }

            return $this->respond(['status' => true, 'data' => $employees]);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function getEmployeeDetailsbyId()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request = $this->request->getJSON(true);
        $user_code_ref = $request['user_code_ref'] ?? null;

        if (empty($user_code_ref)) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required.'], 400);
        }

        try {
            // Fetch main employee details
            $employee = $this->db->table('tbl_employee_details AS ted')
                ->select('
                    ted.*, 
                    tr.First_Name, tr.Last_Name, tr.Email AS register_email, tr.Phone_no AS register_phone_no,
                    tr.company_Code, tr.role_ref_code, tr.team_lead_ref_code, tr.Note AS register_note,
                    tr.Designations AS designation_code, 
                    tdm.designation_name,
                    tl.user_name, tl.email AS login_email, tl.is_verified, tl.is_active AS login_is_active,
                    tc.company_name, tc.address AS company_address, tc.email AS company_email,
                    tc.pf_deduction, tc.contact_number, tc.website, tc.gst_number, tc.gst,
                    tc.logo AS company_logo
                ')
                ->join('tbl_register AS tr', 'ted.user_code_ref = tr.user_code', 'left')
                ->join('tbl_designation_mst AS tdm', 'tr.Designations = tdm.designation_code', 'left')
                ->join('tbl_login AS tl', 'ted.user_code_ref = tl.user_code_ref', 'left')
                ->join('tbl_company AS tc', 'tr.company_Code = tc.company_code', 'left')
                ->where('ted.user_code_ref', $user_code_ref)
                ->where('ted.is_active', 'Y')
                ->get()
                ->getRow();

            if ($employee) {

                $salaryDetails = $this->db->table('tbl_salary_details')
                    ->where('user_ref_code', $user_code_ref)
                    ->orderBy('created_at', 'DESC')
                    ->get(1)
                    ->getRow();

                $employee->salary_details = $salaryDetails ?? null;

                // File URLs
                if (!empty($employee->adhar_card_file)) {
                    $employee->adhar_card_file = base_url($employee->adhar_card_file);
                }
                if (!empty($employee->pan_card_file)) {
                    $employee->pan_card_file = base_url($employee->pan_card_file);
                }
                if (!empty($employee->photo_file)) {
                    $employee->photo_file = base_url($employee->photo_file);
                }
                if (!empty($employee->company_logo)) {
                    $employee->company_logo = base_url($employee->company_logo);
                }

                return $this->respond(['status' => true, 'data' => $employee]);
            } else {
                return $this->respond(['status' => false, 'message' => 'Employee not found or not active.'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    // public function getRegisterData()
    // {
    //     helper(['jwtvalidate_helper']);
    //     $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    //     if (!$headers) {
    //         return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
    //     }

    //     $decoded = validatejwt($headers);
    //     if (!$decoded) {
    //         return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
    //     }

    //     $request = $this->request->getJSON(true);
    //     $user_code = $request['user_code'] ?? null; // Use 'user_code' for tbl_register

    //     try {
    //         $builder = $this->db->table('tbl_register');
    //         $builder->where('is_active', 'Y'); // Assuming 'is_active' exists in tbl_register

    //         if ($user_code) {
    //             $data = $builder->where('user_code', $user_code)->get()->getRow();
    //             if ($data) {
    //                 return $this->respond(['status' => true, 'data' => $data]);
    //             } else {
    //                 return $this->respond(['status' => false, 'message' => 'Registration data not found.'], 404);
    //             }
    //         } else {
    //             $data = $builder->get()->getResult();
    //             return $this->respond(['status' => true, 'data' => $data]);
    //         }
    //     } catch (DatabaseException $e) {
    //         return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    //     } catch (\Exception $e) {
    //         return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    //     }
    // }

    public function getRegisterData()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }
        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }
        $request = $this->request->getJSON(true);
        $user_code = $request['user_code'] ?? null;
        $data = $this->request->getJSON(true);
        $page = $data['page'] ?? 1;
        $limit = $data['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        try {
            $builder = $this->db->table('tbl_register');
            $builder->orderBy('user_code', 'DESC');
            $builder->where('is_active', 'Y');

            if ($user_code) {
                $data = $builder->where('user_code', $user_code)->get()->getRow();
                if ($data) {
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Registration data not found.'], 404);
                }
            } else {
                $total = $builder->countAllResults(false);
                $builder->limit($limit, $offset);
                $query = $builder->get();
                $data = $query->getResult();

                return $this->respond([
                    'status' => true,
                    'message' => 'Data fetched successfully',
                    'data' => $data,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total_records' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getRegisterDatawp()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }
        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }
        $request = $this->request->getJSON(true);
        $user_code = $request['user_code'] ?? null;
        $data = $this->request->getJSON(true);
    
        try {
            $builder = $this->db->table('tbl_register');
            $builder->orderBy('user_code', 'DESC');
            $builder->where('is_active', 'Y');

            if ($user_code) {
                $data = $builder->where('user_code', $user_code)->get()->getRow();
                if ($data) {
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Registration data not found.'], 404);
                }
            } else {
                $total = $builder->countAllResults(false);
                $query = $builder->get();
                $data = $query->getResult();

                return $this->respond([
                    'status' => true,
                    'message' => 'Data fetched successfully',
                    'data' => $data,
               
                ]);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
    


    public function getInsuranceData()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request = $this->request->getJSON(true);
        $user_code_ref = $request['user_code_ref'] ?? null;

        try {
            $builder = $this->db->table('tbl_insurance_details');
            $builder->where('is_active', 'Y'); // Assuming 'is_active' exists in tbl_insurance_details

            if ($user_code_ref) {
                $data = $builder->where('user_code_ref', $user_code_ref)->get()->getResult(); // Can be multiple policies
                if (!empty($data)) {
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Insurance data not found for this user.'], 404);
                }
            } else {
                $data = $builder->get()->getResult();
                return $this->respond(['status' => true, 'data' => $data]);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }



    public function getBankData()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request = $this->request->getJSON(true);
        $user_code_ref = $request['user_code_ref'] ?? null;

        try {
            $builder = $this->db->table('tbl_bank_details');
            $builder->where('is_active', 'Y'); // Assuming 'is_active' exists in tbl_bank_details

            if ($user_code_ref) {
                $data = $builder->where('user_code_ref', $user_code_ref)->get()->getResult(); // Can be multiple bank accounts
                if (!empty($data)) {
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Bank data not found for this user.'], 404);
                }
            } else {
                $data = $builder->get()->getResult();
                return $this->respond(['status' => true, 'data' => $data]);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function getEmployeePersonalData()
    {
        helper(['jwtvalidate_helper', 'url']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request = $this->request->getJSON(true);
        $user_code_ref = $request['user_code_ref'] ?? null;

        try {
            $builder = $this->db->table('tbl_employee_details');
            $builder->where('is_active', 'Y');

            if ($user_code_ref) {
                $data = $builder->where('user_code_ref', $user_code_ref)->get()->getRow();
                if ($data) {
                    if (!empty($data->adhar_card_file)) {
                        $data->adhar_card_file = base_url($data->adhar_card_file);
                    }
                    if (!empty($data->pan_card_file)) {
                        $data->pan_card_file = base_url($data->pan_card_file);
                    }
                    if (!empty($data->photo_file)) {
                        $data->photo_file = base_url($data->photo_file);
                    }
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Employee personal data not found.'], 404);
                }
            } else {
                $data = $builder->get()->getResult();
                foreach ($data as $record) {
                    if (!empty($record->adhar_card_file)) {
                        $record->adhar_card_file = base_url($record->adhar_card_file);
                    }
                    if (!empty($record->pan_card_file)) {
                        $record->pan_card_file = base_url($record->pan_card_file);
                    }
                    if (!empty($record->photo_file)) {
                        $record->photo_file = base_url($record->photo_file);
                    }
                }
                return $this->respond(['status' => true, 'data' => $data]);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function addDesignations()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true);

        // Generate unique designation_code
        $lastDesignation = $this->db->table('tbl_designation_mst')
            ->select('designation_code')
            ->like('designation_code', 'DESGCPL')
            ->orderBy('designation_code', 'DESC')
            ->get(1)
            ->getRow();

        $nextNumber = 1;
        if ($lastDesignation && preg_match('/DESGCPL(\d+)/', $lastDesignation->designation_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $newDesignationCode = 'DESGCPL' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Validation Rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'designation_name' => 'required|max_length[100]|is_unique[tbl_designation_mst.designation_name]',
            'description' => 'permit_empty|max_length[255]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $insertData = [
            'designation_code' => $newDesignationCode,
            'designation_name' => $request['designation_name'],
            'description' => $request['description'] ?? null,
            'is_active' => 'Y',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $createdBy,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $createdBy,
        ];

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_designation_mst');
            if ($builder->insert($insertData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Designation added successfully.',
                    'designation_code' => $newDesignationCode
                ]);
            } else {
                $this->db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to add designation to database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function updateDesignations()
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
            'designation_code' => 'required|max_length[50]|is_not_unique[tbl_designation_mst.designation_code]', // Ensure code exists
            'designation_name' => 'permit_empty|max_length[100]|is_unique[tbl_designation_mst.designation_name,designation_code,{designation_code}]', // Unique, but ignore current record
            'description' => 'permit_empty|max_length[255]',
            'is_active' => 'permit_empty|in_list[Y,N]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $designationCode = $request['designation_code'];
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updatedBy,
        ];

        if (isset($request['designation_name'])) {
            $updateData['designation_name'] = $request['designation_name'];
        }
        if (isset($request['description'])) {
            $updateData['description'] = $request['description'];
        }
        if (isset($request['is_active'])) {
            $updateData['is_active'] = $request['is_active'];
        }

        // Ensure there's something to update besides timestamps
        if (count($updateData) <= 2) { // updated_at and updated_by are always there
            return $this->respond(['status' => false, 'message' => 'No fields provided for update.'], 400);
        }

        $this->db->transStart();
        try {
            $builder = $this->db->table('tbl_designation_mst');
            $builder->where('designation_code', $designationCode);

            if ($builder->update($updateData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Designation updated successfully.',
                    'designation_code' => $designationCode
                ]);
            } else {
                $this->db->transRollback();
                // Check if the record actually existed but no changes were made
                if ($this->db->affectedRows() === 0) {
                    return $this->respond(['status' => false, 'message' => 'Designation not found or no changes were made.'], 404);
                }
                return $this->respond(['status' => false, 'message' => 'Failed to update designation in database.'], 500);
            }
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function getAllDesignations()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request = $this->request->getJSON(true);
        $designation_code = $request['designation_code'] ?? null;

        try {
            $builder = $this->db->table('tbl_designation_mst');
            $builder->where('is_active', 'Y');

            if ($designation_code) {
                $data = $builder->where('designation_code', $designation_code)->get()->getRow();
                if ($data) {
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Designation not found.'], 404);
                }
            } else {
                $data = $builder->get()->getResult();
                return $this->respond(['status' => true, 'data' => $data]);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }



    public function allocateLeaves()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $loggedInUser = $decoded->user_id ?? 'system';

        $resultsSummary = [
            'total_employees_processed' => 0,
            'leaves_allocated_successfully' => [],
            'leaves_allocation_failed' => [],
            'leaves_already_allocated' => [],
            'employees_not_eligible_yet' => [],
            'employees_with_missing_data' => [],
            'other_errors' => []
        ];

        try {
            $employees = $this->db->table('tbl_employee_details as ed')
                ->select('ed.user_code_ref, ed.Gender, r.joining_date')
                ->join('tbl_register as r', 'r.user_code = ed.user_code_ref', 'left')
                ->where('ed.is_active', 'Y')
                ->get()
                ->getResult();

            if (empty($employees)) {
                return $this->respond(['status' => true, 'message' => 'No active employees found for leave allocation.'], 200);
            }

            // Get the valid column names from the destination table once for efficiency.
            $leaveTableColumns = $this->db->getFieldNames('tbl_emp_leaves_remains');

            foreach ($employees as $employee) {
                $resultsSummary['total_employees_processed']++;
                $userCodeRef = $employee->user_code_ref;

                $this->db->transStart();

                try {
                    if (empty($employee->joining_date)) {
                        $resultsSummary['employees_with_missing_data'][] = "Joining date not found for employee {$userCodeRef}.";
                        $this->db->transRollback();
                        continue;
                    }

                    $joiningDate = new DateTime($employee->joining_date);
                    $currentDate = new DateTime();
                    $threeMonthsAfterJoining = (clone $joiningDate)->add(new \DateInterval('P3M'));

                    if ($currentDate < $threeMonthsAfterJoining) {
                        $resultsSummary['employees_not_eligible_yet'][] = $userCodeRef;
                        $this->db->transRollback();
                        continue;
                    }

                    $currentYear = (int) date('Y');
                    $currentMonth = (int) date('n');
                    $financialYearStart = ($currentMonth >= 4) ? $currentYear : $currentYear - 1;
                    $financialYearEnd = $financialYearStart + 1;
                    $leavePeriodDescription = "1-04-{$financialYearStart}_to_31-03-{$financialYearEnd}";

                    $existingAllocation = $this->db->table('tbl_emp_leaves_remains')
                        ->where('user_ref_code', $userCodeRef)
                        ->where('years_start_end_date', $leavePeriodDescription)
                        ->get()
                        ->getRow();

                    if ($existingAllocation) {
                        $resultsSummary['leaves_already_allocated'][] = $userCodeRef;
                        $this->db->transRollback();
                        continue;
                    }

                    $builder = $this->db->table('tbl_leave_mst');
                    $builder->where('is_active', 'Y');
                    $builder->groupStart()
                        ->where('gender_applicability', 'ALL')
                        ->orWhere('gender_applicability', $employee->Gender);
                    $builder->groupEnd();
                    $leaveTypes = $builder->get()->getResultArray();

                    if (empty($leaveTypes)) {
                        $resultsSummary['other_errors'][] = "No applicable leave types found for employee {$userCodeRef}.";
                        $this->db->transRollback();
                        continue;
                    }

                    $leavesToInsert = [
                        'user_ref_code' => $userCodeRef,
                        'years_start_end_date' => $leavePeriodDescription,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $loggedInUser,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $loggedInUser,
                    ];

                    foreach ($leaveTypes as $leaveType) {
                        $leaveCode = strtoupper($leaveType['leave_code']);
                        // --- FIX: Use in_array() to check if the column name exists ---
                        if (in_array($leaveCode, $leaveTableColumns)) {
                            $leavesToInsert[$leaveCode] = (int) $leaveType['no_of_days_per_year'];
                        }
                    }

                    if ($this->db->table('tbl_emp_leaves_remains')->insert($leavesToInsert)) {
                        $this->db->transComplete();
                        $resultsSummary['leaves_allocated_successfully'][] = $userCodeRef;
                    } else {
                        $this->db->transRollback();
                        $resultsSummary['leaves_allocation_failed'][] = $userCodeRef;
                        log_message('error', "Failed to insert leaves for user {$userCodeRef}.");
                    }
                } catch (Exception $e) {
                    $this->db->transRollback();
                    log_message('error', "Exception for user {$userCodeRef} in allocateLeaves: " . $e->getMessage());
                    $resultsSummary['other_errors'][] = "Error for {$userCodeRef}: " . $e->getMessage();
                }
            }

            return $this->respond(['status' => true, 'message' => 'Leave allocation process completed.', 'summary' => $resultsSummary], 200);
        } catch (Exception $e) {
            log_message('error', 'Global Exception in allocateLeaves: ' . $e->getMessage());
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getEmployeeLeavesbyUserCodeRef()
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
        $user_code_ref = $request['user_code_ref'] ?? null;

        if (empty($user_code_ref)) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required.'], 400);
        }

        try {
            $leaves = $this->db->table('tbl_emp_leaves_remains')
                ->where('user_ref_code', $user_code_ref)
                ->get()
                ->getResult();

            if (!empty($leaves)) {
                return $this->respond(['status' => true, 'data' => $leaves], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'No leave records found for this employee.'], 404);
            }
        } catch (DatabaseException $e) {
            log_message('error', 'DatabaseException in getEmployeeLeavesbyUserCodeRef: ' . $e->getMessage());
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            log_message('error', 'General Exception in getEmployeeLeavesbyUserCodeRef: ' . $e->getMessage());
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function gettodaybirthdayemployee()
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

        try {
            $today = date('m-d');

            $db = \Config\Database::connect();

            $employees = $db->table('tbl_employee_details e')
                ->select('
                    r.First_Name,
                    r.Last_Name,
                    r.email,
                    r.phone_no,
                    e.photo_file,
                    e.user_code_ref,
                    DATE_FORMAT(e.date_of_birth, "%d-%m") AS birthday
                ')
                ->join('tbl_register r', 'r.user_code = e.user_code_ref')
                ->where('DATE_FORMAT(e.date_of_birth, "%m-%d")', $today)
                ->where('e.is_active', 'Y')
                ->get()
                ->getResult();

            if (!empty($employees)) {
                return $this->respond([
                    'status' => true,
                    'data' => $employees
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No employees with birthdays today.'
                ], 404);
            }
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'DatabaseException in gettodaybirthdayemployee: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            log_message('error', 'General Exception in gettodaybirthdayemployee: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getteamleader()
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

        try {
            $db = \Config\Database::connect();

            $teamLeaders = $db->table('tbl_register r')
                ->select('
                    r.user_code,
                    r.First_Name,
                    r.Last_Name,
                    r.Email,
                    r.Phone_no,
                    r.Designations,
                    r.role_ref_code,
                    r.team_lead_ref_code,
                    r.company_Code,
                    d.designation_name
                ')
                ->join('tbl_designation_mst d', 'd.designation_code = r.Designations', 'left')
                ->where('r.Designations', 'DESGCPL004')
                ->where('r.is_active', 'Y')
                ->get()
                ->getResult();

            if (!empty($teamLeaders)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Team leaders fetched successfully',
                    'data' => $teamLeaders
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No team leaders found.'
                ], 404);
            }
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'DatabaseException in getteamleader: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            log_message('error', 'General Exception in getteamleader: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function gethod()
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

        try {
            $db = \Config\Database::connect();

            $hods = $db->table('tbl_register r')
                ->select('
                    r.user_code,
                    r.First_Name,
                    r.Last_Name,
                    r.Email,
                    r.Phone_no,
                    r.Designations,
                    r.role_ref_code,
                    r.team_lead_ref_code,
                    r.company_Code,
                    d.designation_name
                ')
                ->join('tbl_designation_mst d', 'd.designation_code = r.Designations', 'left')
                ->where('r.Designations', 'DESGCPL003')
                ->where('r.is_active', 'Y')
                ->get()
                ->getResult();

            if (!empty($hods)) {
                return $this->respond([
                    'status' => true,
                    'message' => 'HODs fetched successfully',
                    'data' => $hods
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No HODs found.'
                ], 404);
            }
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'DatabaseException in gethod: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            log_message('error', 'General Exception in gethod: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateRegisterEmployee()
    {
        $request = $this->request->getJSON(true);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        // Validate JWT
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $updatedBy = $decoded->user_id ?? null;
        if (!$updatedBy) {
            return $this->respond(['status' => false, 'message' => 'Invalid user ID in token'], 400);
        }

        // Get user_code_ref from JSON body
        $userCodeRef = $request['user_code_ref'] ?? null;

        if (!$userCodeRef) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required for updating'], 400);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $registerTable = $db->table('tbl_register');
            $loginTable = $db->table('tbl_login');

            // Check if the employee exists in both tables
            $existingRegister = $registerTable->where('user_code', $userCodeRef)->get()->getRow();
            $existingLogin = $loginTable->where('user_code_ref', $userCodeRef)->get()->getRow();

            if (!$existingRegister || !$existingLogin) {
                $db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Employee not found with the provided user_code_ref'], 404);
            }

            $registerData = [];
            if (isset($request['First_Name'])) {
                $registerData['First_Name'] = $request['First_Name'];
            }
            if (isset($request['Last_Name'])) {
                $registerData['Last_Name'] = $request['Last_Name'];
            }
            if (isset($request['Email'])) {
                $registerData['Email'] = $request['Email'];
            }
            if (isset($request['Phone_no'])) {
                $registerData['Phone_no'] = $request['Phone_no'];
            }
            if (isset($request['company_Code'])) {
                $registerData['company_Code'] = $request['company_Code'];
            }
            if (isset($request['branch_code'])) {
                $registerData['branch_code'] = $request['branch_code'];
            }
            if (isset($request['department_code'])) {
                $registerData['department_code'] = $request['department_code'];
            }
            if (isset($request['Designations'])) {
                $registerData['Designations'] = $request['Designations'];
            }
            if (isset($request['joining_date'])) {
                $registerData['joining_date'] = $request['joining_date'];
            }
            if (isset($request['role_ref_code'])) {
                $registerData['role_ref_code'] = $request['role_ref_code'];
            }
            if (isset($request['team_lead_ref_code'])) {
                $registerData['team_lead_ref_code'] = $request['team_lead_ref_code'];
            }
            if (isset($request['hod_ref_code'])) {
                $registerData['hod_ref_code'] = $request['hod_ref_code'];
            }
            if (isset($request['Note'])) {
                $registerData['Note'] = $request['Note'];
            }

            $registerData['updated_by'] = $updatedBy;
            $registerData['updated_at'] = date('Y-m-d H:i:s');

            // Update tbl_register
            if (!empty($registerData)) {
                $registerTable->where('user_code', $userCodeRef)->update($registerData);
            }

            $loginData = [];
            if (isset($request['First_Name']) && isset($request['Last_Name'])) {
                $loginData['user_name'] = trim($request['First_Name'] . ' ' . $request['Last_Name']);
            }
            if (isset($request['Email'])) {
                $loginData['email'] = $request['Email'];
            }
            if (isset($request['password'])) {
                $loginData['password'] = password_hash($request['password'], PASSWORD_BCRYPT);
            }
            if (isset($request['role_ref_code'])) {
                $loginData['role_ref_code'] = $request['role_ref_code'];
            }
            if (isset($request['Designations'])) {
                $loginData['designations_code'] = $request['Designations'];
            }

            $loginData['updated_by'] = $updatedBy;
            $loginData['updated_at'] = date('Y-m-d H:i:s');

            // Update tbl_login
            if (!empty($loginData)) {
                $loginTable->where('user_code_ref', $userCodeRef)->update($loginData);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                $error = $db->error();
                $msg = isset($error['message']) && $error['message'] ? $error['message'] : 'Unknown DB error';
                return $this->respond(['status' => false, 'message' => 'Update failed due to database error: ' . $msg], 500);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Employee registration details updated successfully',
                'user_code_ref' => $userCodeRef
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Exception during update: ' . $e->getMessage()], 500);
        }
    }
    public function updateEmployeeDetails()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        // Correctly get user ID from the token payload.
        $updatedBy = $decoded->user_id ?? 'system';

        $userCodeRef = $this->request->getPost('user_code_ref');

        if (empty($userCodeRef)) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required for updating.'], 400);
        }

        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_code_ref' => 'required',
            'aadhar_number' => 'permit_empty|numeric|exact_length[12]',
            'pan_card_no' => 'permit_empty|alpha_numeric|exact_length[10]',
            'phone_number' => 'permit_empty|numeric|min_length[10]|max_length[15]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $builder = $this->db->table('tbl_employee_details');
        $existingDetails = $builder->where('user_code_ref', $userCodeRef)->get()->getRow();

        if (!$existingDetails) {
            return $this->respond(['status' => false, 'message' => 'Employee details not found for this user_code_ref'], 404);
        }

        $baseUploadDir = ROOTPATH . 'public/uploads/employees/' . $userCodeRef . '/';
        $certUploadDir = $baseUploadDir . 'certificates/';

        if (!is_dir($certUploadDir)) {
            mkdir($certUploadDir, 0777, true);
        }

        $fileFields = [
            'adhar_card_file' => 'adhar/',
            'pan_card_file' => 'pan/',
            'photo_file' => 'photo/',
        ];

        $uploaded = [];

        $this->db->transStart();
        try {
            foreach ($fileFields as $fileField => $subDir) {
                $dir = $baseUploadDir . $subDir;
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $file = $this->request->getFile($fileField);
                if ($file && $file->isValid() && !$file->hasMoved()) {
                    $newName = $file->getRandomName();
                    if (!$file->move($dir, $newName)) {
                        throw new \Exception("Failed to move uploaded file: " . $fileField);
                    }
                    $uploaded[$fileField] = 'public/uploads/employees/' . $userCodeRef . '/' . $subDir . $newName;
                }
            }

            $updateData = [
                'date_of_birth' => $this->request->getPost('date_of_birth') ?? $existingDetails->date_of_birth,
                'Gender' => $this->request->getPost('Gender') ?? $existingDetails->Gender,
                'aadhar_number' => $this->request->getPost('aadhar_number') ?? $existingDetails->aadhar_number,
                'pan_card_no' => $this->request->getPost('pan_card_no') ?? $existingDetails->pan_card_no,
                'blood_group' => $this->request->getPost('blood_group') ?? $existingDetails->blood_group,
                'current_address' => $this->request->getPost('current_address') ?? $existingDetails->current_address,
                'permanent_address' => $this->request->getPost('permanent_address') ?? $existingDetails->permanent_address,
                'phone_number' => $this->request->getPost('phone_number') ?? $existingDetails->phone_number,
                'second_phone_number' => $this->request->getPost('second_phone_number') ?? $existingDetails->second_phone_number,
                'marital_status' => $this->request->getPost('marital_status') ?? $existingDetails->marital_status,
                'personal_email' => $this->request->getPost('personal_email') ?? $existingDetails->personal_email,
                'reporting_to' => $this->request->getPost('reporting_to') ?? $existingDetails->reporting_to,
                'graduation' => $this->request->getPost('graduation') ?? $existingDetails->graduation,
                'graduationdegree' => $this->request->getPost('graduationdegree') ?? $existingDetails->graduationdegree,
                'graduationunivercity' => $this->request->getPost('graduationunivercity') ?? $existingDetails->graduationunivercity,
                'graduationspecialization' => $this->request->getPost('graduationspecialization') ?? $existingDetails->graduationspecialization,
                'graduation_year_of_completion' => $this->request->getPost('graduation_year_of_completion') ?? $existingDetails->graduation_year_of_completion,
                'post_graduation' => $this->request->getPost('post_graduation') ?? $existingDetails->post_graduation,
                'post_univercity' => $this->request->getPost('post_univercity') ?? $existingDetails->post_univercity,
                'postgraduationspecialization' => $this->request->getPost('postgraduationspecialization') ?? $existingDetails->postgraduationspecialization,
                'post_graduation_year_of_completion' => $this->request->getPost('post_graduation_year_of_completion') ?? $existingDetails->post_graduation_year_of_completion,
                'previous_organization_name' => $this->request->getPost('previous_organization_name') ?? $existingDetails->previous_organization_name,
                'previous_organization_designation' => $this->request->getPost('previous_organization_designation') ?? $existingDetails->previous_organization_designation,
                'reason_of_leaving' => $this->request->getPost('reason_of_leaving') ?? $existingDetails->reason_of_leaving,
                'previous_organization_start_year' => $this->request->getPost('previous_organization_start_year') ?? $existingDetails->previous_organization_start_year,
                'previous_organization_end_year' => $this->request->getPost('previous_organization_end_year') ?? $existingDetails->previous_organization_end_year,
                'emergency_contact_number' => $this->request->getPost('emergency_contact_number') ?? $existingDetails->emergency_contact_number,
                'emergency_contact_person_name' => $this->request->getPost('emergency_contact_person_name') ?? $existingDetails->emergency_contact_person_name,
                'emergency_contact_person_relation' => $this->request->getPost('emergency_contact_person_relation') ?? $existingDetails->emergency_contact_person_relation,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $updatedBy
            ];

            if (isset($uploaded['adhar_card_file'])) {
                $updateData['adhar_card_file'] = $uploaded['adhar_card_file'];
            }
            if (isset($uploaded['pan_card_file'])) {
                $updateData['pan_card_file'] = $uploaded['pan_card_file'];
            }
            if (isset($uploaded['photo_file'])) {
                $updateData['photo_file'] = $uploaded['photo_file'];
            }

            $builder->where('user_code_ref', $userCodeRef)->update($updateData);

            $certFiles = $this->request->getFiles();
            if (!empty($certFiles) && isset($certFiles['other_certificates'])) {
                $this->db->table('tbl_other_certificates_employee')->where('user_code_ref', $userCodeRef)->delete();

                $certArray = $certFiles['other_certificates'];
                $certBuilder = $this->db->table('tbl_other_certificates_employee');
                foreach ($certArray as $certFile) {
                    if ($certFile && $certFile->isValid() && !$certFile->hasMoved()) {
                        $newName = $certFile->getRandomName();
                        if (!$certFile->move($certUploadDir, $newName)) {
                            throw new \Exception("Failed to move uploaded certificate file.");
                        }
                        $certInsert = [
                            'user_code_ref' => $userCodeRef,
                            'file_name' => $certFile->getClientName(),
                            'file_path' => 'public/uploads/employees/' . $userCodeRef . '/certificates/' . $newName,
                            'created_at' => date('Y-m-d H:i:s'),
                            'created_by' => $updatedBy
                        ];
                        $certBuilder->insert($certInsert);
                    }
                }
            }
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                $err = $this->db->error();
                throw new \Exception('Update failed due to database error: ' . ($err['message'] ?? 'Unknown DB error'));
            }

            return $this->respond(['status' => true, 'message' => 'Employee details updated successfully.']);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    // public function updateInsuranceDetails()
    // {
    //     helper('jwtvalidate_helper');

    //     $headers = $this->request->getServer('HTTP_AUTHORIZATION');
    //     if (!$headers) {
    //         return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
    //     }

    //     $decoded = validatejwt($headers);
    //     if (!$decoded) {
    //         return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
    //     }

    //     $updatedBy = $decoded->user_id ?? 'system';
    //     $request = $this->request->getJSON(true) ?? [];

    //     // Get user_code_ref from the JSON body
    //     $userCodeRef = $request['user_code_ref'] ?? null;

    //     if (!$userCodeRef) {
    //         return $this->respond(['status' => false, 'message' => 'user_code_ref is required for updating.'], 400);
    //     }

    //     $validation = \Config\Services::validation();
    //     $validation->setRules([
    //         'user_code_ref' => 'required|max_length[50]',
    //         'spouse_name' => 'permit_empty|max_length[150]',
    //         'spouse_birthdate' => 'permit_empty|valid_date',
    //         'child_name' => 'permit_empty|max_length[150]',
    //         'child_birthdate' => 'permit_empty|valid_date',
    //         'child_two_name' => 'permit_empty|max_length[150]',
    //         'child_two_birthdate' => 'permit_empty|valid_date',
    //         'father_full_name' => 'permit_empty|max_length[150]',
    //         'father_birthdate' => 'permit_empty|valid_date',
    //         'mother_full_name' => 'permit_empty|max_length[150]',
    //         'mother_birthdate' => 'permit_empty|valid_date',
    //         'MIL_name' => 'permit_empty|max_length[150]',
    //         'MIL_birthdate' => 'permit_empty|valid_date',
    //         'FIL_name' => 'permit_empty|max_length[150]',
    //         'FIL_birthdate' => 'permit_empty|valid_date',
    //     ]);

    //     if (!$validation->run($request)) {
    //         return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
    //     }

    //     $db = \Config\Database::connect();
    //     $builder = $db->table('tbl_insurance_details');
    //     $existingInsurance = $builder->where('user_code_ref', $userCodeRef)->get()->getRow();

    //     if (!$existingInsurance) {
    //         return $this->respond(['status' => false, 'message' => 'Insurance details not found for this user_code_ref'], 404);
    //     }

    //     // Updated data array with all the new fields
    //     $updateData = [
    //         'spouse_name' => $request['spouse_name'] ?? $existingInsurance->spouse_name,
    //         'spouse_birthdate' => $request['spouse_birthdate'] ?? $existingInsurance->spouse_birthdate,
    //         'child_name' => $request['child_name'] ?? $existingInsurance->child_name,
    //         'child_birthdate' => $request['child_birthdate'] ?? $existingInsurance->child_birthdate,
    //         'child_two_name' => $request['child_two_name'] ?? $existingInsurance->child_two_name,
    //         'child_two_birthdate' => $request['child_two_birthdate'] ?? $existingInsurance->child_two_birthdate,

    //         // New fields to be updated
    //         'father_full_name' => $request['father_full_name'] ?? $existingInsurance->father_full_name,
    //         'father_birthdate' => $request['father_birthdate'] ?? $existingInsurance->father_birthdate,
    //         'mother_full_name' => $request['mother_full_name'] ?? $existingInsurance->mother_full_name,
    //         'mother_birthdate' => $request['mother_birthdate'] ?? $existingInsurance->mother_birthdate,
    //         'MIL_name' => $request['MIL_name'] ?? $existingInsurance->MIL_name,
    //         'MIL_birthdate' => $request['MIL_birthdate'] ?? $existingInsurance->MIL_birthdate,
    //         'FIL_name' => $request['FIL_name'] ?? $existingInsurance->FIL_name,
    //         'FIL_birthdate' => $request['FIL_birthdate'] ?? $existingInsurance->FIL_birthdate,

    //         'updated_by' => $updatedBy,
    //         'updated_at' => date('Y-m-d H:i:s')
    //     ];

    //     $db->transStart();
    //     try {
    //         if ($builder->where('user_code_ref', $userCodeRef)->update($updateData)) {
    //             $db->transComplete();
    //             return $this->respond([
    //                 'status' => true,
    //                 'message' => 'Insurance details updated successfully.'
    //             ]);
    //         } else {
    //             $db->transRollback();
    //             return $this->respond(['status' => false, 'message' => 'Failed to update insurance details'], 500);
    //         }
    //     } catch (\Throwable $e) {
    //         $db->transRollback();
    //         return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    //     }
    // }
    public function updateInsuranceDetails()
    {
        helper('jwtvalidate_helper');

        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $updatedBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true) ?? [];

        // Get user_code_ref from the JSON body
        $userCodeRef = $request['user_code_ref'] ?? null;
        if (!$userCodeRef) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required for updating.'], 400);
        }

        // Validation rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_code_ref' => 'required|max_length[50]',
            'spouse_name' => 'permit_empty|max_length[150]',
            'spouse_birthdate' => 'permit_empty|valid_date',
            'child_name' => 'permit_empty|max_length[150]',
            'child_birthdate' => 'permit_empty|valid_date',
            'child_two_name' => 'permit_empty|max_length[150]',
            'child_two_birthdate' => 'permit_empty|valid_date',
            'father_full_name' => 'permit_empty|max_length[150]',
            'father_birthdate' => 'permit_empty|valid_date',
            'mother_full_name' => 'permit_empty|max_length[150]',
            'mother_birthdate' => 'permit_empty|valid_date',
            'MIL_name' => 'permit_empty|max_length[150]',
            'MIL_birthdate' => 'permit_empty|valid_date',
            'FIL_name' => 'permit_empty|max_length[150]',
            'FIL_birthdate' => 'permit_empty|valid_date',
        ]);

        if (!$validation->run($request)) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_insurance_details');
        $existingInsurance = $builder->where('user_code_ref', $userCodeRef)->get()->getRow();

        if (!$existingInsurance) {
            return $this->respond(['status' => false, 'message' => 'Insurance details not found for this user_code_ref'], 404);
        }

        // Helper function to handle nulls
        function nullable($value)
        {
            return (isset($value) && $value !== '' && $value !== '0000-00-00') ? $value : null;
        }

        // Prepare update data safely
        $updateData = [
            'spouse_name' => array_key_exists('spouse_name', $request) ? nullable($request['spouse_name']) : $existingInsurance->spouse_name,
            'spouse_birthdate' => array_key_exists('spouse_birthdate', $request) ? nullable($request['spouse_birthdate']) : $existingInsurance->spouse_birthdate,
            'child_name' => array_key_exists('child_name', $request) ? nullable($request['child_name']) : $existingInsurance->child_name,
            'child_birthdate' => array_key_exists('child_birthdate', $request) ? nullable($request['child_birthdate']) : $existingInsurance->child_birthdate,
            'child_two_name' => array_key_exists('child_two_name', $request) ? nullable($request['child_two_name']) : $existingInsurance->child_two_name,
            'child_two_birthdate' => array_key_exists('child_two_birthdate', $request) ? nullable($request['child_two_birthdate']) : $existingInsurance->child_two_birthdate,
            'father_full_name' => array_key_exists('father_full_name', $request) ? nullable($request['father_full_name']) : $existingInsurance->father_full_name,
            'father_birthdate' => array_key_exists('father_birthdate', $request) ? nullable($request['father_birthdate']) : $existingInsurance->father_birthdate,
            'mother_full_name' => array_key_exists('mother_full_name', $request) ? nullable($request['mother_full_name']) : $existingInsurance->mother_full_name,
            'mother_birthdate' => array_key_exists('mother_birthdate', $request) ? nullable($request['mother_birthdate']) : $existingInsurance->mother_birthdate,
            'MIL_name' => array_key_exists('MIL_name', $request) ? nullable($request['MIL_name']) : $existingInsurance->MIL_name,
            'MIL_birthdate' => array_key_exists('MIL_birthdate', $request) ? nullable($request['MIL_birthdate']) : $existingInsurance->MIL_birthdate,
            'FIL_name' => array_key_exists('FIL_name', $request) ? nullable($request['FIL_name']) : $existingInsurance->FIL_name,
            'FIL_birthdate' => array_key_exists('FIL_birthdate', $request) ? nullable($request['FIL_birthdate']) : $existingInsurance->FIL_birthdate,
            'updated_by' => $updatedBy,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Transaction-safe update
        $db->transStart();
        try {
            $builder->where('user_code_ref', $userCodeRef)->update($updateData);
            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->respond(['status' => false, 'message' => 'Failed to update insurance details'], 500);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Insurance details updated successfully.'
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateBankDetails()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $updatedBy = $decoded->user_id ?? 'system';
        $request = $this->request->getJSON(true);

        // Get user_code_ref from the JSON body
        $userCodeRef = $request['user_code_ref'] ?? null;

        if (!$userCodeRef) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required for updating.'], 400);
        }

        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_code_ref' => 'required|max_length[50]',
            'account_holder_name' => 'permit_empty|max_length[150]',
            'bank_name' => 'permit_empty|max_length[150]',
            'account_number' => 'permit_empty|numeric|max_length[30]',
            'account_type' => 'permit_empty|max_length[50]',
            'branch_name' => 'permit_empty|max_length[150]',
            'IFSC_code' => 'permit_empty|alpha_numeric|max_length[20]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(['status' => false, 'message' => $validation->getErrors()], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_bank_details');
        $existingBankDetails = $builder->where('user_code_ref', $userCodeRef)->get()->getRow();

        if (!$existingBankDetails) {
            return $this->respond(['status' => false, 'message' => 'Bank details not found for this user_code_ref'], 404);
        }

        $updateData = [
            'account_holder_name' => $request['account_holder_name'] ?? $existingBankDetails->account_holder_name,
            'bank_name' => $request['bank_name'] ?? $existingBankDetails->bank_name,
            'account_number' => $request['account_number'] ?? $existingBankDetails->account_number,
            'account_type' => $request['account_type'] ?? $existingBankDetails->account_type,
            'branch_name' => $request['branch_name'] ?? $existingBankDetails->branch_name,
            'IFSC_code' => $request['IFSC_code'] ?? $existingBankDetails->IFSC_code,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updatedBy,
        ];

        $this->db->transStart();
        try {
            if ($builder->where('user_code_ref', $userCodeRef)->update($updateData)) {
                $this->db->transComplete();
                return $this->respond([
                    'status' => true,
                    'message' => 'Bank details updated successfully.',
                    'user_code_ref' => $userCodeRef
                ]);
            } else {
                $this->db->transRollback();
                return $this->respond(['status' => false, 'message' => 'Failed to update bank details to database.'], 500);
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getOtherCertificates()
    {
        // 1. Validate JWT token
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        // 2. Get user_code_ref from the request body
        $request = $this->request->getJSON(true);
        $userCodeRef = $request['user_code_ref'] ?? null;

        if (!$userCodeRef) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required.'], 400);
        }

        try {
            // 3. Query the database for certificates
            $certificates = $this->db->table('tbl_other_certificates_employee')
                ->select('id, user_code_ref, file_name, file_path, created_at')
                ->where('user_code_ref', $userCodeRef)
                ->get()
                ->getResult();

            // 4. Check if any certificates were found
            if ($certificates) {
                // Get the base URL
                $baseURL = base_url();

                // Loop through the results and prepend the base URL to each file_path
                foreach ($certificates as $certificate) {
                    $certificate->file_path = $baseURL . '/' . $certificate->file_path;
                }

                return $this->respond([
                    'status' => true,
                    'message' => 'Certificates retrieved successfully.',
                    'data' => $certificates
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No certificates found for the given user_code_ref.'
                ], 404);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'An error occurred while fetching certificates: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addAssets()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $decoded = validatejwt($headers);
        $createdBy = $decoded->user_id ?? null;
        if (!$createdBy) {
            return $this->respond(['status' => false, 'message' => 'Invalid user ID in token'], 400);
        }

        $requestData = $this->request->getJSON(true);
        $assets = is_array($requestData) && isset($requestData[0]) ? $requestData : [$requestData];

        if (empty($assets)) {
            return $this->respond(['status' => false, 'message' => 'Invalid or empty data provided.'], 400);
        }

        $validation = \Config\Services::validation();
        $builder = $this->db->table('tbl_assets');
        $successCount = 0;
        $errors = [];
        $createdAssets = []; // Array to store details of successfully created assets

        $this->db->transStart();

        try {
            foreach ($assets as $index => $assetData) {
                if (empty($assetData)) {
                    continue;
                }

                $validation->reset();
                $validation->setRules([
                    'asset_code' => 'required|max_length[100]',
                    // 'serial_number' => 'required|max_length[100]|is_unique[tbl_assets.serial_number]',
                    'user_code_ref' => 'required|max_length[100]',
                    'assigned_date' => 'required|valid_date',
                ]);

                if (!$validation->run($assetData)) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $assetData,
                        'message' => $validation->getErrors()
                    ];
                    continue;
                }

                $userExists = $this->db->table('tbl_register')
                    ->where('user_code', $assetData['user_code_ref'])
                    ->countAllResults() > 0;
                if (!$userExists) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $assetData,
                        'message' => 'The provided user_code_ref does not exist.'
                    ];
                    continue;
                }

                $data = [
                    'asset_code' => $assetData['asset_code'],
                    'asset_description' => $assetData['asset_description'] ?? null,
                    'serial_number' => $assetData['serial_number'],
                    'user_code_ref' => $assetData['user_code_ref'],
                    'assigned_date' => $assetData['assigned_date'],
                    'status' => $assetData['status'] ?? 'Assigned',
                    'condition' => $assetData['condition'] ?? 'Good',
                    'created_by' => $createdBy,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                if ($builder->insert($data)) {
                    $successCount++;
                    $data['id'] = $this->db->insertID();
                    $createdAssets[] = $data; // Add the full data to the new array
                } else {
                    $errors[] = [
                        'index' => $index,
                        'data' => $assetData,
                        'message' => 'Database insert failed.'
                    ];
                }
            }
            $this->db->transComplete();
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }

        if ($this->db->transStatus() === false) {
            return $this->respond(['status' => false, 'message' => 'Database transaction failed.'], 500);
        }

        if ($successCount > 0) {
            return $this->respondCreated([
                'status' => true,
                'message' => "Successfully created $successCount asset(s).",
                'created_assets' => $createdAssets, // Include the full details here
                'errors' => $errors
            ]);
        } else {
            return $this->respond(['status' => false, 'message' => 'No assets were created.', 'errors' => $errors], 400);
        }
    }

    public function getAssetsByUser()
    {

        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $requestData = $this->request->getJSON(true);
        $userCodeRef = $requestData['user_code_ref'] ?? null;

        if (empty($userCodeRef)) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required.'], 400);
        }

        $assets = $this->db->table('tbl_assets')
            ->where('user_code_ref', $userCodeRef)
            ->get()
            ->getResultArray();

        if (empty($assets)) {
            return $this->respond(['status' => true, 'message' => 'No assets found for this user.', 'assets' => []], 200);
        } else {
            return $this->respond(['status' => true, 'message' => 'Assets retrieved successfully.', 'assets' => $assets], 200);
        }
    }

    public function getAllUserAssets()
    {
        // Validate JWT Token
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!validatejwt($headers)) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }


        // Fetch all assets with user details (ordered by assigned_date DESC)
        $assets = $this->db->table('tbl_assets AS a')
            ->select('a.*, r.user_code, r.First_Name, r.Last_Name, asst.category as asset_name, asst.asset_type as asset_type')
            ->join('tbl_register AS r', 'r.user_code = a.user_code_ref', 'left')
            ->join('tbl_assetss AS asst', 'asst.asset_code  = a.asset_code', 'left') 
      ->where('a.is_active', 'Y')
            ->orderBy('a.assigned_date', 'DESC')
            ->get()
            ->getResultArray();

        if (empty($assets)) {
            return $this->respond([
                'status' => true,
                'message' => 'No assets found.',
                'assets' => []
            ], 200);
        }

        return $this->respond([
            'status' => true,
            'message' => 'All user assets retrieved successfully.',
            'assets' => $assets
        ], 200);
    }



    public function terminateEmployee()
    {
        helper('jwtvalidate_helper');

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
        $userCodeRef = $requestData['user_code_ref'] ?? null;
        $terminationDate = $requestData['termination_date'] ?? null;
        $terminationReason = $requestData['termination_reason'] ?? null;
        $assets = $requestData['assets'] ?? [];

        if (empty($userCodeRef) || empty($terminationDate) || empty($terminationReason)) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing required fields. user_code_ref, termination_date, and termination_reason are all required.'
            ], 400);
        }

        $this->db->transStart();

        try {
            // 1. Insert termination details into tbl_terminations
            $terminationData = [
                'user_code_ref' => $userCodeRef,
                'termination_date' => $terminationDate,
                'termination_reason' => $terminationReason,
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->table('tbl_terminations')->insert($terminationData);

            // 2. Update assets status if provided
            if (!empty($assets)) {
                foreach ($assets as $asset) {
                    $serialNumber = $asset['serial_number'] ?? null;
                    if ($serialNumber) {
                        $this->db->table('tbl_assets')
                            ->where('serial_number', $serialNumber)
                            ->update([
                                'returned_date' => $terminationDate,
                                'status' => 'In Stock',
                                'updated_by' => $createdBy,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    }
                }
            }

            // 3. Deactivate the user in tbl_login
            $this->db->table('tbl_login')
                ->where('user_code_ref', $userCodeRef)
                ->update(['is_active' => 'N']);

            // 4. Deactivate the user in tbl_register
            $this->db->table('tbl_register')
                ->where('user_code', $userCodeRef)
                ->update(['is_active' => 'N']);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->respond(['status' => false, 'message' => 'Transaction failed. Please try again.'], 500);
            }

            return $this->respond(['status' => true, 'message' => 'Employee termination and related records updated successfully.'], 200);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->respond(['status' => false, 'message' => 'An error occurred during the termination process: ' . $e->getMessage()], 500);
        }
    }

    public function getTerminationData()
    {
        // 1. Validate JWT token for security
        helper('jwtvalidate');
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $payload = validatejwt($headers);
        if (!$payload) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        // 2. Get user_code_ref from the POST request body
        $requestData = $this->request->getJSON(true);
        $userCodeRef = $requestData['user_code_ref'] ?? null;

        if (empty($userCodeRef)) {
            return $this->respond(['status' => false, 'message' => 'user_code_ref is required.'], 400);
        }

        try {
            // 3. Fetch primary termination and employee details
            $builder = $this->db->table('tbl_terminations');
            $builder->select('
                t.termination_date,
                t.termination_reason,
                CONCAT(r.First_Name, " ", r.Last_Name) AS employee_name,
                r.Designations AS designation_code,
                r.company_Code AS company_code
            ');
            $builder->from('tbl_terminations t');
            $builder->join('tbl_register r', 'r.user_code = t.user_code_ref');
            $builder->where('t.user_code_ref', $userCodeRef);
            $terminationData = $builder->get()->getRow();

            if (!$terminationData) {
                return $this->respond(['status' => false, 'message' => 'Termination data not found for this user.'], 404);
            }

            // 4. Fetch company name
            $companyName = $this->db->table('tbl_company')
                ->select('company_name')
                ->where('company_code', $terminationData->company_code)
                ->get()
                ->getRow()->company_name ?? 'N/A';

            // 5. Fetch designation name
            $designationName = $this->db->table('tbl_designation_mst')
                ->select('designation_name')
                ->where('designation_code', $terminationData->designation_code)
                ->get()
                ->getRow()->designation_name ?? 'N/A';

            // 6. Fetch returned assets list with additional details
            $assets = $this->db->table('tbl_assets')
                ->select('serial_number, returned_date, asset_name, asset_description')
                ->where('user_code_ref', $userCodeRef)
                ->get()
                ->getResult();

            // 7. Determine the top-level returned date from the first asset
            $topLevelReturnedDate = !empty($assets) ? $assets[0]->returned_date : null;

            // 8. Combine all data into a single response array
            $responseData = [
                'Termination Date' => $terminationData->termination_date,
                'Employee Name' => $terminationData->employee_name,
                'Company Name' => $companyName,
                'Last Working Date' => $terminationData->termination_date,
                'Reason of termination' => $terminationData->termination_reason,
                'Designation' => $designationName,
                'returned_date' => $topLevelReturnedDate, // New field as requested
                'Return Assets' => $assets
            ];

            return $this->respond(['status' => true, 'message' => 'Termination data retrieved successfully.', 'data' => $responseData], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'An error occurred while retrieving termination data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getInternData()
    {
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $payload = validatejwt($headers);
        if (!$payload) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        try {

            $builder = $this->db->table('tbl_register');
            $builder->select('
                tbl_register.*,
                tbl_employee_details.*
            ');
            $builder->join('tbl_employee_details', 'tbl_employee_details.user_code_ref = tbl_register.user_code');
            $builder->where('tbl_register.Designations', 'DESGCPL028');
            $query = $builder->get();
            $interns = $query->getResult();

            if ($interns) {
                return $this->respond(['status' => true, 'message' => 'Intern data retrieved successfully.', 'data' => $interns], 200);
            } else {
                return $this->respond(['status' => true, 'message' => 'No intern data found.', 'data' => []], 200);
            }
        } catch (\Exception $e) {

            return $this->respond([
                'status' => false,
                'message' => 'An error occurred while retrieving intern data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getteammember()
    {
        helper(['jwtvalidate_helper', 'url']);

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
        $teamLeaderUserCode = $requestData['user_code'] ?? null;

        if (empty($teamLeaderUserCode)) {
            return $this->respond([
                'status' => false,
                'message' => 'user_code for team leader is required.'
            ], 400);
        }

        try {
            $db = \Config\Database::connect();

            $teamMembers = $db->table('tbl_register r')
                ->select('
                    r.user_code AS user_code_ref,
                    r.First_Name,
                    r.Last_Name,
                    r.Email,
                    r.Phone_no,
                    r.Designations,
                    r.role_ref_code,
                    r.team_lead_ref_code,
                    r.company_Code,
                    d.designation_name,
                    ed.photo_file
                ')
                ->join('tbl_designation_mst d', 'd.designation_code = r.Designations', 'left')
                ->join('tbl_employee_details ed', 'ed.user_code_ref = r.user_code', 'left')
                ->where('r.team_lead_ref_code', $teamLeaderUserCode)
                ->where('r.is_active', 'Y')
                ->get()
                ->getResult();

            // Append base_url to photo_file path if it exists
            if (!empty($teamMembers)) {
                foreach ($teamMembers as $member) {
                    if (!empty($member->photo_file)) {
                        $member->photo_file = base_url($member->photo_file);
                    }
                }

                return $this->respond([
                    'status' => true,
                    'message' => 'Team members fetched successfully',
                    'data' => $teamMembers
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No team members found for this team leader.'
                ], 404);
            }
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'DatabaseException in getteammember: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            log_message('error', 'General Exception in getteammember: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function gettRegisterData()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');

        if (!$headers) {
            return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
        }

        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request = $this->request->getJSON(true);
        $user_code = $request['user_code'] ?? null; // Use 'user_code' for tbl_register

        try {
            $builder = $this->db->table('tbl_register');
            $builder->where('is_active', 'Y'); // Assuming 'is_active' exists in tbl_register

            if ($user_code) {
                $data = $builder->where('user_code', $user_code)->get()->getRow();
                if ($data) {
                    return $this->respond(['status' => true, 'data' => $data]);
                } else {
                    return $this->respond(['status' => false, 'message' => 'Registration data not found.'], 404);
                }
            } else {
                $data = $builder->get()->getResult();
                return $this->respond(['status' => true, 'data' => $data]);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function gettodaybirthdayemployeebycode()
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

        try {
            $today = date('m-d');
            $user_code = $decoded->user_id ?? null;  // Logged in user code

            if (!$user_code) {
                return $this->respond([
                    'status' => false,
                    'message' => 'User ID not found in token payload.'
                ], 400);
            }

            $db = \Config\Database::connect();

            $employees = $db->table('tbl_employee_details e')
                ->select('
                r.First_Name,
                r.Last_Name,
                r.email,
                r.phone_no,
                e.user_code_ref,
                DATE_FORMAT(e.date_of_birth, "%d-%m") AS birthday
            ')
                ->join('tbl_register r', 'r.user_code = e.user_code_ref')
                ->where('DATE_FORMAT(e.date_of_birth, "%m-%d")', $today)
                ->where('e.is_active', 'Y')
                ->where('e.user_code_ref', $user_code)   // ✅ filter by logged-in user code
                ->get()
                ->getResult();

            if (!empty($employees)) {
                return $this->respond([
                    'status' => true,
                    'data' => $employees
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No employees with birthdays today for this user.'
                ], 404);
            }
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'DatabaseException in gettodaybirthdayemployee: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            log_message('error', 'General Exception in gettodaybirthdayemployee: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update asset allotment
     */
    public function updateAsset()
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
        $id = $input['id'] ?? null;

        if (!$id) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset ID is required for update'
            ], 400);
        }

        $db = \Config\Database::connect();

        // Check if asset exists
        $existingAsset = $db->table('tbl_assets')->where('id', $id)->get()->getRow();
        if (!$existingAsset) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset not found'
            ], 404);
        }

        $data = [
            'asset_code' => $input['asset_code'] ?? $existingAsset->asset_name,
            'asset_description' => $input['asset_description'] ?? $existingAsset->asset_description,
            'serial_number' => $input['serial_number'] ?? $existingAsset->serial_number,
            'user_code_ref' => $input['user_code_ref'] ?? $existingAsset->user_code_ref,
            'assigned_date' => $input['assigned_date'] ?? $existingAsset->assigned_date,
            'status' => $input['status'] ?? $existingAsset->status,
            'condition' => $input['condition'] ?? $existingAsset->condition,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($db->table('tbl_assets')->where('id', $id)->update($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Asset updated successfully'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update asset'
            ], 500);
        }
    }

    /**
     * Delete asset allotment
     */
    public function deleteAsset()
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
        $id = $input['id'] ?? null;

        if (!$id) {
            return $this->respond([
                'status' => false,
                'message' => 'Asset ID is required for deletion'
            ], 400);
        }

        $db = \Config\Database::connect();

        if ($db->table('tbl_assets')->where('id', $id)->delete()) {
            return $this->respond([
                'status' => true,
                'message' => 'Asset deleted successfully'
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to delete asset'
            ], 500);
        }
    }

public function calculateSalaries()
{
    helper(['jwtvalidate_helper']);
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    if (!$headers) {
        return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
    }

    $decoded = validatejwt($headers);
    if (!$decoded) {
        return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
    }

    $request = $this->request->getJSON(true);
    $startMonth = $request['start_month'] ?? null;
    $endMonth = $request['end_month'] ?? null;

    if (!$startMonth || !$endMonth) {
        return $this->respond(['status' => false, 'message' => 'Both start_month and end_month are required'], 400);
    }

    try {
        // Generate salary report directly from the three tables
        $salaryReport = $this->generateSalaryReport($startMonth, $endMonth, null);

        return $this->respond([
            'status' => true,
            'message' => "Salaries calculated successfully for " . count($salaryReport) . " employees",
            'data' => $salaryReport,
            'processed_count' => count($salaryReport)
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => 'Salary calculation failed: ' . $e->getMessage()
        ], 500);
    }
}

public function getSalaryReport()
{
    helper(['jwtvalidate_helper']);
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');

    if (!$headers) {
        return $this->respond(['status' => false, 'message' => 'Authorization header missing'], 401);
    }

    $decoded = validatejwt($headers);
    if (!$decoded) {
        return $this->respond(['status' => false, 'message' => 'Invalid or expired token'], 401);
    }

    $request = $this->request->getJSON(true);
    $startMonth = $request['start_month'] ?? null;
    $endMonth = $request['end_month'] ?? null;
    $userRefCode = $request['user_ref_code'] ?? null;
    $page = $request['page'] ?? 1;
    $limit = $request['limit'] ?? 10;

    if (!$startMonth || !$endMonth) {
        return $this->respond(['status' => false, 'message' => 'Both start_month and end_month are required'], 400);
    }

    try {
        // Generate salary report directly from the three tables
        $allSalaryData = $this->generateSalaryReport($startMonth, $endMonth, $userRefCode);
        
        // Apply pagination
        $totalRecords = count($allSalaryData);
        $totalPages = ceil($totalRecords / $limit);
        $offset = ($page - 1) * $limit;
        $paginatedData = array_slice($allSalaryData, $offset, $limit);

        return $this->respond([
            'status' => true,
            'message' => 'Salary report retrieved successfully',
            'data' => $paginatedData,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages
            ]
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => 'Failed to retrieve salary report: ' . $e->getMessage()
        ], 500);
    }
}

private function generateSalaryReport($startMonth, $endMonth, $userRefCode = null)
{
    $startDate = $startMonth . '-01';
    $endDate = date('Y-m-t', strtotime($endMonth . '-01'));

    // Debug: Log the parameters
    log_message('debug', "Generating salary report from {$startDate} to {$endDate}");

    // Get all active employees with their salary details
    $builder = $this->db->table('tbl_register AS tr')
        ->select('
            tr.user_code,
            tr.First_Name,
            tr.Last_Name,
            tr.Email,
            tr.Phone_no,
            tr.Designations,
            tr.joining_date,
            tr.department_code,
            tsd.basic_salary,
            tsd.hra,
            tsd.special_allowance,
            tsd.insurance,
            tsd.pf,
            tsd.tds,
            tsd.pt,
            tsd.appraisal_date,
            tsd.authenticated_by
        ')
        ->join('tbl_salary_details AS tsd', 'tsd.user_ref_code = tr.user_code', 'left')
        ->join('tbl_login AS tl', 'tr.user_code = tl.user_code_ref', 'left')
        ->where('tr.is_active', 'Y')
        ->where('tl.is_active', 'Y')
        ->groupBy('tr.user_code')
        ->orderBy('tr.First_Name', 'ASC');

    // Filter by employee if provided
    if ($userRefCode) {
        $builder->where('tr.user_code', $userRefCode);
    }

    $employees = $builder->get()->getResult();
    
    // Debug: Check if employees and salary data are retrieved
    log_message('debug', "Found " . count($employees) . " employees");
    
    $salaryReport = [];

    foreach ($employees as $employee) {
        // Debug: Check individual employee salary data
        if (empty($employee->basic_salary) || $employee->basic_salary <= 0) {
            log_message('debug', "No salary data for employee: {$employee->user_code} - {$employee->First_Name}");
        }
        
        // Get attendance data from tbl_time
        $attendanceData = $this->calculateAttendanceForPeriod($employee->user_code, $startMonth, $endMonth);
        
        // Calculate salary based on attendance - PASS START MONTH
        $salaryCalculation = $this->calculateEmployeeSalary($employee, $attendanceData, $startMonth);
        
        $salaryReport[] = $salaryCalculation;
    }

    return $salaryReport;
}
private function calculateEmployeeSalary($employee, $attendanceData, $startMonth)
{
    // Get salary components from tbl_salary_details
    $basicSalary = floatval($employee->basic_salary ?? 0);
    $hra = floatval($employee->hra ?? 0);
    $specialAllowance = floatval($employee->special_allowance ?? 0);
    $insurance = floatval($employee->insurance ?? 0);
    $pf = floatval($employee->pf ?? 0);
    $tds = floatval($employee->tds ?? 0);
    $pt = floatval($employee->pt ?? 0);

    $totalWorkingDays = $attendanceData['total_days'];
    $presentDays = $attendanceData['present_days'];
    
    // Debug: Check if we have salary data
    if ($basicSalary <= 0) {
        log_message('debug', "No salary data for user: {$employee->user_code}, Basic: {$basicSalary}");
    }
    
    // Calculate prorated salary based on attendance
    if ($totalWorkingDays > 0 && $presentDays > 0) {
        $attendanceRatio = $presentDays / $totalWorkingDays;
        
        // Basic salary is prorated based on attendance
        $proratedBasicSalary = $basicSalary * $attendanceRatio;
        
        // HRA is usually 40-50% of basic, so prorate with basic
        $proratedHra = $hra * $attendanceRatio;
        
        // Special Allowance prorated
        $proratedSpecialAllowance = $specialAllowance * $attendanceRatio;
        
        // Deductions (insurance, PF, PT are usually fixed per month)
        $proratedInsurance = $insurance;
        $proratedPf = $pf;
        $proratedTds = $tds * $attendanceRatio;
        $proratedPt = $pt;
    } else {
        // No attendance or no working days
        $proratedBasicSalary = 0;
        $proratedHra = 0;
        $proratedSpecialAllowance = 0;
        $proratedInsurance = 0;
        $proratedPf = 0;
        $proratedTds = 0;
        $proratedPt = 0;
    }

    // Calculate totals
    $totalEarnings = $proratedBasicSalary + $proratedHra + $proratedSpecialAllowance;
    $totalDeductions = $proratedInsurance + $proratedPf + $proratedTds + $proratedPt;
    $netSalary = $totalEarnings - $totalDeductions;

    // Determine payment status based on net salary and attendance
    $paymentStatus = 'Pending';
    if ($netSalary <= 0) {
        $paymentStatus = 'N/A';
    } elseif ($presentDays == 0) {
        $paymentStatus = 'No Attendance';
    } elseif ($netSalary > 0) {
        $paymentStatus = 'Pending';
    }

    $attendanceRate = $totalWorkingDays > 0 ? ($presentDays / $totalWorkingDays) * 100 : 0;

    return [
        'user_ref_code' => $employee->user_code,
        'user_code' => $employee->user_code,
        'First_Name' => $employee->First_Name,
        'Last_Name' => $employee->Last_Name,
        'Email' => $employee->Email,
        'Phone_no' => $employee->Phone_no,
        'designation' => $employee->Designations,
        'joining_date' => $employee->joining_date,
        
        // Salary Components (from tbl_salary_details)
        'basic_salary' => round($proratedBasicSalary, 2),
        'hra' => round($proratedHra, 2),
        'special_allowance' => round($proratedSpecialAllowance, 2),
        'insurance' => round($proratedInsurance, 2),
        'pf' => round($proratedPf, 2),
        'tds' => round($proratedTds, 2),
        'pt' => round($proratedPt, 2),
        
        // Calculated Totals
        'total_earnings' => round($totalEarnings, 2),
        'total_deductions' => round($totalDeductions, 2),
        'net_salary' => round($netSalary, 2),
        
        // Attendance Data (from tbl_time)
        'attendance_days' => (int)$presentDays,
        'total_days' => (int)$totalWorkingDays,
        'attendance_rate' => round($attendanceRate, 2),
        
        // Status
        'payment_status' => $paymentStatus,
        'salaryMonth' => date('F Y', strtotime($startMonth . '-01')),
        'attendanceBadge' => $attendanceRate >= 90 ? 'success' : ($attendanceRate >= 75 ? 'warning' : 'danger'),
        'paymentBadge' => $paymentStatus === 'Paid' ? 'success' : ($paymentStatus === 'Pending' ? 'warning' : 'danger'),
        
        // Additional Info
        'last_appraisal_date' => $employee->appraisal_date,
        'authenticated_by' => $employee->authenticated_by,
        'calculation_timestamp' => date('Y-m-d H:i:s')
    ];
}
private function calculateAttendanceForPeriod($userRefCode, $startMonth, $endMonth)
{
    $startDate = $startMonth . '-01';
    $endDate = date('Y-m-t', strtotime($endMonth . '-01'));
    
    // Calculate total working days in the period (excluding weekends)
    $totalWorkingDays = $this->calculateWorkingDays($startDate, $endDate);
    
    // Get punch records from tbl_time
    $attendanceRecords = $this->db->table('tbl_time')
        ->select('today_date, punch_in, punch_out')
        ->where('user_ref_code', $userRefCode)
        ->where("DATE(today_date) >= DATE('{$startDate}')")
        ->where("DATE(today_date) <= DATE('{$endDate}')")
        ->where('is_active', 'Y')
        ->orderBy('today_date', 'ASC')
        ->get()
        ->getResult();

    $presentDays = 0;
    $uniqueDates = [];

    foreach ($attendanceRecords as $record) {
        $date = date('Y-m-d', strtotime($record->today_date));
        
        // Avoid counting same date multiple times
        if (in_array($date, $uniqueDates)) {
            continue;
        }

        // Count as present if they have valid punch in and punch out
        if (!empty($record->punch_in) && !empty($record->punch_out)) {
            
            // Calculate hours worked
            $punchIn = strtotime($record->punch_in);
            $punchOut = strtotime($record->punch_out);
            
            if ($punchIn && $punchOut && $punchOut > $punchIn) {
                $hoursWorked = ($punchOut - $punchIn) / 3600; // Convert seconds to hours
                
                // Consider as full day if worked more than 4 hours
                if ($hoursWorked >= 4) {
                    $presentDays++;
                } elseif ($hoursWorked >= 2) {
                    // Half day if worked between 2-4 hours
                    $presentDays += 0.5;
                }
                // Less than 2 hours is not counted
            } else {
                // If time calculation fails but has both punches, count as present
                $presentDays++;
            }
            
            $uniqueDates[] = $date;
        }
    }

    return [
        'present_days' => $presentDays,
        'total_days' => $totalWorkingDays,
        'attendance_rate' => $totalWorkingDays > 0 ? round(($presentDays / $totalWorkingDays) * 100, 2) : 0,
        'unique_dates' => $uniqueDates
    ];
}

private function calculateWorkingDays($startDate, $endDate)
{
    $start = strtotime($startDate);
    $end = strtotime($endDate);
    
    $workingDays = 0;
    $current = $start;
    
    // Loop through each day from start to end
    while ($current <= $end) {
        $dayOfWeek = date('N', $current); // 1 (Monday) to 7 (Sunday)
        if ($dayOfWeek <= 5) { // Monday to Friday are working days
            $workingDays++;
        }
        // Move to next day
        $current = strtotime('+1 day', $current);
    }
    
    return $workingDays;
}

    
}


