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
use Helper\jwtvalidate;

require_once ROOTPATH . 'public/JWT/src/JWT.php';
class CompanyController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->homeModel = new HomeModel();
        helper(['jwtvalidate']);
    }

    public function add_company()
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
        $created_by = $decodedToken->user_id ?? null;
        $company_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 5);
        $input = $this->request->getPost();
        $logoFile = $this->request->getFile('logo');
        $logoName = null;
        if ($logoFile && $logoFile->isValid() && !$logoFile->hasMoved()) {
            $logoName = $company_code . '_' . $logoFile->getRandomName();
            $logoFile->move(ROOTPATH . 'companylogo', $logoName);
        }
        $data = [
            'company_code'    => $company_code,
            'company_name'    => $input['company_name'] ?? '',
            'address'         => $input['address'] ?? '',
            'email'           => $input['email'] ?? '',
            'contact_number'  => $input['contact_number'] ?? '',
            'website'         => $input['website'] ?? '',
            'pf_deduction'    => $input['pf_deduction'] ?? 0,
            'gst_number'      => $input['gst_number'] ?? '',
            'gst'             => $input['gst'] ?? '',
            'logo'            => $logoName,
            'is_active'       => 'Y',
            'created_by'      => $created_by
        ];

        $db = \Config\Database::connect();
        if ($db->table('tbl_company')->insert($data)) {
            return $this->respond([
                'status'        => true,
                'message'       => 'Company added successfully',
                'company_code'  => $company_code
            ], 201);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to add company'
            ], 500);
        }
    }

    public function get_company()
    {
        helper(['jwtvalidate', 'url']);

        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing JWT'
            ], 401);
        }

        $request = $this->request->getJSON(true);
        $company_code = $request['company_code'] ?? null;

        if (!$company_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Company code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $company = $db->table('tbl_company')
            ->where('company_code', $company_code)
            ->get()
            ->getRowArray();

        if ($company) {
            if (!empty($company['logo'])) {
                $baseUrl = base_url('companylogo');
                $company['logo'] = $baseUrl . '/' . $company['logo'];
            }

            return $this->respond([
                'status' => true,
                'data'   => $company
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Company not found'
            ], 404);
        }
    }


    public function serveCompanyLogo($fileName)
    {
        $filePath = FCPATH . 'companylogo/' . $fileName;

        if (file_exists($filePath)) {
            return $this->response
                ->setHeader('Content-Type', mime_content_type($filePath))
                ->setBody(file_get_contents($filePath));
        }

        return $this->response->setStatusCode(404)->setBody('File not found');
    }

    public function  getallcompany()
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
        $companies = $db->table('tbl_company')
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();
        if ($companies) {
            foreach ($companies as &$company) {
                if (!empty($company['logo'])) {
                    $company['logo'] = base_url('companylogo/' . $company['logo']);
                }
            }
            return $this->respond([
                'status' => true,
                'data'   => $companies
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No active companies found'
            ], 404);
        }
    }

    public function editcompany()
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

        $updated_by = $decodedToken->user_id ?? null;
        $input = $this->request->getPost();
        $company_code = $input['company_code'] ?? null;

        if (!$company_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Company code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $data = [];

        if (isset($input['company_name']))    $data['company_name'] = $input['company_name'];
        if (isset($input['address']))         $data['address'] = $input['address'];
        if (isset($input['email']))           $data['email'] = $input['email'];
        if (isset($input['contact_number']))  $data['contact_number'] = $input['contact_number'];
        if (isset($input['website']))         $data['website'] = $input['website'];
        if (isset($input['gst_number']))      $data['gst_number'] = $input['gst_number'];
        if (isset($input['gst']))             $data['gst'] = $input['gst'];

        // 🔽 Handle logo upload
        $logoFile = $this->request->getFile('logo');
        if ($logoFile && $logoFile->isValid() && !$logoFile->hasMoved()) {
            $logoName = $company_code . '_' . $logoFile->getRandomName();
            $logoFile->move(ROOTPATH . 'companylogo', $logoName);
            $data['logo'] = $logoName;
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($data)) {
            return $this->respond([
                'status' => false,
                'message' => 'No fields provided for update.'
            ], 400);
        }

        $company = $db->table('tbl_company')->where('company_code', $company_code)->get()->getRow();
        if (!$company) {
            return $this->respond([
                'status' => false,
                'message' => 'Company not found.'
            ], 404);
        }

        if ($db->table('tbl_company')->where('company_code', $company_code)->update($data)) {
            return $this->respond([
                'status'  => true,
                'message' => 'Company updated successfully'
            ]);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to update company'
            ], 500);
        }
    }
}
