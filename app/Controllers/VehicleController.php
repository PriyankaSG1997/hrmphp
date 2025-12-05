<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use App\Models\HomeModel;
use DateTime;

class VehicleController extends BaseController
{
    use ResponseTrait;

    protected $key = 'HS256';
    protected $homeModel;

    private function handleUpload(string $fileKey): ?string
    {
        $file = $this->request->getFile($fileKey);
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $uploadPath = FCPATH . 'uploads/vehicles/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            $file->move($uploadPath, $newName);
            return 'uploads/vehicles/' . $newName;
        }
        return null;
    }
    private function handlekminfoUpload(string $fileKey): ?string
    {
        $file = $this->request->getFile($fileKey);
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $uploadPath = FCPATH . 'uploads/vehicles/beforetravle/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            $file->move($uploadPath, $newName);
            return 'uploads/vehicles/beforetravle/' . $newName;
        }
        return null;
    }
    public function add_vehicle()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;

        $rules = [
            'vehicle_number'        => 'required',
            'vehicle_type'          => 'required',
            'insurance_start_date'  => 'required|valid_date[Y-m-d]',
            'insurance_expiry_date' => 'required|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $this->validator->getErrors()
            ], 400);
        }

        // Handle optional uploads
        $rcImagePath = null;
        $insuranceCopyPath = null;
        $PUCCopyPath = null;

        if ($file = $this->request->getFile('rc_image')) {
            if ($file->isValid() && !$file->hasMoved()) {
                $rcImagePath = $this->handleUpload('rc_image');
            }
        }

        if ($file = $this->request->getFile('insurense_copy')) {
            if ($file->isValid() && !$file->hasMoved()) {
                $insuranceCopyPath = $this->handleUpload('insurense_copy');
            }
        }

        if ($file = $this->request->getFile('PUC')) {
            if ($file->isValid() && !$file->hasMoved()) {
                $PUCCopyPath = $this->handleUpload('PUC');
            }
        }

        $vehicle_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);

        $data = [
            'vehicle_code'          => $vehicle_code,
            'vehicle_number'        => $this->request->getPost('vehicle_number'),
            'vehicle_type'          => $this->request->getPost('vehicle_type'),
            'vehicle_model'         => $this->request->getPost('vehicle_model'),
            'rc_image'              => $rcImagePath,
            'PUC'                   => $PUCCopyPath,
            'insurense_copy'        => $insuranceCopyPath,
            'insurance_start_date'  => $this->request->getPost('insurance_start_date'),
            'insurance_expiry_date' => $this->request->getPost('insurance_expiry_date'),
            'is_active'             => 'Y',
            'created_by'            => $created_by
        ];

        $db = \Config\Database::connect();
        try {
            $db->table('tbl_vehicle_mst')->insert($data);
            return $this->respond([
                'status' => true,
                'message' => 'Vehicle added successfully',
                'vehicle_code' => $vehicle_code
            ], 201);
        } catch (DatabaseException $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function update_vehicle()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $updated_by = $decodedToken->user_id ?? null;
        $vehicle_code = $this->request->getPost('vehicle_code');
        if (empty($vehicle_code)) {
            return $this->respond(['status' => false, 'message' => 'Vehicle code is required'], 400);
        }

        $data = [];

        $inputFields = [
            'vehicle_number',
            'vehicle_type',
            'vehicle_model',
            'is_active',
            'insurance_start_date',
            'insurance_expiry_date'
        ];

        foreach ($inputFields as $field) {
            $value = $this->request->getPost($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        if ($this->request->getFile('rc_image') && $this->request->getFile('rc_image')->isValid()) {
            $data['rc_image'] = $this->handleUpload('rc_image');
        }
        if ($this->request->getFile('insurense_copy') && $this->request->getFile('insurense_copy')->isValid()) {
            $data['insurense_copy'] = $this->handleUpload('insurense_copy');
        }
        if ($this->request->getFile('PUC') && $this->request->getFile('PUC')->isValid()) {
            $data['PUC'] = $this->handleUpload('PUC');
        }

        if (empty($data)) {
            return $this->respond(['status' => false, 'message' => 'No data provided to update'], 400);
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $db = \Config\Database::connect();
        try {
            $builder = $db->table('tbl_vehicle_mst')->where('vehicle_code', $vehicle_code)->update($data);
            if ($db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Vehicle updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Vehicle not found or no change'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }


    public function get_active_vehicles()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $baseUrl = base_url(); // http://192.168.0.171/SDKCPL_PHP/

        $db = \Config\Database::connect();
        $vehicles = $db->table('tbl_vehicle_mst')->where('is_active', 'Y')->get()->getResultArray();

        foreach ($vehicles as &$vehicle) {
            if (!empty($vehicle['rc_image'])) {
                $vehicle['rc_image'] = $baseUrl . $vehicle['rc_image'];
            }
            if (!empty($vehicle['insurense_copy'])) {
                $vehicle['insurense_copy'] = $baseUrl . $vehicle['insurense_copy'];
            }
            if (!empty($vehicle['PUC'])) {
                $vehicle['PUC'] = $baseUrl . $vehicle['PUC'];
            }
        }

        return $this->respond(['status' => true, 'data' => $vehicles], 200);
    }


    public function get_vehicles_from_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $request = $this->request->getJSON(true);
        $vehicle_id = $request['vehicle_id'] ?? null;

        if (!$vehicle_id) {
            return $this->respond(['status' => false, 'message' => 'Vehicle ID is required'], 400);
        }

        $db = \Config\Database::connect();
        $vehicle = $db->table('tbl_vehicle_mst')->where('vehicle_code', $vehicle_id)->get()->getRowArray();

        if (!$vehicle) {
            return $this->respond(['status' => false, 'message' => 'Vehicle not found'], 404);
        }

        $baseUrl = base_url();

        if (!empty($vehicle['rc_image'])) {
            $vehicle['rc_image'] = $baseUrl . $vehicle['rc_image'];
        }
        if (!empty($vehicle['insurense_copy'])) {
            $vehicle['insurense_copy'] = $baseUrl . $vehicle['insurense_copy'];
        }
        if (!empty($vehicle['PUC'])) {
            $vehicle['PUC'] = $baseUrl . $vehicle['PUC'];
        }

        return $this->respond(['status' => true, 'data' => $vehicle], 200);
    }


    public function delete_vehicle()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $deleted_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);
        $vehicle_code = $input['vehicle_code'] ?? null;

        if (!$vehicle_code) {
            return $this->respond(['status' => false, 'message' => 'Vehicle code is required'], 400);
        }

        $db = \Config\Database::connect();
        try {
            $db->table('tbl_vehicle_mst')->where('vehicle_code', $vehicle_code)->update([
                'is_active'  => 'N'
                // 'deleted_by' => $deleted_by
            ]);

            if ($db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Vehicle deactivated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Vehicle not found'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAllVehicles()
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

        $baseUrl = base_url(); // returns http://192.168.0.171/SDKCPL_PHP/
        $db = \Config\Database::connect();
        $vehicles = $db->table('tbl_vehicle_mst')->get()->getResultArray();

        foreach ($vehicles as &$vehicle) {
            if (!empty($vehicle['rc_image'])) {
                $vehicle['rc_image'] = $baseUrl . $vehicle['rc_image'];
            }
            if (!empty($vehicle['insurense_copy'])) {
                $vehicle['insurense_copy'] = $baseUrl . $vehicle['insurense_copy'];
            }
            if (!empty($vehicle['PUC'])) {
                $vehicle['PUC'] = $baseUrl . $vehicle['PUC'];
            }
        }

        return $this->respond([
            'status' => true,
            'data'   => $vehicles
        ], 200);
    }

    public function uplodevehicleinfobeforetravale()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $kmimage = $this->handlekminfoUpload('km_info');
        $vehicle_reqest_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);

        $data = [
            'vehicle_info_code'   => $vehicle_reqest_code,
            'vehicle_number' => $this->request->getPost('vehicle_number'),
            'employee_name'  => $this->request->getPost('employee_name'),
            'start_km'       => $this->request->getPost('start_km'),
            'request_code'   => $this->request->getPost('request_code'),
            'end_km'       => $this->request->getPost('end_km'),
            'km_image'       => $kmimage,
            'is_active'      => 'Y',
            'created_by'     => $created_by
        ];

        $db = \Config\Database::connect();
        try {
            $db->table('tbl_vehicle_info_before_travale')->insert($data);
            return $this->respond(['status' => true, 'message' => 'data inserted successfully'], 201);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }
    public function updateVehicleInfoBeforeTravale()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $updated_by = $decodedToken->user_id ?? null;
        $kmimage = $this->handlekminfoUpload('km_info');
        $vehicle_info_code = $this->request->getPost('vehicle_info_code');
        $updateData = [
            'vehicle_number' => $this->request->getPost('vehicle_number'),
            'employee_name'  => $this->request->getPost('employee_name'),
            'start_km'       => $this->request->getPost('start_km'),
            'end_km'         => $this->request->getPost('end_km'),
            'is_active'      => $this->request->getPost('is_active') ?? 'Y',
            'updated_by'     => $updated_by,
        ];
        if ($kmimage) {
            $updateData['km_image'] = $kmimage;
        }
        $db = \Config\Database::connect();
        try {
            $builder = $db->table('tbl_vehicle_info_before_travale');
            $builder->where('vehicle_info_code', $vehicle_info_code);
            $updated = $builder->update($updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Data updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'No changes or record not found'], 404);
            }
        } catch (\Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAllListOfInfoVehicale()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_vehicle_info_before_travale');

        $data = $builder->get()->getResultArray();

        if ($data) {
            return $this->respond(['status' => true, 'data' => $data], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'No records found'], 404);
        }
    }
    public function getVehicleInfoByUserCode()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        $userCode = $decodedToken->user_id ?? null;
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_vehicle_info_before_travale');
        $builder->where('created_by', $userCode);

        $data = $builder->get()->getResultArray();

        if ($data) {
            return $this->respond(['status' => true, 'data' => $data], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'No records found for this user'], 404);
        }
    }

    public function request_vehical()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $request_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $dataInput = $this->request->getJSON(true);

        $db = \Config\Database::connect();

        // Find an available vehicle (active and not currently assigned)
        $subQuery = $db->table('tbl_vehicle_requests')
            ->select('vehicle_code')
            ->where('v_release', 'N')
            ->where('vehicle_code IS NOT NULL', null, false)
            ->getCompiledSelect();

        $availableVehicle = $db->table('tbl_vehicle_mst')
            ->where('is_active', 'Y')
            ->where("vehicle_code NOT IN ($subQuery)", null, false)
            ->limit(1)
            ->get()
            ->getRowArray();

        $vehicle_code = $availableVehicle['vehicle_code'] ?? null;
        $approval_status = $vehicle_code ? 'Approved' : 'Pending';

        $data = [
            'request_code'     => $request_code,
            'user_ref_code'    => $dataInput['user_ref_code'] ?? $created_by,
            'trip_start_date'  => $dataInput['trip_start_date'] ?? null,
            'trip_end_date'    => $dataInput['trip_end_date'] ?? null,
            'trip_start_time'  => $dataInput['trip_start_time'] ?? null,
            'trip_end_time'    => $dataInput['trip_end_time'] ?? null,
            'pickup_location'  => $dataInput['pickup_location'] ?? null,
            'drop_location'    => $dataInput['drop_location'] ?? null,
            'no_of_passengers' => $dataInput['no_of_passengers'] ?? null,
            'reason'           => $dataInput['reason'] ?? null,
            'vehicle_code'     => $vehicle_code,
            'approval_status'  => $approval_status,
            'is_active'        => 'Y',
            'created_by'       => $created_by
        ];

        try {
            $db->table('tbl_vehicle_requests')->insert($data);

            // If vehicle assigned, fetch its details
            $vehicleDetails = null;
            if ($vehicle_code) {
                $vehicleDetails = $db->table('tbl_vehicle_mst')
                    ->select('vehicle_code, vehicle_number, vehicle_type, vehicle_model, rc_image, PUC, insurense_copy')
                    ->where('vehicle_code', $vehicle_code)
                    ->get()
                    ->getRowArray();

                // Add full URL for images
                $baseUrl = base_url();
                if ($vehicleDetails) {
                    if (!empty($vehicleDetails['rc_image'])) {
                        $vehicleDetails['rc_image'] = $baseUrl . $vehicleDetails['rc_image'];
                    }
                    if (!empty($vehicleDetails['insurense_copy'])) {
                        $vehicleDetails['insurense_copy'] = $baseUrl . $vehicleDetails['insurense_copy'];
                    }
                    if (!empty($vehicleDetails['PUC'])) {
                        $vehicleDetails['PUC'] = $baseUrl . $vehicleDetails['PUC'];
                    }
                }
            }

            return $this->respond([
                'status'        => true,
                'message'       => $vehicle_code ? 'Vehicle request approved and allocated automatically' : 'Vehicle request submitted (pending allocation)',
                'request_code'  => $request_code,
                'vehicle_code'  => $vehicle_code,
                'vehicle_data'  => $vehicleDetails
            ], 201);
        } catch (\Exception $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    public function getAllVehicleRequests()
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
        $builder = $db->table('tbl_vehicle_requests');
        $requests = $builder->orderBy('created_at', 'DESC')->get()->getResultArray();

        if (empty($requests)) {
            return $this->respond([
                'status' => true,
                'message' => 'No vehicle requests found',
                'data'   => []
            ], 200);
        }

        $baseUrl = base_url();

        // Attach vehicle_info_before_travale and vehicle details for each request
        foreach ($requests as &$req) {
            // Attach vehicle_info_before_travale
            $infoData = $db->table('tbl_vehicle_info_before_travale')
                ->where('request_code', $req['request_code'])
                ->get()
                ->getResultArray();
            $req['vehicle_info_before_travale'] = $infoData ?? [];

            // Attach vehicle details from tbl_vehicle_mst
            if (!empty($req['vehicle_code'])) {
                $vehicle = $db->table('tbl_vehicle_mst')
                    ->select('vehicle_code, vehicle_number, vehicle_type, vehicle_model, rc_image, PUC, insurense_copy')
                    ->where('vehicle_code', $req['vehicle_code'])
                    ->get()
                    ->getRowArray();

                if ($vehicle) {
                    // Full URLs for images
                    if (!empty($vehicle['rc_image'])) $vehicle['rc_image'] = $baseUrl . $vehicle['rc_image'];
                    if (!empty($vehicle['PUC'])) $vehicle['PUC'] = $baseUrl . $vehicle['PUC'];
                    if (!empty($vehicle['insurense_copy'])) $vehicle['insurense_copy'] = $baseUrl . $vehicle['insurense_copy'];

                    $req['vehicle_data'] = $vehicle;
                } else {
                    $req['vehicle_data'] = null;
                }
            } else {
                $req['vehicle_data'] = null;
            }
        }

        return $this->respond([
            'status' => true,
            'message' => 'Vehicle requests fetched successfully',
            'data'   => $requests
        ], 200);
    }



    public function approve_vehical_request()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $dataInput = $this->request->getJSON(true);

        $request_code = $dataInput['request_code'] ?? null;
        $vehicle_code = $dataInput['vehicle_code'] ?? null;
        $approval_status = $dataInput['approval_status'] ?? null;

        if (!$request_code || !$approval_status) {
            return $this->respond(['status' => false, 'message' => 'request_code and approval_status are required'], 400);
        }

        $db = \Config\Database::connect();
        try {
            $builder = $db->table('tbl_vehicle_requests');
            $builder->where('request_code', $request_code)
                ->update([
                    'vehicle_code'    => $vehicle_code,
                    'approval_status' => $approval_status,
                    'updated_by'      => $created_by,
                    'updated_at'      => date('Y-m-d H:i:s')
                ]);

            return $this->respond([
                'status'  => true,
                'message' => 'Vehicle request updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status'  => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function get_vehicles_request_foruser()
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

        $db = \Config\Database::connect();

        try {
            $builder = $db->table('tbl_vehicle_requests');
            $builder->where('user_ref_code', $userCode);
            $builder->orderBy('created_at', 'DESC');
            $requests = $builder->get()->getResultArray();

            $baseUrl = base_url();

            // Loop through requests to attach vehicle details if available
            foreach ($requests as &$request) {
                if (!empty($request['vehicle_code'])) {
                    $vehicle = $db->table('tbl_vehicle_mst')
                        ->select('vehicle_code, vehicle_number, vehicle_type, vehicle_model, rc_image, PUC, insurense_copy')
                        ->where('vehicle_code', $request['vehicle_code'])
                        ->get()
                        ->getRowArray();

                    if ($vehicle) {
                        // Add full URL for images
                        if (!empty($vehicle['rc_image'])) $vehicle['rc_image'] = $baseUrl . $vehicle['rc_image'];
                        if (!empty($vehicle['PUC'])) $vehicle['PUC'] = $baseUrl . $vehicle['PUC'];
                        if (!empty($vehicle['insurense_copy'])) $vehicle['insurense_copy'] = $baseUrl . $vehicle['insurense_copy'];

                        $request['vehicle_data'] = $vehicle;
                    } else {
                        $request['vehicle_data'] = null;
                    }
                } else {
                    $request['vehicle_data'] = null;
                }
            }

            return $this->respond([
                'status' => true,
                'message' => 'Vehicle requests fetched successfully',
                'data' => $requests
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function releasevehical()
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

        $dataInput = $this->request->getJSON(true);
        $requestCode = $dataInput['request_code'] ?? null;

        if (!$requestCode) {
            return $this->respond([
                'status' => false,
                'message' => 'request_code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tbl_vehicle_requests');

        try {
            $updated = $builder->where('request_code', $requestCode)
                ->update(['v_release' => 'Y']);

            if ($db->affectedRows() > 0) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Vehicle released successfully'
                ], 200);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'No record found for given request_code'
                ], 404);
            }
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Database error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function getactiveVhehicles()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();

        $hasPendingRequests = $db->table('tbl_vehicle_requests')
            ->where('v_release', 'N')
            ->where('vehicle_code IS NOT NULL', null, false)
            ->limit(1)
            ->countAllResults() > 0;

        $builder = $db->table('tbl_vehicle_mst vm');
        $builder->select('vm.*');
        $builder->where('vm.is_active', 'Y');

        if ($hasPendingRequests) {
            $subQuery = $db->table('tbl_vehicle_requests vr')
                ->select('vr.vehicle_code')
                ->where('vr.v_release', 'N')
                ->where('vr.vehicle_code IS NOT NULL', null, false)
                ->getCompiledSelect();

            $builder->where("vm.vehicle_code NOT IN ($subQuery)", null, false);
        }

        $vehicles = $builder->get()->getResultArray();

        $baseUrl = base_url();
        foreach ($vehicles as &$vehicle) {
            if (!empty($vehicle['rc_image'])) {
                $vehicle['rc_image'] = $baseUrl . $vehicle['rc_image'];
            }
            if (!empty($vehicle['insurense_copy'])) {
                $vehicle['insurense_copy'] = $baseUrl . $vehicle['insurense_copy'];
            }
            if (!empty($vehicle['PUC'])) {
                $vehicle['PUC'] = $baseUrl . $vehicle['PUC'];
            }
        }

        return $this->respond([
            'status' => true,
            'data'   => $vehicles
        ], 200);
    }
    public function reminder_of_insurance_expiry()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $today = date('Y-m-d');
        $next30 = date('Y-m-d', strtotime('+30 days'));

        $vehicles = $db->table('tbl_vehicle_mst')
            ->select('vehicle_code, vehicle_number, vehicle_type, insurance_expiry_date, vehicle_model, created_by')
            ->where('is_active', 'Y')
            ->where('insurance_expiry_date IS NOT NULL')
            ->where('STR_TO_DATE(insurance_expiry_date, "%Y-%m-%d") >=', $today)
            ->where('STR_TO_DATE(insurance_expiry_date, "%Y-%m-%d") <=', $next30)
            ->get()
            ->getResultArray();

        if (empty($vehicles)) {
            return $this->respond([
                'status'  => false,
                'message' => 'No insurance expiry reminders found for the next 30 days'
            ], 404);
        }

        foreach ($vehicles as &$vehicle) {
            $expiryDate = new \DateTime($vehicle['insurance_expiry_date']);
            $todayDate = new \DateTime($today);
            $interval = $todayDate->diff($expiryDate);
            $daysLeft = (int)$interval->format('%r%a');
            $vehicle['days_left'] = $daysLeft;
            $vehicle['days_left_text'] = $daysLeft >= 0
                ? "Expires in $daysLeft days"
                : "Expired " . abs($daysLeft) . " days ago";
        }

        return $this->respond([
            'status'  => true,
            'message' => 'Insurance expiry reminders fetched successfully',
            'data'    => $vehicles
        ], 200);
    }
}
