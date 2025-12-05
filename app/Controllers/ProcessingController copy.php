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

class ProcessingController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = Database::connect();
    }


    public function addStageoneform()
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
        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }
        $data = $this->request->getJSON(true);
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_processing_from');
        $datePart = date('Ymd');
        $randomPart = mt_rand(1000, 9999);
        $processing_code = "PROC" . $datePart . $randomPart;
        $insertData = [
            'processing_code'              => $processing_code,
            'Stage'                        => $data['Stage'] ?? null,
            'fees_agreement'              => $data['fees_agreement'] ?? null,
            'Type'                         => $data['Type'] ?? null,
            'Investment_complete'         => $data['Investment_complete'] ?? null,
            'exp_invest_complete_month'   => $data['exp_invest_complete_month'] ?? null,
            'expected_fixed_capital'      => $data['expected_fixed_capital'] ?? null,
            'implementing_agency'         => $data['implementing_agency'] ?? null,
            'Zone'                         => $data['Zone'] ?? null,
            'incorporation_doc'           => $data['incorporation_doc'] ?? null,
            'Udyam_IEM'                    => $data['Udyam_IEM'] ?? null,
            'midc_land_doc'               => $data['midc_land_doc'] ?? null,
            'non_midc_land_doc'           => $data['non_midc_land_doc'] ?? null,
            'Building_docs'               => $data['Building_docs'] ?? null,
            'mpcb_cte'                     => $data['mpcb_cte'] ?? null,
            'mpcb_cto'                     => $data['mpcb_cto'] ?? null,
            'term_loan_sanction_letter'   => $data['term_loan_sanction_letter'] ?? null,
            'Date_of_production'          => $data['Date_of_production'] ?? null,
            'Balance_sheets_received'     => $data['Balance_sheets_received'] ?? null,
            'GST_data_received'           => $data['GST_data_received'] ?? null,
            'Basic_application'           => $data['Basic_application'] ?? null,
            'is_active'                   => 'Y',
            'created_by'                  => $userCode,
            'created_at'                  => date('Y-m-d H:i:s'),
        ];
        if ($builder->insert($insertData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Stage one data saved successfully.',
                'processing_code' => $processing_code,
                'insert_id' => $db->insertID()
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to save data.'
            ], 500);
        }
    }
    public function upadateaddStageoneform()
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

        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }

        $data = $this->request->getJSON(true);
        $processing_code = $data['processing_code'] ?? null;

        if (!$processing_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Processing code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_processing_from');

        $updateData = [
            'Stage'                      => $data['Stage'] ?? null,
            'fees_agreement'             => $data['fees_agreement'] ?? null,
            'Type'                       => $data['Type'] ?? null,
            'Investment_complete'        => $data['Investment_complete'] ?? null,
            'exp_invest_complete_month'  => $data['exp_invest_complete_month'] ?? null,
            'expected_fixed_capital'     => $data['expected_fixed_capital'] ?? null,
            'implementing_agency'        => $data['implementing_agency'] ?? null,
            'Zone'                       => $data['Zone'] ?? null,
            'incorporation_doc'         => $data['incorporation_doc'] ?? null,
            'Udyam_IEM'                  => $data['Udyam_IEM'] ?? null,
            'midc_land_doc'             => $data['midc_land_doc'] ?? null,
            'non_midc_land_doc'         => $data['non_midc_land_doc'] ?? null,
            'Building_docs'             => $data['Building_docs'] ?? null,
            'mpcb_cte'                   => $data['mpcb_cte'] ?? null,
            'mpcb_cto'                   => $data['mpcb_cto'] ?? null,
            'term_loan_sanction_letter' => $data['term_loan_sanction_letter'] ?? null,
            'Date_of_production'        => $data['Date_of_production'] ?? null,
            'Balance_sheets_received'   => $data['Balance_sheets_received'] ?? null,
            'GST_data_received'         => $data['GST_data_received'] ?? null,
            'Basic_application'         => $data['Basic_application'] ?? null,
            'updated_by'                => $userCode,
            'updated_at'                => date('Y-m-d H:i:s'),
        ];

        $builder->where('processing_code', $processing_code);
        $update = $builder->update($updateData);

        if ($update) {
            return $this->respond([
                'status' => true,
                'message' => 'Stage one data updated successfully.',
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update data.'
            ], 500);
        }
    }
    public function getStageoneform()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $request = $this->request->getJSON(true);
        $processing_code = $request['processing_code'] ?? '';
        $client_code = $request['client_code'] ?? '';
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $docs = $db->table('tbl_processing_from')
                ->select('*')
                ->where('processing_code', $processing_code)
                ->where('client_code', $client_code)
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $docs
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getStagetwoform()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $request = $this->request->getJSON(true);
        $processing_code = $request['processing_code'] ?? '';
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $docs = $db->table('tbl_processing_stagetwo')
                ->select('*')
                ->where('processing_code', $processing_code)
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $docs
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getStagetreeform()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $request = $this->request->getJSON(true);
        $processing_code = $request['processing_code'] ?? '';
        if (!validatejwt($authHeader)) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }
        $db = \Config\Database::connect();
        try {
            $docs = $db->table('tbl_processing_stagetree')
                ->select('*')
                ->where('processing_code', $processing_code)
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status' => true,
                'data' => $docs
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function addStagesecondform()
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
        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }
        $data = $this->request->getJSON(true);
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_processing_stagetwo');
        $insertData = [
            'processing_code'         => $data['processing_code'] ?? null,
            'prod_date_actual'        => $data['prod_date_actual'] ?? null,
            'fixed_capital_total'     => $data['fixed_capital_total'] ?? null,
            'expected_ec_amt'         => $data['expected_ec_amt'] ?? null,
            'bank_appraisal_recvd'    => $data['bank_appraisal_recvd'] ?? null,
            'ec_file_status'          => $data['ec_file_status'] ?? null,
            'all_data_recvd'          => $data['all_data_recvd'] ?? null,
            'remark'                  => $data['remark'] ?? null,
            'is_active'               => 'Y',
            'created_by'              => $userCode,
            'created_at'              => date('Y-m-d H:i:s'),
        ];
        if ($builder->insert($insertData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Stage two data saved successfully.',
                'insert_id' => $db->insertID()
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to save stage two data.'
            ], 500);
        }
    }
    public function updatestagesecondform()
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
        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }
        $data = $this->request->getJSON(true);
        $processing_code = $data['processing_code'] ?? null;
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_processing_stagetwo');
        $updateData = [

            'prod_date_actual'        => $data['prod_date_actual'] ?? null,
            'fixed_capital_total'     => $data['fixed_capital_total'] ?? null,
            'expected_ec_amt'         => $data['expected_ec_amt'] ?? null,
            'bank_appraisal_recvd'    => $data['bank_appraisal_recvd'] ?? null,
            'ec_file_status'          => $data['ec_file_status'] ?? null,
            'all_data_recvd'          => $data['all_data_recvd'] ?? null,
            'remark'                  => $data['remark'] ?? null,
            'is_active'               => 'Y',
            'updated_by'                => $userCode,
            'updated_at'                => date('Y-m-d H:i:s'),
        ];
        $builder->where('processing_code', $processing_code);
        $update = $builder->update($updateData);

        if ($update) {
            return $this->respond([
                'status' => true,
                'message' => 'Stage one data updated successfully.',
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update data.'
            ], 500);
        }
    }
    public function addStagetreeform()
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

        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }

        $data = $this->request->getJSON(true);
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_processing_stagetree ');

        $insertData = [
            'processing_code'           => $data['processing_code'] ?? null,
            'ips_all_docs_recvd'        => $data['ips_all_docs_recvd'] ?? null,
            'ips_status'                => $data['ips_status'] ?? null,
            'ips_sanction_letter_month' => $data['ips_sanction_letter_month'] ?? null,
            'ips_disbursement_recvd'    => $data['ips_disbursement_recvd'] ?? null,
            'created_by'                => $userCode,
            'created_at'                => date('Y-m-d H:i:s')
        ];

        if ($builder->insert($insertData)) {
            return $this->respond([
                'status' => true,
                'message' => 'IPS document data saved successfully.',
                'insert_id' => $db->insertID()
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to save IPS document data.'
            ], 500);
        }
    }
    public function updatestagetreeform()
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

        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }

        $data = $this->request->getJSON(true);
        $processing_code = $data['processing_code'] ?? null;
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_processing_stagetree ');

        $updateData = [
            'ips_all_docs_recvd'        => $data['ips_all_docs_recvd'] ?? null,
            'ips_status'                => $data['ips_status'] ?? null,
            'ips_sanction_letter_month' => $data['ips_sanction_letter_month'] ?? null,
            'ips_disbursement_recvd'    => $data['ips_disbursement_recvd'] ?? null,
            'created_by'                => $userCode,
            'created_at'                => date('Y-m-d H:i:s')
        ];

        $builder->where('processing_code', $processing_code);
        $update = $builder->update($updateData);

        if ($update) {
            return $this->respond([
                'status' => true,
                'message' => 'Stage one data updated successfully.',
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update data.'
            ], 500);
        }
    }

    public function asignStagepersons()
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

        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond([
                'status' => false,
                'message' => 'User not found in token'
            ], 400);
        }

        $data = $this->request->getJSON(true);
        $client_code = $data['client_code'] ?? null;
        $stage = $data['stage'] ?? null;
        $user_code_ref = $data['user_code'] ?? null;

        if ($stage == 1) {
            $coloumname = 'Stageoneperson';
        } elseif ($stage == 2) {
            $coloumname = 'Stagetwoperson';
        } else {
            $coloumname = 'Stagetreeperson';
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_client_mst');

        $updateData = [
            $coloumname    => $user_code_ref,
            'updated_by'   => $userCode,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        $builder->where('client_code', $client_code);
        $update = $builder->update($updateData);

        if ($update) {
            return $this->respond([
                'status' => true,
                'message' => 'Stage person assigned successfully.',
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to assign stage person.'
            ], 500);
        }
    }
    public function getstegeoneallotpersonsList()
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
        $clientRows = $db->table('tbl_client_mst')
            ->where('converts', 'Y')
            ->get()
            ->getResultArray();
        $finalData = [];
        foreach ($clientRows as $row) {
            $stages = [];
            foreach (['Stageoneperson', 'Stagetwoperson', 'Stagetreeperson'] as $stageCol) {
                $userName = 'Not Assigned';
                $userCode = $row[$stageCol] ?? null;
                if (!empty($userCode)) {
                    $userQuery = $db->table('tbl_login')
                        ->select('user_name')
                        ->where('user_code_ref', $userCode)
                        ->get()
                        ->getRowArray();

                    if (!empty($userQuery['user_name'])) {
                        $userName = $userQuery['user_name'];
                    }
                }
                $stages[] = [
                    'stage_type' => $stageCol,
                    'user_code'  => $userCode,
                    'user_name'  => $userName
                ];
            }
            $row['stage_allocations'] = $stages;
            $finalData[] = $row;
        }
        return $this->respond([
            'status' => true,
            'message' => 'Data fetched successfully',
            'data' => $finalData
        ]);
    }
    public function getstegetoperson()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        $userCode = $decodedToken->user_id ?? null;

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $db = \Config\Database::connect();

        $clientRows = $db->table('tbl_client_mst')
            ->where('converts', 'Y')
            ->groupStart()
            ->where('Stageoneperson', $userCode)
            ->orWhere('Stagetwoperson', $userCode)
            ->orWhere('Stagetreeperson', $userCode)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $finalData = [];
        foreach ($clientRows as $row) {
            $stages = [];
            foreach (['Stageoneperson', 'Stagetwoperson', 'Stagetreeperson'] as $stageCol) {
                $userName = 'Not Assigned';
                $stageUserCode = $row[$stageCol] ?? null;
                if (!empty($stageUserCode)) {
                    $userQuery = $db->table('tbl_login')
                        ->select('user_name')
                        ->where('user_code_ref', $stageUserCode)
                        ->get()
                        ->getRowArray();

                    if (!empty($userQuery['user_name'])) {
                        $userName = $userQuery['user_name'];
                    }
                }
                $stages[] = [
                    'stage_type' => $stageCol,
                    'user_code'  => $stageUserCode,
                    'user_name'  => $userName
                ];
            }

            // 🔹 Get processing data for this client
            $processingData = $db->table('tbl_processing_from')
                ->where('client_code', $row['client_code'])
                ->get()
                ->getResultArray();

            $row['stage_allocations'] = $stages;
            $row['processing_data']   = $processingData; // Contains processing_code and other columns

            $finalData[] = $row;
        }

        return $this->respond([
            'status' => true,
            'message' => 'Data fetched successfully',
            'data' => $finalData
        ]);
    }
}
