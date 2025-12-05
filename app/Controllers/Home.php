<?php

namespace App\Controllers;
use CodeIgniter\API\ResponseTrait;
use Config\App;
use DateTime;
require_once ROOTPATH . 'public/JWT/src/JWT.php';
use Config\Database;
use CodeIgniter\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;
use Config\Services;
// helper('email_helper');
use App\Models\HomeModel; 
// require_once FCPATH . 'vendor/autoload.php';
class Home extends BaseController
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
        $this->db = \Config\Database::connect();
        $this->uri = service('uri');
        if ($this->homeModel === null) {
            log_message('error', 'HomeModel could not be instantiated.');
        }
        

    }
    public function optionsMethod()
    {
        return $this->response->setStatusCode(200)
                              ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                              ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
                              ->setHeader('Access-Control-Max-Age', '86400'); 
    }
    public function index()
    {
        try {
            $db = \Config\Database::connect();
            if ($db->connect()) {
                return $this->response->setJSON(['status' => 'success', 'message' => 'Database connection established successfully.']);
            }
        } catch (DatabaseException $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to connect to the database: ' . $e->getMessage()]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'Unable to establish database connection.']);

        // return view('welcome_message');
    }


}
