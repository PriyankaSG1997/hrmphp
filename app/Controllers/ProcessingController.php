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
            'Land_type'                  =>  $data['Land_type'] ?? null,
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
            'Expected_month_completion_investment' => $data['Expected_month_completion_investment'] ?? null,
            'City'                       => $data['City'] ?? null,
            'Incorporation_document_date' => $data['Incorporation_document_date'] ?? null,
            'Udyam_IEM_date'        =>   $data['Udyam_IEM_date'] ?? null,
            'MPCB_CTE_date'            => $data['MPCB_CTE_date'] ?? null,
            'MPCB_CTO_date'            => $data['MPCB_CTO_date'] ?? null,
            'Date_of_term_loan_sanction_letter' => $data['Date_of_term_loan_sanction_letter'] ?? null,
            'Amount_of_term_loan_sanction_letter' => $data['Amount_of_term_loan_sanction_letter'] ?? null,
            'exp_invest_complete_month'  => $data['exp_invest_complete_month'] ?? null,
            'expected_fixed_capital'     => $data['expected_fixed_capital'] ?? null,
            'implementing_agency'        => $data['implementing_agency'] ?? null,
            'Zone'                       => $data['Zone'] ?? null,
            'Land_type'                  =>  $data['Land_type'] ?? null,
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
            'stageone_remarks'          => $data['stageone_remarks'] ?? null,
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
            // 🔹 Get StageTree rows
            $docs = $db->table('tbl_processing_stagetree')
                ->select('*')
                ->where('processing_code', $processing_code)
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();

            // 🔹 Attach claims for each StageTree row
            foreach ($docs as &$doc) {
                $claims = $db->table('tbl_stagetree_claim')
                    ->where('processing_code', $doc['processing_code'])  // assuming primary key is `id`
                    ->where('is_active', 'Y')
                    ->get()
                    ->getResultArray();

                $doc['claims'] = $claims;
            }

            return $this->respond([
                'status' => true,
                'data'   => $docs
            ], 200);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
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
        $db   = \Config\Database::connect();
        $db->transStart();
        $stageData = [
            'processing_code'              => $data['processing_code'] ?? null,
            'ips_all_docs_recvd'           => $data['ips_all_docs_recvd'] ?? null,
            'IPS_on_hold_status_reason'    => $data['IPS_on_hold_status_reason'] ?? null,
            'Submitted_IPS_status_date'    => $data['Submitted_IPS_status_date'] ?? null,
            'Submitted_IPS_status_amount'  => isset($data['Submitted_IPS_status_amount']) ? (float)$data['Submitted_IPS_status_amount'] : null,
            'IPS_sanction_received_date'   => $data['IPS_sanction_received_date'] ?? null,
            'IPS_sanction_received_amount' => isset($data['IPS_sanction_received_amount']) ? (float)$data['IPS_sanction_received_amount'] : null,
            'Disbursement_received_date'   => $data['Disbursement_received_date'] ?? null,
            'ips_status'                   => $data['ips_status'] ?? null,
            'Remark'                       => $data['Remark'] ?? null,
            'ips_sanction_letter_month'    => $data['ips_sanction_letter_month'] ?? null,
            'ips_disbursement_recvd'       => isset($data['ips_disbursement_recvd']) ? (float)$data['ips_disbursement_recvd'] : null,
            'created_by'                   => $userCode,
            'created_at'                   => date('Y-m-d H:i:s')
        ];
        if (!$db->table('tbl_processing_stagetree')->insert($stageData)) {
            return $this->respond([
                'status'   => false,
                'message'  => 'Stage insert failed',
                'db_error' => $db->error()
            ], 500);
        }
        $stageId = $db->insertID();
        if (!empty($data['claims']) && is_array($data['claims'])) {
            foreach ($data['claims'] as $claim) {
                $claimCode = 'CLAIM' . date('YmdHis') . rand(1000, 9999);
                $claimData = [
                    'stage_id'            => $stageId,
                    'claim_code'          => $claimCode,
                    'processing_code'     => $data['processing_code'] ?? null,
                    'from_date'           => $claim['from_date'] ?? null,
                    'to_date'             => $claim['to_date'] ?? null,
                    'disbursement_amount' => isset($claim['disbursement_amount']) ? (float)$claim['disbursement_amount'] : 0,
                    'created_by'          => $userCode,
                    'created_at'          => date('Y-m-d H:i:s'),
                    'is_active'           => 'Y'
                ];
                if (!$db->table('tbl_stagetree_claim')->insert($claimData)) {
                    $db->transRollback();
                    return $this->respond([
                        'status'   => false,
                        'message'  => 'Claim insert failed',
                        'claimData' => $claimData,
                        'db_error' => $db->error()
                    ], 500);
                }
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respond([
                'status'  => false,
                'message' => 'Transaction failed - IPS & claims not saved'
            ], 500);
        }

        return $this->respond([
            'status'   => true,
            'message'  => 'IPS document & claim data saved successfully',
            'stage_id' => $stageId
        ]);
    }

    public function updatestagetreeform()
    {
        $db = \Config\Database::connect();
        $payload = $this->request->getJSON(true);

        if (!$payload || empty($payload['processing_code'])) {
            return $this->respond([
                'status'  => false,
                'message' => 'processing_code is required'
            ], 400);
        }

        $processingCode = $payload['processing_code'];
        $userCode       = $payload['updated_by'] ?? 'SYSTEM';
        $claims         = $payload['claims'] ?? [];

        $db->transStart();

        try {
            // 1️⃣ Update processing stage
            $stageData = [
                'ips_all_docs_recvd'          => $payload['ips_all_docs_recvd'] ?? null,
                'IPS_on_hold_status_reason'   => $payload['IPS_on_hold_status_reason'] ?? null,
                'Submitted_IPS_status_amount' => $payload['Submitted_IPS_status_amount'] ?? null,
                'Submitted_IPS_status_date'   => $payload['Submitted_IPS_status_date'] ?? null,
                'IPS_sanction_received_date'  => $payload['IPS_sanction_received_date'] ?? null,
                'IPS_sanction_received_amount' => $payload['IPS_sanction_received_amount'] ?? null,
                'Disbursement_received_date'  => $payload['Disbursement_received_date'] ?? null,
                'ips_status'                  => $payload['ips_status'] ?? null,
                'Remark'                      => $payload['Remark'] ?? null,
                'ips_sanction_letter_month'   => $payload['ips_sanction_letter_month'] ?? null,
                'ips_disbursement_recvd'      => $payload['ips_disbursement_recvd'] ?? null,
                'updated_by'                  => $userCode,
                'updated_at'                  => date('Y-m-d H:i:s'),
            ];

            $db->table('tbl_processing_stagetree')
                ->where('processing_code', $processingCode)
                ->update($stageData);

            // 2️⃣ Prepare list of claim_codes from payload
            $payloadClaimCodes = [];
            foreach ($claims as $claim) {
                if (!empty($claim['claim_code'])) {
                    $payloadClaimCodes[] = $claim['claim_code'];
                }
            }

            // 3️⃣ Deactivate any claims not in payload for this processing_code
            if (!empty($payloadClaimCodes)) {
                $db->table('tbl_stagetree_claim')
                    ->where('processing_code', $processingCode)
                    ->whereNotIn('claim_code', $payloadClaimCodes)
                    ->update([
                        'is_active'  => 'N',
                        'updated_by' => $userCode,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // If no claims sent, deactivate all existing claims for this processing_code
                $db->table('tbl_stagetree_claim')
                    ->where('processing_code', $processingCode)
                    ->update([
                        'is_active'  => 'N',
                        'updated_by' => $userCode,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            }

            // 4️⃣ Loop through payload claims to insert/update
            foreach ($claims as $claim) {
                $claimCode = $claim['claim_code'] ?? 'CLAIM' . date('YmdHis') . rand(1000, 9999);

                $exists = $db->table('tbl_stagetree_claim')
                    ->where('claim_code', $claimCode)
                    ->where('processing_code', $processingCode)
                    ->countAllResults();

                if ($exists > 0) {
                    // Update existing claim
                    $db->table('tbl_stagetree_claim')
                        ->where('claim_code', $claimCode)
                        ->where('processing_code', $processingCode)
                        ->update([
                            'from_date'           => $claim['from_date'] ?? null,
                            'to_date'             => $claim['to_date'] ?? null,
                            'disbursement_amount' => $claim['disbursement_amount'] ?? 0,
                            'is_active'           => 'Y',
                            'updated_by'          => $userCode,
                            'updated_at'          => date('Y-m-d H:i:s'),
                        ]);
                } else {
                    // Insert new claim
                    $db->table('tbl_stagetree_claim')->insert([
                        'processing_code'     => $processingCode,
                        'claim_code'          => $claimCode,
                        'from_date'           => $claim['from_date'] ?? null,
                        'to_date'             => $claim['to_date'] ?? null,
                        'disbursement_amount' => $claim['disbursement_amount'] ?? 0,
                        'created_by'          => $userCode,
                        'created_at'          => date('Y-m-d H:i:s'),
                        'is_active'           => 'Y'
                    ]);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->respond([
                    'status'  => false,
                    'message' => 'Failed to update stage & claims',
                    'error'   => [
                        'last_query' => $db->getLastQuery()->getQuery(),
                        'db_error'   => $db->error()
                    ]
                ], 500);
            }

            return $this->respond([
                'status'  => true,
                'message' => 'Stage & claims updated successfully'
            ], 200);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond([
                'status'  => false,
                'message' => 'Exception occurred',
                'error'   => $e->getMessage()
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
            $processingData = $db->table('tbl_processing_from')
                ->where('client_code', $row['client_code'])
                ->get()
                ->getResultArray();

            $row['stage_allocations'] = $stages;
            $row['processing_data']   = $processingData;

            $finalData[] = $row;
        }

        return $this->respond([
            'status' => true,
            'message' => 'Data fetched successfully',
            'data' => $finalData
        ]);
    }

    public function addclaim_processing()
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
        $userCode =  $decoded->user_id ?? null;
        $data = $this->request->getJSON(true);
        $client_code         = $data['client_code'] ?? null;
        $processing_code     = $data['processing_code'] ?? null;
        $claim_type          = $data['claim_type'] ?? null;
        $claim_period_from   = $data['claim_period_from'] ?? null;
        $claim_period_to     = $data['claim_period_to'] ?? null;
        $documentation       = $data['documentation'] ?? null;
        $claim_status        = $data['claim_status'] ?? null;
        $remarks             = $data['remarks'] ?? null;
        $date_of_sanction    = $data['date_of_sanction'] ?? null;
        $provisional_amount  = $data['provisional_amount'] ?? 0;
        $sanction_percentage = $data['sanction_percentage'] ?? null;
        $disbursements       = $data['disbursements'] ?? [];
        if (!$client_code || !$claim_type) {
            return $this->respond([
                'status' => false,
                'message' => 'Client code and claim type are required.'
            ], 400);
        }
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $claim_code = 'CLAIM' . date('YmdHis') . rand(1000, 9999);
            $claimData = [
                'claim_code'          => $claim_code,
                'client_code'         => $client_code,
                'processing_code'     => $processing_code,
                'claim_type'          => $claim_type,
                'claim_period_from'   => $claim_period_from,
                'claim_period_to'     => $claim_period_to,
                'documentation_status'       => $documentation,
                'claim_status'        => $claim_status,
                'remarks'             => $remarks,
                'date_of_sanction'    => $date_of_sanction,
                'provisional_sanction_amount'  => $provisional_amount,
                'sanction_percentage' => $sanction_percentage,
                'created_by'          => $userCode,
                'created_at'          => (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
            ];
            $insertClaim = $db->table('tbl_claim_processing')->insert($claimData);
            if (!$insertClaim) {
                $error = $db->error();
                throw new \Exception('Failed to insert claim processing data: ' . json_encode($error));
            }
            if (!empty($disbursements)) {
                foreach ($disbursements as $dis) {
                    $disbursementData = [
                        'claim_code'   => $claim_code,
                        'disbursement_date'    => $dis['date'] ?? null,
                        'amount'  => $dis['amount'] ?? 0,
                        'percentage'   => $dis['percentage'] ?? null,
                        'created_by'   => $userCode,
                        'created_at'   => (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
                    ];
                    $insertDisb = $db->table('tbl_claim_disbursement')->insert($disbursementData);

                    if (!$insertDisb) {
                        throw new \Exception('Failed to insert disbursement record');
                    }
                }
            }
            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed while saving claim data');
            }
            return $this->respond([
                'status' => true,
                'message' => 'Claim processing data saved successfully.',
                'claim_code' => $claim_code
            ], 200);
        } catch (\Throwable $e) {
            $db->transRollback(); // ❌ Rollback Transaction
            log_message('error', 'Error in addclaim_processing: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Error saving claim processing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  public function update_claim_processing()
{
    helper(['jwtvalidate_helper']);
    $headers = $this->request->getServer('HTTP_AUTHORIZATION');
    $decoded = validatejwt($headers);
    if (!$decoded) {
        return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
    }
    $userCode = $decoded->user_id ?? null;
    $data = $this->request->getJSON(true);
    $processing_code = $data['processing_code'] ?? null;
    if (!$processing_code) {
        return $this->respond(['status' => false, 'message' => 'Processing code is required.'], 400);
    }
    $db = \Config\Database::connect();
    $db->transStart();
    try {
        $claim = $db->table('tbl_claim_processing')
            ->select('claim_code')
            ->where('processing_code', $processing_code)
            ->get()
            ->getRow();

        if (!$claim) {
            throw new \Exception('Invalid processing code — no claim found.');
        }
        $claim_code = $claim->claim_code;
        $updateData = [
            'claim_type'                  => $data['claim_type'] ?? null,
            'claim_period_from'           => $data['claim_period_from'] ?? null,
            'claim_period_to'             => $data['claim_period_to'] ?? null,
            'documentation_status'        => $data['documentation'] ?? null,
            'claim_status'                => $data['claim_status'] ?? null,
            'remarks'                     => $data['remarks'] ?? null,
            'date_of_sanction'            => $data['date_of_sanction'] ?? null,
            'provisional_sanction_amount' => $data['provisional_amount'] ?? 0,
            'sanction_percentage'         => $data['sanction_percentage'] ?? null,
            'updated_by'                  => $userCode,
            'updated_at'                  => (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
        ];
        $db->table('tbl_claim_processing')
            ->where('processing_code', $processing_code)
            ->update($updateData);
        if ($db->affectedRows() == 0) {
            throw new \Exception('No rows updated or invalid processing_code');
        }
        $db->table('tbl_claim_disbursement')->where('claim_code', $claim_code)->delete();
        if (!empty($data['disbursements'])) {
            foreach ($data['disbursements'] as $dis) {
                $disbursementData = [
                    'claim_code'        => $claim_code,
                    'disbursement_date' => $dis['date'] ?? null,
                    'amount'            => $dis['amount'] ?? 0,
                    'percentage'        => $dis['percentage'] ?? null,
                    'updated_by'        => $userCode,
                    'updated_at'        => (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
                ];
                $db->table('tbl_claim_disbursement')->insert($disbursementData);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \Exception('Transaction failed while updating claim processing data.');
        }

        return $this->respond([
            'status' => true,
            'message' => 'Claim processing and disbursements updated successfully.'
        ]);

    } catch (\Throwable $e) {
        $db->transRollback();
        return $this->respond([
            'status' => false,
            'message' => 'Error updating claim processing data.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function getClaimProcessingById()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }
        $data = $this->request->getJSON(true);
        $processing_code = $data['processing_code'] ?? null;
        if (!$processing_code) {
            return $this->respond(['status' => false, 'message' => 'processing code is required.'], 400);
        }
        $db = \Config\Database::connect();
        $claim = $db->table('tbl_claim_processing')
            ->where('processing_code', $processing_code)
            ->get()
            ->getRowArray();
        if (!$claim) {
            return $this->respond(['status' => false, 'message' => 'Claim not found.'], 404);
        }
        $disbursements = $db->table('tbl_claim_disbursement')
            ->where('claim_code', $claim['claim_code'])
            ->get()
            ->getResultArray();

        $claim['disbursements'] = $disbursements;
        return $this->respond([
            'status' => true,
            'message' => 'Claim data fetched successfully.',
            'data' => $claim
        ]);
    }
    public function getAllClaimProcessing()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);
        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Unauthorized or Invalid Token'], 401);
        }

        $db = \Config\Database::connect();

        $claims = $db->table('tbl_claim_processing AS tcp')
            ->select('tcp.*, tcm.client_name')
            ->join('tbl_client_mst AS tcm', 'tcp.client_code = tcm.client_code', 'left')
            ->orderBy('tcp.created_at', 'DESC')
            ->get()
            ->getResultArray();

        if (empty($claims)) {
            return $this->respond(['status' => true, 'message' => 'No claims found.', 'data' => []]);
        }

        return $this->respond([
            'status' => true,
            'message' => 'Claim processing list fetched successfully.',
            'data' => $claims
        ]);
    }
    public function add_ED_processing()
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
        $userCode = $decoded->user_id ?? null;
        $data = $this->request->getJSON(true);
        $processing_code        = $data['processing_code'] ?? null;
        $client_code             = $data['client_code'] ?? null;
        $ed_online_application   = $data['ed_online_application'] ?? null;   // Yes / No
        $ed_physical_submission  = $data['ed_physical_submission'] ?? null;  // Submitted / Not Submitted
        $ed_exemption_order      = $data['ed_exemption_order'] ?? null;      // Received / Pending
        $ed_month                = $data['ed_month'] ?? null;                // e.g. September, 2025
        $ed_refund_submitted     = $data['ed_refund_submitted'] ?? null;     // Submitted / Pending
        $ed_refund_received      = $data['ed_refund_received'] ?? null;      // Received / Pending
        if (!$client_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Client code is required.'
            ], 400);
        }
        $db = \Config\Database::connect();
        try {
            $ed_code = 'ED' . date('YmdHis') . rand(1000, 9999);
            $edData = [
                'ed_code'              => $ed_code,
                'processing_code'      => $processing_code,
                'client_code'          => $client_code,
                'ed_online_application' => $ed_online_application,
                'ed_physical_submission' => $ed_physical_submission,
                'ed_exemption_order'   => $ed_exemption_order,
                'ed_month'             => $ed_month,
                'ed_refund_submitted'  => $ed_refund_submitted,
                'ed_refund_received'   => $ed_refund_received,
                'created_by'           => $userCode,
                'created_at'           => date('Y-m-d H:i:s'),
            ];
            $insert = $db->table('tbl_ed_processing')->insert($edData);
            if (!$insert) {
                $error = $db->error();
                throw new \Exception('Failed to insert ED processing data: ' . json_encode($error));
            }
            return $this->respond([
                'status' => true,
                'message' => 'ED processing data saved successfully.',
                'ed_code' => $ed_code
            ], 200);
        } catch (\Throwable $e) {
            log_message('error', 'Error in add_ED_processing: ' . $e->getMessage());
            return $this->respond([
                'status' => false,
                'message' => 'Error saving ED processing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function update_ED_processing()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);

        if (!$decoded) {
            return $this->respond(['status'=>false,'message'=>'Unauthorized or Invalid Token'], 401);
        }

        $userCode = $decoded->user_id ?? null;
        $data = $this->request->getJSON(true);

        $processing_code = $data['processing_code'] ?? null;
        if (!$processing_code) {
            return $this->respond(['status'=>false,'message'=>'ED code is required.'], 400);
        }

        $updateData = [
            'ed_online_application' => $data['ed_online_application'] ?? null,
            'ed_physical_submission'=> $data['ed_physical_submission'] ?? null,
            'ed_exemption_order' => $data['ed_exemption_order'] ?? null,
            'ed_month' => $data['ed_month'] ?? null,
            'ed_refund_submitted' => $data['ed_refund_submitted'] ?? null,
            'ed_refund_received' => $data['ed_refund_received'] ?? null,
            'updated_by' => $userCode,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $db = \Config\Database::connect();

        try {
            $update = $db->table('tbl_ed_processing')->where('processing_code', $processing_code)->update($updateData);
            if (!$update) throw new \Exception('Failed to update ED processing record');

            return $this->respond(['status'=>true,'message'=>'ED processing updated successfully.','processing_code'=>$processing_code], 200);

        } catch (\Throwable $e) {
            log_message('error','update_ED_processing: '.$e->getMessage());
            return $this->respond(['status'=>false,'message'=>'Error updating ED processing','error'=>$e->getMessage()], 500);
        }
    }
    public function getEDProcessingById()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);
        $data = $this->request->getJSON(true);
        $processing_code = $data['processing_code'] ?? null;
        if (!$decoded) {
            return $this->respond(['status'=>false,'message'=>'Unauthorized or Invalid Token'], 401);
        }

        if (!$processing_code) {
            return $this->respond(['status'=>false,'message'=>'ED code is required'], 400);
        }

        $db = \Config\Database::connect();
        $record = $db->table('tbl_ed_processing')->where('processing_code',$processing_code)->get()->getRow();

        if (!$record) {
            return $this->respond(['status'=>false,'message'=>'No ED processing record found'], 404);
        }

        return $this->respond(['status'=>true,'message'=>'ED processing fetched','data'=>$record], 200);
    }
    public function getAllEDProcessing()
    {
        helper(['jwtvalidate_helper']);
        $headers = $this->request->getServer('HTTP_AUTHORIZATION');
        $decoded = validatejwt($headers);

        if (!$decoded) {
            return $this->respond(['status'=>false,'message'=>'Unauthorized or Invalid Token'], 401);
        }

        $db = \Config\Database::connect();
        $records = $db->table('tbl_ed_processing')->orderBy('created_at','DESC')->get()->getResult();

        return $this->respond(['status'=>true,'message'=>'All ED processing records fetched','data'=>$records], 200);
    }

}
