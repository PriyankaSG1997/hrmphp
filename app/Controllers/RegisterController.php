<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use Config\App;
use DateTime;

require_once ROOTPATH . 'public/JWT/src/JWT.php';


use Config\Database;
use CodeIgniter\Controller;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;

use Config\Services;
use App\Models\HomeModel;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class RegisterController extends BaseController
{

    protected $db;
    protected $key = 'HS256';

    protected $secretKey = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    protected $nodeurl;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->uri = service('uri');
        $this->homeModel = new HomeModel();
        if ($this->homeModel === null) {
            log_message('error', 'HomeModel could not be instantiated.');
        }
        require_once ROOTPATH . 'public/JWT/src/JWTExceptionWithPayloadInterface.php';
        require_once ROOTPATH . 'public/JWT/src/JWT.php';
        require_once ROOTPATH . 'public/JWT/src/Key.php';
        require_once ROOTPATH . 'public/JWT/src/BeforeValidException.php';
        require_once ROOTPATH . 'public/JWT/src/SignatureInvalidException.php';
        require_once ROOTPATH . 'public/JWT/src/ExpiredException.php';
    }


    public function authenticate()
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $input = json_decode($this->request->getBody(), true);

        if (!$input) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Invalid input format.'
            ])->setStatusCode(400);
        }

        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Email and Password are required.'
            ])->setStatusCode(400);
        }

        // ðŸ”¹ Step 1: Fetch user from tbl_login
        $user = $this->db->table('tbl_login')
            ->where('email', $email)
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$user) {
            return $this->response->setJSON([
                'status' => 401,
                'message' => 'Invalid email or password.'
            ])->setStatusCode(401);
        }

        // ðŸ”¹ Step 2: Verify password
        if (!password_verify($password, $user['password'])) {
            return $this->response->setJSON([
                'status' => 401,
                'message' => 'Invalid email or password.'
            ])->setStatusCode(401);
        }

        // ðŸ”¹ Step 3: Get department_code from tbl_register
        $register = $this->db->table('tbl_register')
            ->select('department_code')
            ->where('user_code', $user['user_code_ref'])
            ->get()
            ->getRowArray();

        $department_code = $register['department_code'] ?? null;

        // ðŸ”¹ Step 4: Generate JWT
        $timezone = new \DateTimeZone('Asia/Kolkata');
        $current_time = new \DateTime('now', $timezone);
        $last_login = $current_time->format('Y-m-d H:i:s');
        $last_token_time = $current_time->format('Y-m-d H:i:s');

        $payload = [
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60), // 24 hours
            'user_id' => $user['user_code_ref'],
            'email' => $user['email'],
        ];

        $token = JWT::encode($payload, $this->key, 'HS256');

        // ðŸ”¹ Step 5: Handle refresh token
        if ($user['auto_logout'] == 0) {
            $refreshPayload = [
                'iat' => time(),
                'exp' => time() + (48 * 60 * 60), // 48 hours
                'user_id' => $user['user_code_ref'],
                'email' => $user['email'],
            ];
            $refreshToken = JWT::encode($refreshPayload, $this->key, 'HS256');
        } else {
            $refreshToken = null;
        }

        // ðŸ”¹ Step 6: Update login info
        $updateData = [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'last_login' => $last_login,
            'last_token_time' => $last_token_time,
            'Devices_count' => ($user['Devices_count'] ?? 0) + 1
        ];

        $this->db->table('tbl_login')
            ->where('email', $email)
            ->update($updateData);

        // ðŸ”¹ Step 7: Return response
        return $this->response->setJSON([
            'userid' => $user['user_code_ref'],
            'email' => $user['email'],
            'role_ref_code' => $user['role_ref_code'],
            'user_name' => $user['user_name'],
            'designations' => $user['designations_code'],
            'department_code' => $department_code, // âœ… Added here
            'message' => 'Authentication successful',
            'token' => $token,
            'refresh_token' => $refreshToken,
        ])->setStatusCode(200);
    }

    //********************************************************************************************************************//

    public function forgot_password_api()
    {
        $this->response->setHeader('Content-Type', 'application/json');

        $input = $this->request->getJSON(true);
        $email = $input['email'] ?? $this->request->getPost('email');

        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'A valid email is required'
            ])->setStatusCode(400);
        }

        // Check user existence
        $user = $this->db->table('tbl_login')->where('email', $email)->get()->getRow();

        if (!$user) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Email not found'
            ])->setStatusCode(404);
        }

        // Generate a new reset code
        $reset_code = (string) random_int(100000, 999999);

        // Prepare update data
        $updateData = [
            'password_token'      => $reset_code,            // token as VARCHAR
            'password_token_time' => date('Y-m-d H:i:s')     // timestamp of generation
        ];

        // Update token and time in DB
        $this->db->table('tbl_login')
            ->where('email', $email)
            ->update($updateData);

        // Load PHPMailer
        require_once APPPATH . '../vendor/autoload.php';
        $mail = new PHPMailer(true);

        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host       = 'mail.skdconsultants.org';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'support@skdconsultants.org';
            $mail->Password   = '77c18zC(96tZ';
            $mail->SMTPSecure = 'ssl';
            $mail->Port       = 465;

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Email content
            $mail->setFrom('support@skdconsultants.org', 'SDKCPL Support');
            $mail->addAddress($email, $user->user_name ?? '');
            $mail->isHTML(true);
            $mail->Subject = 'Reset Password Code';
            $mail->Body = "
            Hi <strong>{$user->user_name}</strong>,<br><br>
            Your password reset code is: <strong>{$reset_code}</strong><br><br>
            This code will expire in 15 minutes.<br><br>
            Regards,<br>
            <strong>SDKCPL Support</strong>
        ";

            $mail->send();

            return $this->response->setJSON([
                'status' => true,
                'message' => 'Reset code sent to your email successfully.',
                'email'   => $email,
                'token'   => $reset_code
            ])->setStatusCode(200);
        } catch (Exception $e) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Email sending failed: ' . $mail->ErrorInfo
            ])->setStatusCode(500);
        }
    }

public function reset_password_api()
{
    $this->response->setHeader('Content-Type', 'application/json');

    // Get POST values from form-urlencoded or JSON
    $email = strtolower(trim($this->request->getPost('email')));
    $reset_code = $this->request->getPost('code');
    $new_password = $this->request->getPost('new_password');

    // Validate required fields
    if (empty($email) || empty($reset_code) || empty($new_password)) {
        return $this->response->setJSON([
            'status' => false,
            'message' => 'Email, reset code, and new password are required.'
        ])->setStatusCode(400);
    }

    // Find user with matching email and password token
    $user = $this->db->table('tbl_login')
        ->where('email', $email)
        ->where('password_token', $reset_code)
        ->get()
        ->getRow();

    if (!$user) {
        return $this->response->setJSON([
            'status' => false,
            'message' => 'Invalid email or reset code.'
        ])->setStatusCode(401);
    }

    // Check if the token has expired (15 minutes)
    $code_created_at = strtotime($user->password_token_time);
    if ($code_created_at && (time() - $code_created_at > 900)) { // 900s = 15 min
        return $this->response->setJSON([
            'status' => false,
            'message' => 'Reset code has expired. Please request a new one.'
        ])->setStatusCode(410);
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Update password and clear token fields
    $this->db->table('tbl_login')
        ->where('email', $email)
        ->update([
            'password' => $hashed_password,
            'password_token' => null,
            'password_token_time' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

    return $this->response->setJSON([
        'status' => true,
        'message' => 'Password has been updated successfully.'
    ])->setStatusCode(200);
}


    //**************************************************************************





    public function direct_reset_password_api()
    {
        $this->response->setHeader('Content-Type', 'application/json');

        // Get JWT from Authorization header
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->fail('Authorization token is required.', 401);
        }

        $jwt = $matches[1];

        try {
            $decoded = JWT::decode($jwt, new Key($this->secretKey, 'HS256'));
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Invalid or expired token.',
                'error'   => $e->getMessage()
            ], 401);
        }

        // Continue with password reset
        $user_code    = $this->request->getPost('user_code');
        $new_password = $this->request->getPost('new_password');

        if (empty($user_code) || empty($new_password)) {
            return $this->fail('User code and new password are required.', 400);
        }

        $user = $this->db->table('tbl_login')
            ->where('user_code_ref', $user_code)
            ->get()
            ->getRow();

        if (!$user) {
            return $this->fail('User not found.', 404);
        }

        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        $this->db->table('tbl_login')
            ->where('user_code_ref', $user_code)
            ->update([
                'password' => $hashed_password,
                'token' => null,
                'last_token_time' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $this->respond([
            'status' => true,
            'message' => 'Password has been reset successfully.'
        ], 200);
    }

    public function direct_reset_password_api_without_jwt()
    {
        $this->response->setHeader('Content-Type', 'application/json');

        $user_code    = $this->request->getPost('user_code');
        $new_password = $this->request->getPost('new_password');

        if (empty($user_code) || empty($new_password)) {
            return $this->fail('User code and new password are required.', 400);
        }

        $user = $this->db->table('tbl_login')
            ->where('user_code_ref', $user_code)
            ->get()
            ->getRow();

        if (!$user) {
            return $this->fail('User not found.', 404);
        }

        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        $this->db->table('tbl_login')
            ->where('user_code_ref', $user_code)
            ->update([
                'password' => $hashed_password,
                'token' => null,
                'last_token_time' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $this->respond([
            'status' => true,
            'message' => 'Password has been reset successfully (no JWT required).'
        ], 200);
    }
}
