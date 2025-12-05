<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use Exception;

helper('jwtvalidate');

/**
 * @method \CodeIgniter\HTTP\ResponseInterface failValidationError(string $message, string $errorCode = '', array $data = [])
 * @method \CodeIgniter\HTTP\ResponseInterface failUnauthorized(string $message, string $errorCode = '', array $data = [])
 * @method \CodeIgniter\HTTP\ResponseInterface failNotFound(string $message, string $errorCode = '', array $data = [])
 * @method \CodeIgniter\HTTP\ResponseInterface failServerError(string $message, string $errorCode = '', array $data = [])
 * @method \CodeIgniter\HTTP\ResponseInterface respondCreated($response, string $message = '')
 * @method \CodeIgniter\HTTP\ResponseInterface respondUpdated($response, string $message = '')
 * @method \CodeIgniter\HTTP\ResponseInterface respond($response, int $status = 200, string $message = '')
 */
class QueriesComplaintsController extends Controller
{
    use ResponseTrait;

    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    private function _convertIsActive($value)
    {
        if (is_bool($value) && $value === true || $value === 1 || strtolower($value) === 'y') {
            return 'Y';
        }
        return 'N';
    }

    public function add()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->failUnauthorized('Invalid or missing JWT');
        }

        $data = $this->request->getJSON(true);
        $required = ['type', 'title'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->failValidationError("Missing required field: $field");
            }
        }

        try {
            $insertData = [
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'Pending',
                'is_active' => $this->_convertIsActive($data['is_active'] ?? 'Y'),
                'created_by' => $decodedToken->user_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->table('tbl_queries_complaints')->insert($insertData);
            $newId = $this->db->insertID();

            return $this->respondCreated([
                'status' => true,
                'message' => 'Record added successfully.',
                'id' => $newId
            ]);
        } catch (Exception $e) {
            return $this->failServerError('An error occurred: ' . $e->getMessage());
        }
    }

    public function update()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->failUnauthorized('Invalid or missing JWT');
        }

        $data = $this->request->getJSON(true);
        $id = $data['id'] ?? null;
        if (empty($id)) {
            return $this->failValidationError('Missing required field: id');
        }

        try {
            if (!$this->db->table('tbl_queries_complaints')->where('id', $id)->countAllResults()) {
                return $this->failNotFound('Record not found.');
            }

            unset($data['id']);
            if (isset($data['is_active'])) {
                $data['is_active'] = $this->_convertIsActive($data['is_active']);
            }
            $data['updated_by'] = $decodedToken->user_id; // Corrected from user_code to user_id
            $data['updated_at'] = date('Y-m-d H:i:s');

            $this->db->table('tbl_queries_complaints')->where('id', $id)->update($data);

            return $this->respondUpdated([
                'status' => true,
                'message' => 'Record updated successfully.'
            ]);
        } catch (Exception $e) {
            return $this->failServerError('An error occurred: ' . $e->getMessage());
        }
    }

    public function getById()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->failUnauthorized('Invalid or missing JWT');
        }

        $data = $this->request->getJSON(true);
        $id = $data['id'] ?? null;
        if (empty($id)) {
            return $this->failValidationError('Missing required field: id');
        }

        try {
            $query = $this->db->table('tbl_queries_complaints as qc')
                                ->select('qc.*,
                                        CONCAT(u_created.First_Name, " ", u_created.Last_Name) as created_by_name,
                                        CONCAT(u_updated.First_Name, " ", u_updated.Last_Name) as updated_by_name')
                                ->join('tbl_register u_created', 'u_created.user_code = qc.created_by', 'left')
                                ->join('tbl_register u_updated', 'u_updated.user_code = qc.updated_by', 'left')
                                ->where('qc.id', $id);

            $record = $query->get()->getRow();

            if (!$record) {
                return $this->failNotFound('Record not found.');
            }

            return $this->respond([
                'status' => true,
                'message' => 'Record retrieved successfully.',
                'data' => $record
            ]);
        } catch (Exception $e) {
            return $this->failServerError('An error occurred: ' . $e->getMessage());
        }
    }

    public function getByType()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->failUnauthorized('Invalid or missing JWT');
        }

        $data = $this->request->getJSON(true);
        $type = $data['type'] ?? null;
        if (empty($type)) {
            return $this->failValidationError('Missing required field: type');
        }

        try {
            $query = $this->db->table('tbl_queries_complaints as qc')
                                ->select('qc.*,
                                        CONCAT(u_created.First_Name, " ", u_created.Last_Name) as created_by_name,
                                        CONCAT(u_updated.First_Name, " ", u_updated.Last_Name) as updated_by_name')
                                ->join('tbl_register u_created', 'u_created.user_code = qc.created_by', 'left')
                                ->join('tbl_register u_updated', 'u_updated.user_code = qc.updated_by', 'left')
                                ->where('qc.type', $type)
                                ->orderBy('qc.created_at', 'DESC');

            $records = $query->get()->getResult();

            if (empty($records)) {
                return $this->failNotFound('No records found for this type.');
            }

            return $this->respond([
                'status' => true,
                'message' => 'Records retrieved successfully.',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return $this->failServerError('An error occurred: ' . $e->getMessage());
        }
    }

    public function getAll()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->failUnauthorized('Invalid or missing JWT');
        }

        try {
            $query = $this->db->table('tbl_queries_complaints as qc')
                                ->select('qc.*,
                                        CONCAT(u_created.First_Name, " ", u_created.Last_Name) as created_by_name,
                                        CONCAT(u_updated.First_Name, " ", u_updated.Last_Name) as updated_by_name')
                                ->join('tbl_register u_created', 'u_created.user_code = qc.created_by', 'left')
                                ->join('tbl_register u_updated', 'u_updated.user_code = qc.updated_by', 'left')
                                ->orderBy('qc.created_at', 'DESC');

            $records = $query->get()->getResult();

            if (empty($records)) {
                return $this->failNotFound('No records found.');
            }

            return $this->respond([
                'status' => true,
                'message' => 'All records retrieved successfully.',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return $this->failServerError('An error occurred: ' . $e->getMessage());
        }
    }

  public function getbyusercode()
{
    $authHeader = $this->request->getHeaderLine('Authorization');
    $decodedToken = validatejwt($authHeader);
    if (!$decodedToken) {
        return $this->failUnauthorized('Invalid or missing JWT');
    }

    $data = $this->request->getJSON(true);
    $user_code = $decodedToken->user_id;
    if (empty($user_code)) {
        return $this->failValidationError('Missing required field: user_code');
    }

    try {
        $query = $this->db->table('tbl_queries_complaints as qc')
            ->select('qc.*,
                    CONCAT(u_created.First_Name, " ", u_created.Last_Name) as created_by_name,
                    CONCAT(u_updated.First_Name, " ", u_updated.Last_Name) as updated_by_name')
            ->join('tbl_register u_created', 'u_created.user_code = qc.created_by', 'left')
            ->join('tbl_register u_updated', 'u_updated.user_code = qc.updated_by', 'left')
            ->where('qc.created_by', $user_code)
            ->orderBy('qc.created_at', 'DESC');

        $records = $query->get()->getResult();

        if (empty($records)) {
            return $this->failNotFound('No records found for this user.');
        }

        return $this->respond([
            'status' => true,
            'message' => 'Records retrieved successfully for the user.',
            'count' => count($records),   // ✅ Added count
            'data' => $records
        ]);
    } catch (Exception $e) {
        return $this->failServerError('An error occurred: ' . $e->getMessage());
    }
}

}
