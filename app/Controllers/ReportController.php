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
class ReportController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    use ResponseTrait;


    public function getemployeealldatareport()
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
}