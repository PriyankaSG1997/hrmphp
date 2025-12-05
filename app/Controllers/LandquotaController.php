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

class LandquotaController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate_helper', 'url']);
        $this->db = Database::connect();
    }

    public function add_land_quota()
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
                'message' => 'User code not found in token'
            ], 400);
        }
        $request = $this->request->getJSON(true);
        $requiredFields = ['district', 'area_size', 'zone', 'industry_type'];
        foreach ($requiredFields as $field) {
            if (empty($request[$field])) {
                return $this->respond([
                    'status' => false,
                    'message' => "Field '$field' is required"
                ], 400);
            }
        }
        $landcode = 'LQ' . date('YmdHis') . strtoupper(substr(uniqid(), -5));
        $db = \Config\Database::connect();
        $data = [
            'district'       => $request['district'],
            'land_code'      => $landcode,
            'area_size'      => $request['area_size'],
            'zone'           => $request['zone'],
            'industry_type'  => $request['industry_type'],
            'nearby'         => $request['nearby'] ?? null,
            'address'        => $request['address'] ?? null,
            'contact_details' => $request['contact_details'] ?? null,
            'remarks'        => $request['remarks'] ?? null,
            'created_by'     => $userCode,
             'Acre'        => $request['Acre'] ?? null,
            'Gunta'        => $request['Gunta'] ?? null,
            'alloted'       => 'N',
            'created_at'     => date('Y-m-d H:i:s'),
            'is_active'      => 'Y'
        ];
        $inserted = $db->table('tbl_land_quota')->insert($data);
        if (!$inserted) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to add land quota',
                'error' => $db->error()
            ], 500);
        }
        return $this->respond([
            'status' => true,
            'message' => 'Land quota added successfully',
            'data' => $data
        ], 200);
    }

    // public function getAll_land_quota()
    // {
    //     helper('jwtvalidate');
    //     $authHeader = $this->request->getHeaderLine('Authorization');
    //     $decodedToken = validatejwt($authHeader);
    //     if (!$decodedToken) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Invalid or missing JWT'
    //         ], 401);
    //     }
    //     $db = \Config\Database::connect();
    //     $builder = $db->table('tbl_land_quota');
    //     $landQuotas = $builder->where('is_active', 'Y')->get()->getResultArray();
    //     return $this->respond([
    //         'status' => true,
    //         'message' => 'Land quotas retrieved successfully',
    //         'data' => $landQuotas
    //     ], 200);
    // }
public function getAll_land_quota()
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

    try {
        // ✅ Step 1: Fetch all active land quotas with ALL columns
        $landQuotas = $db->table('tbl_land_quota')
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        if (empty($landQuotas)) {
            return $this->respond([
                'status' => true,
                'message' => 'No active land quotas found',
                'data' => []
            ], 200);
        }

        $finalData = [];

        foreach ($landQuotas as $quota) {
            $landCode = $quota['land_code'];
            $totalArea = floatval($quota['area_size']);

            // ✅ Step 2: Purchased area from tbl_investment_in_land
            $investment = $db->table('tbl_investment_in_land')
                ->selectSum('Purchase_Land_Area', 'purchased_area')
                ->where('land_number', $landCode)
                ->where('is_purchased', 'Y')
                ->get()
                ->getRowArray();

            $purchasedArea = floatval($investment['purchased_area'] ?? 0);

            // ✅ Step 3: Enquiry area from tbl_land_enquiry
            $enquiry = $db->table('tbl_land_enquiry')
                ->selectSum('area_size', 'enquiry_area')
                ->where('land_code', $landCode)
                ->get()
                ->getRowArray();

            $enquiryArea = floatval($enquiry['enquiry_area'] ?? 0);

            // ✅ Step 4: Remaining area calculation
            $remainingArea = $totalArea - ($purchasedArea + $enquiryArea);
            if ($remainingArea < 0) {
                $remainingArea = 0;
            }

            // ✅ Step 5: Merge all tbl_land_quota data + computed values
            $finalData[] = array_merge($quota, [
                'purchased_area' => $purchasedArea,
                'enquiry_area'   => $enquiryArea,
                'remaining_area' => $remainingArea
            ]);
        }

        // ✅ Step 6: Return final response
        return $this->respond([
            'status'  => true,
            'message' => 'Land quota details retrieved successfully with all fields',
            'data'    => $finalData
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status'  => false,
            'message' => 'Error fetching land quota data',
            'error'   => $e->getMessage()
        ], 500);
    }
}


    // public function getById_land_quota()
    // {
    //     helper('jwtvalidate');
    //     $authHeader = $this->request->getHeaderLine('Authorization');
    //     $decodedToken = validatejwt($authHeader);
    //     if (!$decodedToken) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Invalid or missing JWT'
    //         ], 401);
    //     }
    //     $landCode = $this->request->getVar('land_code');
    //     if (empty($landCode)) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'land_code parameter is required'
    //         ], 400);
    //     }
    //     $db = \Config\Database::connect();
    //     $builder = $db->table('tbl_land_quota');
    //     $landQuota = $builder->where('land_code', $landCode)->where('is_active', 'Y')->get()->getRowArray();
    //     if (!$landQuota) {
    //         return $this->respond([
    //             'status' => false,
    //             'message' => 'Land quota not found'
    //         ], 404);
    //     }
    //     return $this->respond([
    //         'status' => true,
    //         'message' => 'Land quota retrieved successfully',
    //         'data' => $landQuota
    //     ], 200);
    // }
public function getById_land_quota()
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

    $landCode = $this->request->getVar('land_code');
    if (empty($landCode)) {
        return $this->respond([
            'status' => false,
            'message' => 'land_code parameter is required'
        ], 400);
    }

    $db = \Config\Database::connect();

    try {
        // ✅ Fetch main land quota record
        $landQuota = $db->table('tbl_land_quota')
            ->where('land_code', $landCode)
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$landQuota) {
            return $this->respond([
                'status' => false,
                'message' => 'Land quota not found'
            ], 404);
        }

        $totalArea = floatval($landQuota['area_size']);

        // ✅ Step 1: Get purchased area from tbl_investment_in_land
        $investment = $db->table('tbl_investment_in_land')
            ->selectSum('Purchase_Land_Area', 'purchased_area')
            ->where('land_number', $landCode)
            ->where('is_purchased', 'Y')
            ->get()
            ->getRowArray();

        $purchasedArea = floatval($investment['purchased_area'] ?? 0);

        // ✅ Step 2: Get enquired area from tbl_land_enquiry
        $enquiry = $db->table('tbl_land_enquiry')
            ->selectSum('area_size', 'enquiry_area')
            ->where('land_code', $landCode)
            ->get()
            ->getRowArray();

        $enquiryArea = floatval($enquiry['enquiry_area'] ?? 0);

        // ✅ Step 3: Calculate remaining area
        $remainingArea = $totalArea - ($purchasedArea + $enquiryArea);
        if ($remainingArea < 0) {
            $remainingArea = 0;
        }

        // ✅ Step 4: Add computed data to response
        $landQuota['purchased_area'] = $purchasedArea;
        $landQuota['enquiry_area'] = $enquiryArea;
        $landQuota['remaining_area'] = $remainingArea;

        return $this->respond([
            'status' => true,
            'message' => 'Land quota retrieved successfully',
            'data' => $landQuota
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => 'Error retrieving land quota details',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function update_land_quota()
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
                'message' => 'User code not found in token'
            ], 400);
        }
        $request = $this->request->getJSON(true);
        if (empty($request['land_code'])) {
            return $this->respond([
                'status' => false,
                'message' => "Field 'land_code' is required"
            ], 400);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_land_quota');
        $existing = $builder->where('land_code', $request['land_code'])->where('is_active', 'Y')->get()->getRowArray();
        if (!$existing) {
            return $this->respond([
                'status' => false,
                'message' => 'Land quota not found'
            ], 404);
        }
        $data = [
            'district'       => $request['district'] ?? $existing['district'],
            'area_size'      => $request['area_size'] ?? $existing['area_size'],
            'zone'           => $request['zone'] ?? $existing['zone'],
            'industry_type'  => $request['industry_type'] ?? $existing['industry_type'],
            'nearby'         => $request['nearby'] ?? $existing['nearby'],
            'address'        => $request['address'] ?? $existing['address'],
            'contact_details' => $request['contact_details'] ?? $existing['contact_details'],
            'remarks'        => $request['remarks'] ?? $existing['remarks'],
            // 'alloted'       => $request['alloted'] ?? $existing['alloted'],
            'Acre'        => $request['Acre'] ?? $existing['Acre'],
            'Gunta'        => $request['Gunta'] ?? $existing['Gunta'],
            'updated_by'     => $userCode,
            'updated_at'     => date('Y-m-d H:i:s')
        ];
        $updated = $builder->where('land_code', $request['land_code'])->update($data);
        if ($updated === false) {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to update land quota',
                'error' => $db->error()
            ], 500);
        }
        return $this->respond([
            'status' => true,
            'message' => 'Land quota updated successfully',
            'data' => $data
        ], 200);
    }

    public function getAll_alloted_land()
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
        $builder = $db->table('tbl_land_quota');
        $landQuotas = $builder->where('is_active', 'Y')->get()->getResultArray();
        return $this->respond([
            'status' => true,
            'message' => 'Land quotas retrieved successfully',
            'data' => $landQuotas
        ], 200);
    }

    public function addInvestmentInLand()
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
        $investment_code = 'IL' . date('YmdHis') . strtoupper(substr(uniqid(), -5));
        $data = [
            'name'              => trim($request['name']),
            'investment_code'   => $investment_code,
            'contact_details'   => trim($request['contact_details'] ?? ''),
            'uid'               => trim($request['uid']),
            'pan_no'            => trim($request['pan_no']),
            'address'           => trim($request['address'] ?? ''),
            'ref_contact_name'  => trim($request['ref_contact_name'] ?? ''),
            'ref_contact_no'    => trim($request['ref_contact_no'] ?? ''),
            'land_number'       => trim($request['land_number']),
            'Purchase_Land_Area' => trim($request['Purchase_Land'] ?? ''),
            'created_by'        => $createdBy,
            'created_at'        => date('Y-m-d H:i:s'),
            'is_active'         => 'Y',
        ];
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_investment_in_land');

        try {
            $inserted = $builder->insert($data);

            if ($inserted) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Investment in land added successfully',
                    'data' => $data
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to add investment in land'
                ], 500);
            }
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateInvestmentInLand()
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
        if (empty($request['investment_code'])) {
            return $this->respond(['status' => false, 'message' => 'investment_code is required'], 400);
        }
        $data = [
            'name'              => trim($request['name']),
            'contact_details'   => trim($request['contact_details'] ?? ''),
            'uid'               => trim($request['uid']),
            'pan_no'            => trim($request['pan_no']),
            'address'           => trim($request['address'] ?? ''),
            'ref_contact_name'  => trim($request['ref_contact_name'] ?? ''),
             'Purchase_Land_Area' => trim($request['Purchase_Land'] ?? ''),
            'ref_contact_no'    => trim($request['ref_contact_no'] ?? ''),
            'land_number'       => trim($request['land_number']),
            'updated_by'        => $updatedBy,
            'is_purchased'   => trim($request['is_purchased'] ?? ''),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_investment_in_land');

        try {
            $existing = $builder->where('investment_code', $request['investment_code'])->where('is_active', 'Y')->get()->getRowArray();
            if (!$existing) {
                return $this->respond(['status' => false, 'message' => 'Investment record not found'], 404);
            }
            $updated = $builder->where('investment_code', $request['investment_code'])->update($data);

            if ($updated === false) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to update investment in land'
                ], 500);
            } else {
                return $this->respond([
                    'status' => true,
                    'message' => 'Investment in land updated successfully',
                    'data' => $data
                ]);
            }
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllInvestmentInLand()
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
        $builder = $db->table('tbl_investment_in_land');
        $investments = $builder->where('is_active', 'Y')->get()->getResultArray();
        return $this->respond([
            'status' => true,
            'message' => 'Investments in land retrieved successfully',
            'data' => $investments
        ], 200);
    }

    public function getByIdInvestmentInLand()
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
        $investmentCode = $this->request->getVar('investment_code');
        if (empty($investmentCode)) {
            return $this->respond([
                'status' => false,
                'message' => 'investment_code parameter is required'
            ], 400);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_investment_in_land');
        $investment = $builder->where('investment_code', $investmentCode)->where('is_active', 'Y')->get()->getRowArray();
        if (!$investment) {
            return $this->respond([
                'status' => false,
                'message' => 'Investment record not found'
            ], 404);
        }
        return $this->respond([
            'status' => true,
            'message' => 'Investment in land retrieved successfully',
            'data' => $investment
        ], 200);
    }

    public function add_land_Enquiry()
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
        $land_Enquiry_code  = 'LE' . date('YmdHis') . strtoupper(substr(uniqid(), -5));
        $request = $this->request->getJSON(true) ?? [];
        $data = [
            'name'              => trim($request['name']),
            'land_enquiry_code' => $land_Enquiry_code,
            'contact_details'   => trim($request['contact_details']),
            'email'             => trim($request['email'] ?? ''),
            'uid'               => trim($request['uid'] ?? ''),
            'pan_no'            => trim($request['pan_no'] ?? ''),
            'ref_contact_name'  => trim($request['ref_contact_name'] ?? ''),
            'ref_contact_no'    => trim($request['ref_contact_no'] ?? ''),
            'address'           => trim($request['address'] ?? ''),
            'district'          => trim($request['district']),
            'required_land'   => trim($request['required_land'] ?? ''),
            'area_size'         => trim($request['area_size']),
            'land_code'        => trim($request['land_code'] ?? ''),
            'zone'              => trim($request['zone']),
            'type_of_industry'  => trim($request['type_of_industry']),
            'nearby'            => trim($request['nearby'] ?? ''),
            'alloted'           => trim($request['alloted'] ?? ''),
            'remarks'           => trim($request['remarks'] ?? ''),
            'created_by'        => $createdBy,
            'created_at'        => date('Y-m-d H:i:s'),
            'is_active'         => 'Y'
        ];
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_land_enquiry');
        try {
            $inserted = $builder->insert($data);
            if ($inserted) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Land enquiry added successfully',
                    'data' => $data
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to add land enquiry'
                ], 500);
            }
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update_land_Enquiry()
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
        if (empty($request['land_enquiry_code'])) {
            return $this->respond(['status' => false, 'message' => 'land_enquiry_code is required'], 400);
        }
        $data = [
            'name'              => trim($request['name']),
            'contact_details'   => trim($request['contact_details']),
            'email'             => trim($request['email'] ?? ''),
            'uid'               => trim($request['uid'] ?? ''),
            'pan_no'            => trim($request['pan_no'] ?? ''),
            'ref_contact_name'  => trim($request['ref_contact_name'] ?? ''),
            'ref_contact_no'    => trim($request['ref_contact_no'] ?? ''),
              'required_land'   => trim($request['required_land'] ?? ''),
            'address'           => trim($request['address'] ?? ''),
            'district'          => trim($request['district']),
            'land_code'        => trim($request['land_code'] ?? ''),
            'area_size'         => trim($request['area_size']),
            'zone'              => trim($request['zone']),
            'type_of_industry'  => trim($request['type_of_industry']),
            'nearby'            => trim($request['nearby'] ?? ''),
            'remarks'           => trim($request['remarks'] ?? ''),
             'alloted'           => trim($request['alloted'] ?? ''),
            'updated_by'        => $updatedBy,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_land_enquiry');
        try {
            $existing = $builder->where('land_enquiry_code', $request['land_enquiry_code'])->where('is_active', 'Y')->get()->getRowArray();
            if (!$existing) {
                return $this->respond(['status' => false, 'message' => 'Land enquiry record not found'], 404);
            }
            $updated = $builder->where('land_enquiry_code', $request['land_enquiry_code'])->update($data);
            if ($updated === false) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to update land enquiry'
                ], 500);
            } else {
                return $this->respond([
                    'status' => true,
                    'message' => 'Land enquiry updated successfully',
                    'data' => $data
                ]);
            }
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAll_land_Enquiry()
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
        $builder = $db->table('tbl_land_enquiry');
        $enquiries = $builder->where('is_active', 'Y')->get()->getResultArray();
        return $this->respond([
            'status' => true,
            'message' => 'Land enquiries retrieved successfully',
            'data' => $enquiries
        ], 200);
    }

    public function getById_land_Enquiry()
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
        $landEnquiryCode = $this->request->getVar('land_enquiry_code');
        if (empty($landEnquiryCode)) {
            return $this->respond([
                'status' => false,
                'message' => 'land_enquiry_code parameter is required'
            ], 400);
        }
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_land_enquiry');
        $enquiry = $builder->where('land_enquiry_code', $landEnquiryCode)->where('is_active', 'Y')->get()->getRowArray();
        if (!$enquiry) {
            return $this->respond([
                'status' => false,
                'message' => 'Land enquiry not found'
            ], 404);
        }
        return $this->respond([
            'status' => true,
            'message' => 'Land enquiry retrieved successfully',
            'data' => $enquiry
        ], 200);
    }
}
