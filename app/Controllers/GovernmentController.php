<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use Config\Services;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

class GovernmentController extends BaseController
{
    protected $db;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
    }

      // ---------- Add ----------
    public function addcgov()
    {
        helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $data = $this->request->getJSON(true);
            $cgov_code = 'GSOV' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        $insertData = [
            'cgov_code'                   => $cgov_code,
            'File_Inward'                 => $data['File_Inward'] ?? null,
            'Document_Collection'         => $data['Document_Collection'] ?? null,
            'DPR_Preparation'             => $data['DPR_Preparation'] ?? null,
            'Bank_Identification'         => $data['Bank_Identification'] ?? null,
            'File_Logged_in_Bank'         => $data['File_Logged_in_Bank'] ?? null,
            'Approval_Status'             => $data['Approval_Status'] ?? null,
            'Approval_Status_remark'      => $data['Approval_Status_remark'] ?? null,
            'Fees_Collection_Status'      => $data['Fees_Collection_Status'] ?? null,
            'Fees_Collection_Status_ammount' => $data['Fees_Collection_Status_ammount'] ?? null,
            'is_active'                   => $data['is_active'] ?? 'Y',
            'created_at'                  => date('Y-m-d H:i:s'),
            'created_by'                  => $data['created_by'] ?? null
        ];

        $this->db->table('tbl_Centre_Government_Scheme_pfinance')->insert($insertData);

        return $this->respond(['status' => true, 'message' => 'Record added successfully.']);
    }
    public function updatecgov()
    {
        helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }
        $data = $this->request->getJSON(true);
        $id = $data['cgov_code'] ?? null;
        if (!$id) {
            return $this->respond(['status' => false, 'message' => 'ID is required.'], 400);
        }

        $updateData = [
            'File_Inward'                 => $data['File_Inward'] ?? null,
            'Document_Collection'         => $data['Document_Collection'] ?? null,
            'DPR_Preparation'             => $data['DPR_Preparation'] ?? null,
            'Bank_Identification'         => $data['Bank_Identification'] ?? null,
            'File_Logged_in_Bank'         => $data['File_Logged_in_Bank'] ?? null,
            'Approval_Status'             => $data['Approval_Status'] ?? null,
            'Approval_Status_remark'      => $data['Approval_Status_remark'] ?? null,
            'Fees_Collection_Status'      => $data['Fees_Collection_Status'] ?? null,
            'Fees_Collection_Status_ammount' => $data['Fees_Collection_Status_ammount'] ?? null,
            'is_active'                   => $data['is_active'] ?? 'Y',
            'updated_at'                  => date('Y-m-d H:i:s'),
            'updated_by'                  => $data['updated_by'] ?? null
        ];

        $this->db->table('tbl_Centre_Government_Scheme_pfinance')
            ->where('cgov_code', $id)
            ->update($updateData);

        return $this->respond(['status' => true, 'message' => 'Record updated successfully.']);
    }

    public function getAllcgov()
    {
         helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }
        $data = $this->db->table('tbl_Centre_Government_Scheme_pfinance')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResult();

        return $this->respond(['status' => true, 'data' => $data]);
    }
    public function getByIdcgov()
    {
        helper(['jwtvalidate']);
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }
        $data = $this->request->getJSON(true);
        $id = $data['cgov_code'] ?? null;
        if (!$id) {
            return $this->respond(['status' => false, 'message' => 'ID is required.'], 400);
        }

        $row = $this->db->table('tbl_Centre_Government_Scheme_pfinance')
            ->where('cgov_code', $id)
            ->get()
            ->getRow();

        if (!$row) {
            return $this->respond(['status' => false, 'message' => 'Record not found.'], 404);
        }

        return $this->respond(['status' => true, 'data' => $row]);
    }
}