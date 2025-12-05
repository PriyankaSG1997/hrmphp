<?php

use CodeIgniter\API\ResponseTrait;
use Config\App;
use Config\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

require_once ROOTPATH . 'public/JWT/src/JWT.php';
require_once ROOTPATH . 'public/JWT/src/JWTExceptionWithPayloadInterface.php';
require_once ROOTPATH . 'public/JWT/src/Key.php';
require_once ROOTPATH . 'public/JWT/src/BeforeValidException.php';
require_once ROOTPATH . 'public/JWT/src/SignatureInvalidException.php';
require_once ROOTPATH . 'public/JWT/src/ExpiredException.php';

if (!function_exists('validatejwt')) {

    function validatejwt(string $authHeader)
    {
        $secretKey = 'HS256';

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return false;
        }
        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            $userId = trim($decoded->user_id ?? '');

            $db = Database::connect();
            $user = $db->table('tbl_login')
                ->where('user_code_ref', $userId)
                ->where('token', $token)
                ->where('is_active', 'Y')
                ->get()
                ->getRow();

            if (!$user) {
                return false;
            }
            return $decoded;
        } catch (BeforeValidException $e) {
            return false;
        } catch (ExpiredException $e) {
            return false;
        } catch (SignatureInvalidException $e) {
            return false;
        } catch (\Exception $e) {
            error_log('JWT Validation Error: ' . $e->getMessage());
            return false;
        }
    }
}
