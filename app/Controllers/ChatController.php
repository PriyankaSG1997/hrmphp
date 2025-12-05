<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use DateTime;
use DateTimeZone;
use Exception;
use Config\Database;

class ChatController extends ResourceController
{
    use ResponseTrait;

    protected $db;

    public function __construct()
    {
        helper(['jwtvalidate']);
        $this->db = Database::connect();
    }

    public function sendMessage()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $from_user = $decoded->user_id ?? null;
        $to_user   = $this->request->getPost('to_user_ref_code');
        $message   = $this->request->getPost('message');
        $file_type = $this->request->getPost('file_type');
        $file_path = null;

        if (!$to_user) {
            return $this->respond(['status' => false, 'message' => 'to_user_ref_code is required'], 400);
        }

        // Handle file upload (optional)
        $file = $this->request->getFile('file');
        if ($file && $file->isValid()) {
            $newName = $file->getRandomName();
            $file->move(ROOTPATH . 'public/uploads/chat/', $newName);
            $file_path = '/uploads/chat/' . $newName;
        }

        try {
            $builder = $this->db->table('tbl_user_chat_messages');
            $builder->insert([
                'from_user_ref_code' => $from_user,
                'to_user_ref_code'   => $to_user,
                'message'            => $message,
                'file_path'          => $file_path,
                'file_type'          => $file_type,
                'is_read'            => 'N',
                'sent_at'            => (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s')
            ]);
            return $this->respond(['status' => true, 'message' => 'Message sent successfully.'], 200);
        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getChatHistory()
    {
        // Validate JWT Token
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader); // Your JWT validator

        if (!$decoded) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or missing token'
            ], 401);
        }

        $from_user = $decoded->user_id ?? null;
        $to_user = $this->request->getGet('to_user_ref_code');

        if (!$to_user) {
            return $this->respond([
                'status' => false,
                'message' => 'to_user_ref_code is required'
            ], 400);
        }

        try {
            $builder = $this->db->table('tbl_user_chat_messages');

            $builder->where("
                (from_user_ref_code = '{$from_user}' AND to_user_ref_code = '{$to_user}') 
                OR 
                (from_user_ref_code = '{$to_user}' AND to_user_ref_code = '{$from_user}')
            ", null, false);

            $builder->orderBy('sent_at', 'ASC');
            $messages = $builder->get()->getResult();

            // Define base URL
            $baseURL = base_url(); // e.g. http://localhost/

            foreach ($messages as &$msg) {
                if (!empty($msg->file_path)) {
                    $msg->file_path = $baseURL . 'public/' . ltrim($msg->file_path, '/');
                }
            }

            return $this->respond([
                'status' => true,
                'data' => $messages
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getChatHistoryByUser()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $request = $this->request->getJSON(true);
        $user_one_code = $request['user_one_code'] ?? null;
        $user_two_code = $request['user_two_code'] ?? null;

        if (empty($user_one_code) || empty($user_two_code)) {
            return $this->respond(['status' => false, 'message' => 'Both user_one_code and user_two_code are required'], 400);
        }

        try {
            $builder = $this->db->table('tbl_user_chat_messages');

            $builder->where("
                (from_user_ref_code = '{$user_one_code}' AND to_user_ref_code = '{$user_two_code}') 
                OR 
                (from_user_ref_code = '{$user_two_code}' AND to_user_ref_code = '{$user_one_code}')
            ", null, false);

            $builder->orderBy('sent_at', 'ASC');
            $messages = $builder->get()->getResult();

            $baseURL = base_url();

            foreach ($messages as &$msg) {
                if (!empty($msg->file_path)) {
                    $msg->file_path = $baseURL . 'public/' . ltrim($msg->file_path, '/');
                }
            }

            return $this->respond([
                'status' => true,
                'data' => $messages
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getChatHistoryallUser()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        try {
            $messages = $this->db->table('tbl_user_chat_messages')
                ->select("
                    tbl_user_chat_messages.*,
                    CONCAT(from_user.First_Name, ' ', from_user.Last_Name) AS from_user_name,
                    CONCAT(to_user.First_Name, ' ', to_user.Last_Name) AS to_user_name
                ")
                ->join('tbl_register AS from_user', 'from_user.user_code = tbl_user_chat_messages.from_user_ref_code', 'left')
                ->join('tbl_register AS to_user', 'to_user.user_code = tbl_user_chat_messages.to_user_ref_code', 'left')
                ->orderBy('sent_at', 'ASC')
                ->get()
                ->getResult();
            
            $baseURL = base_url();
            foreach ($messages as &$msg) {
                if (!empty($msg->file_path)) {
                    $msg->file_path = $baseURL . 'public/' . ltrim($msg->file_path, '/');
                }
            }
            
            return $this->respond([
                'status' => true,
                'data' => $messages
            ], 200);

        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function markAsRead()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decoded = validatejwt($authHeader);

        if (!$decoded) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing token'], 401);
        }

        $from_user = $decoded->user_id ?? null;
        $to_user   = $this->request->getPost('to_user_ref_code');

        if (!$to_user) {
            return $this->respond(['status' => false, 'message' => 'to_user_ref_code is required'], 400);
        }

        try {
            $builder = $this->db->table('tbl_user_chat_messages');
            $builder->where('from_user_ref_code', $to_user);
            $builder->where('to_user_ref_code', $from_user);
            $builder->where('is_read', 'N');
            $builder->update(['is_read' => 'Y']);

            return $this->respond(['status' => true, 'message' => 'Messages marked as read.'], 200);
        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
