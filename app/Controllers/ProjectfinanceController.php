<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use Config\Services;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

class ProjectfinanceController extends BaseController
{
    protected $db;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
    }

        public function project_finance_add()
    {
        helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }
        $data = $this->request->getJSON(true);
        $Project_Finance_code = 'PF' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $db = \Config\Database::connect();
        $insertData = [
            'client_code'                                 => $data['client_code'] ?? null,
            'Project_Finance_code'                       => $Project_Finance_code,
            'Business_KYC_of_Firm'                       => $data['Business_KYC_of_Firm'] ?? null,
            'Company_MOA_AOA'                            => $data['Company_MOA_AOA'] ?? null,
            'PAN_NO'                                     => $data['PAN_NO'] ?? null,
            'COI_Partnership_Deed'                       => $data['COI_Partnership_Deed'] ?? null,
            'Udyam'                                      => $data['Udyam'] ?? null,
            'KYC_of_all_Directors_Partners_PAN_No'       => $data['KYC_of_all_Directors_Partners_PAN_No'] ?? null,
            'KYC_of_all_Directors_Partners_UID_No'       => $data['KYC_of_all_Directors_Partners_UID_No'] ?? null,
            'Financial_history_review'                   => $data['Financial_history_review'] ?? null,
            'Background_information_on_promoters'        => $data['Background_information_on_promoters'] ?? null,
            'Security_documentation'                     => $data['Security_documentation'] ?? null,
            'Existing_loan_information'                  => $data['Existing_loan_information'] ?? null,
            'Investment_planning_documents'              => $data['Investment_planning_documents'] ?? null,
            'Financial_capacity_verification'            => $data['Financial_capacity_verification'] ?? null,
            'Authorised_Person_Contact'                  => $data['Authorised_Person_Contact'] ?? null,
            'Authorised_Person_Email'                    => $data['Authorised_Person_Email'] ?? null,
            'File_Inward'                                => $data['File_Inward'] ?? null,
            'DPR_Preparation'                            => $data['DPR_Preparation'] ?? null,
            'DPR_Preparation_Remarks'                    => $data['DPR_Preparation_Remarks'] ?? null,
            'Bank_Identification'                        => $data['Bank_Identification'] ?? null,
            'Bank_Identification_Remarks'                => $data['Bank_Identification_Remarks'] ?? null,
            'File_Logged_in_Bank'                        => $data['File_Logged_in_Bank'] ?? null,
            'File_Logged_in_Bank_Remarks'                => $data['File_Logged_in_Bank_Remarks'] ?? null,
            'Approval_Status'                            => $data['Approval_Status'] ?? null,
            'Approval_Status_Remarks'                    => $data['Approval_Status_Remarks'] ?? null,
            'Fees_Collection_Status'                     => $data['Fees_Collection_Status'] ?? null,
            'Fees_Collection_Status_Remark'              => $data['Fees_Collection_Status_Remark'] ?? null,
            'Fees_Collection_Amount'                  => $data['Fees_Collection_Amount'] ?? null,
            'is_active'                                  => $data['is_active'] ?? 'Y',
            'created_at'                                 => date('Y-m-d H:i:s'),
            'created_by'                                 => $decoded->user_id ?? null
        ];

        if ($db->table('tbl_Project_finance')->insert($insertData)) {
            return $this->respond(['status' => true, 'message' => 'Project finance added successfully.'], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'Failed to insert data.'], 500);
        }
    }
    public function project_finance_update()
    {
        helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

       

        $data = $this->request->getJSON(true);
        $id = $data['Project_Finance_code'] ?? null;
         if (!$id) {
            return $this->respond(['status' => false, 'message' => 'Project Finance ID is required'], 400);
        }
        $updateData = [
            'client_code'                                 => $data['client_code'] ?? null,
            'Business_KYC_of_Firm'                       => $data['Business_KYC_of_Firm'] ?? null,
            'Company_MOA_AOA'                            => $data['Company_MOA_AOA'] ?? null,
            'PAN_NO'                                     => $data['PAN_NO'] ?? null,
            'COI_Partnership_Deed'                       => $data['COI_Partnership_Deed'] ?? null,
            'Udyam'                                      => $data['Udyam'] ?? null,
            'KYC_of_all_Directors_Partners_PAN_No'       => $data['KYC_of_all_Directors_Partners_PAN_No'] ?? null,
            'KYC_of_all_Directors_Partners_UID_No'       => $data['KYC_of_all_Directors_Partners_UID_No'] ?? null,
            'Financial_history_review'                   => $data['Financial_history_review'] ?? null,
            'Background_information_on_promoters'        => $data['Background_information_on_promoters'] ?? null,
            'Security_documentation'                     => $data['Security_documentation'] ?? null,
            'Existing_loan_information'                  => $data['Existing_loan_information'] ?? null,
            'Investment_planning_documents'              => $data['Investment_planning_documents'] ?? null,
            'Financial_capacity_verification'            => $data['Financial_capacity_verification'] ?? null,
            'Authorised_Person_Contact'                  => $data['Authorised_Person_Contact'] ?? null,
            'Authorised_Person_Email'                    => $data['Authorised_Person_Email'] ?? null,
            'File_Inward'                                => $data['File_Inward'] ?? null,
            'DPR_Preparation'                            => $data['DPR_Preparation'] ?? null,
            'DPR_Preparation_Remarks'                    => $data['DPR_Preparation_Remarks'] ?? null,
            'Bank_Identification'                        => $data['Bank_Identification'] ?? null,
            'Bank_Identification_Remarks'                => $data['Bank_Identification_Remarks'] ?? null,
            'File_Logged_in_Bank'                        => $data['File_Logged_in_Bank'] ?? null,
            'File_Logged_in_Bank_Remarks'                => $data['File_Logged_in_Bank_Remarks'] ?? null,
            'Approval_Status'                            => $data['Approval_Status'] ?? null,
            'Approval_Status_Remarks'                    => $data['Approval_Status_Remarks'] ?? null,
            'Fees_Collection_Status'                     => $data['Fees_Collection_Status'] ?? null,
            'Fees_Collection_Status_Remark'              => $data['Fees_Collection_Status_Remark'] ?? null,
                        'Fees_Collection_Amount'                  => $data['Fees_Collection_Amount'] ?? null,
            'is_active'                                  => $data['is_active'] ?? 'Y',
            'updated_at'                                 => date('Y-m-d H:i:s'),
            'updated_by'                                 => $decoded->user_id ?? null
        ];

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_Project_finance');

        if ($builder->where('Project_Finance_code', $id)->update($updateData)) {
            return $this->respond(['status' => true, 'message' => 'Project finance updated successfully.'], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'Failed to update data.'], 500);
        }
    }

    public function getProjectFinanceById()
    {
        helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);
          $data = $this->request->getJSON(true);
          $id = $data['Project_Finance_code'] ?? null;
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        if (!$id) {
            return $this->respond(['status' => false, 'message' => 'Project Finance ID is required'], 400);
        }

        $db = \Config\Database::connect();
        $data = $db->table('tbl_Project_finance')->where('Project_Finance_code', $id)->get()->getRow();

        if ($data) {
            return $this->respond(['status' => true, 'data' => $data], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'No record found'], 404);
        }
    }

    public function getallProject_Finance()
    {
        helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $db = \Config\Database::connect();
        $data = $db->table('tbl_Project_finance')->get()->getResult();

        if ($data) {
            return $this->respond(['status' => true, 'data' => $data], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'No records found'], 404);
        }
    }
}