<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use Config\Services;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

class SignatureandstampController extends BaseController
{
    protected $db;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = Database::connect();
        helper(['jwtvalidate', 'form', 'url']);
    }

    /**
     * Add new signature and stamp
     */
    public function add_signatureandstamp()
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
        $signatureandstamp_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        
        // Get input data
        $input = $this->request->getPost();
        
        // Create upload directories if they don't exist
        $this->createUploadDirectories();

        // Handle signature image upload
        $signatureFile = $this->request->getFile('signature_img');
        $signatureName = null;
        
        if ($signatureFile && $signatureFile->isValid() && !$signatureFile->hasMoved()) {
            $uploadPath = FCPATH . 'uploads/signatures/';
            $signatureName = 'signature_' . $signatureandstamp_code . '_' . time() . '.' . $signatureFile->getClientExtension();
            
            // Debug info
            log_message('info', 'Signature upload path: ' . $uploadPath);
            log_message('info', 'Signature file name: ' . $signatureName);
            log_message('info', 'Is directory writable: ' . (is_writable($uploadPath) ? 'Yes' : 'No'));
            
            if ($signatureFile->move($uploadPath, $signatureName)) {
                log_message('info', 'Signature file uploaded successfully: ' . $signatureName);
            } else {
                $error = $signatureFile->getErrorString();
                log_message('error', 'Failed to move signature file: ' . $error);
            }
        } else {
            $error = $signatureFile ? $signatureFile->getErrorString() : 'No file received';
            log_message('error', 'Signature file invalid: ' . $error);
        }

        // Handle stamp image upload
        $stampFile = $this->request->getFile('stamp_img');
        $stampName = null;
        
        if ($stampFile && $stampFile->isValid() && !$stampFile->hasMoved()) {
            $uploadPath = FCPATH . 'uploads/stamps/';
            $stampName = 'stamp_' . $signatureandstamp_code . '_' . time() . '.' . $stampFile->getClientExtension();
            
            // Debug info
            log_message('info', 'Stamp upload path: ' . $uploadPath);
            log_message('info', 'Stamp file name: ' . $stampName);
            log_message('info', 'Is directory writable: ' . (is_writable($uploadPath) ? 'Yes' : 'No'));
            
            if ($stampFile->move($uploadPath, $stampName)) {
                log_message('info', 'Stamp file uploaded successfully: ' . $stampName);
            } else {
                $error = $stampFile->getErrorString();
                log_message('error', 'Failed to move stamp file: ' . $error);
            }
        } else {
            $error = $stampFile ? $stampFile->getErrorString() : 'No file received';
            log_message('error', 'Stamp file invalid: ' . $error);
        }

        $data = [
            'signatureandstamp_code' => $signatureandstamp_code,
            'name'                   => $input['name'] ?? '',
            'signature_img'          => $signatureName,
            'stamp_img'              => $stampName,
            'is_active'              => 'Y',
            'created_by'             => $created_by,
            'created_at'             => date('Y-m-d H:i:s')
        ];

        // Log data for debugging
        log_message('info', 'Inserting data: ' . print_r($data, true));

        if ($this->db->table('tbl_signatureandstamp')->insert($data)) {
            return $this->respond([
                'status'        => true,
                'message'       => 'Signature and stamp added successfully',
                'signatureandstamp_code' => $signatureandstamp_code,
                'signature_img' => $signatureName,
                'stamp_img'     => $stampName
            ], 201);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to add signature and stamp'
            ], 500);
        }
    }

    /**
     * Create upload directories if they don't exist
     */
    private function createUploadDirectories()
    {
        $signaturesDir = FCPATH . 'uploads/signatures';
        $stampsDir = FCPATH . 'uploads/stamps';

        if (!is_dir($signaturesDir)) {
            mkdir($signaturesDir, 0755, true);
            log_message('info', 'Created signatures directory: ' . $signaturesDir);
        }

        if (!is_dir($stampsDir)) {
            mkdir($stampsDir, 0755, true);
            log_message('info', 'Created stamps directory: ' . $stampsDir);
        }
    }

    /**
     * Get all signatures and stamps
     */
    public function getallsignatureandstamp()
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

        $db = \Config\Database::connect();
        $signatures = $db->table('tbl_signatureandstamp')
            ->where('is_active', 'Y')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        if ($signatures) {
            foreach ($signatures as &$signature) {
                // Generate full URLs for images
                if (!empty($signature['signature_img'])) {
                    $signature['signature_img'] = base_url('uploads/signatures/' . $signature['signature_img']);
                } else {
                    $signature['signature_img'] = base_url('assets/images/profile-default.png');
                }
                
                if (!empty($signature['stamp_img'])) {
                    $signature['stamp_img'] = base_url('uploads/stamps/' . $signature['stamp_img']);
                } else {
                    $signature['stamp_img'] = base_url('assets/images/profile-default.png');
                }
            }
            
            return $this->respond([
                'status' => true,
                'data'   => $signatures
            ]);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'No signatures and stamps found',
                'data'   => []
            ]);
        }
    }

    /**
     * Serve signature images
     */
    public function serveSignatureImage($fileName)
    {
        $filePath = FCPATH . 'uploads/signatures/' . $fileName;

        if (file_exists($filePath)) {
            $mimeType = mime_content_type($filePath);
            return $this->response
                ->setContentType($mimeType)
                ->setBody(file_get_contents($filePath));
        }

        return $this->response->setStatusCode(404)->setBody('Signature image not found');
    }

    /**
     * Serve stamp images
     */
    public function serveStampImage($fileName)
    {
        $filePath = FCPATH . 'uploads/stamps/' . $fileName;

        if (file_exists($filePath)) {
            $mimeType = mime_content_type($filePath);
            return $this->response
                ->setContentType($mimeType)
                ->setBody(file_get_contents($filePath));
        }

        return $this->response->setStatusCode(404)->setBody('Stamp image not found');
    }

    /**
     * Debug method to check file upload status
     */
    public function debugUpload()
    {
        $signatureFile = $this->request->getFile('signature_img');
        $stampFile = $this->request->getFile('stamp_img');
        
        $debugInfo = [
            'signature_file' => [
                'received' => $signatureFile ? 'Yes' : 'No',
                'name' => $signatureFile ? $signatureFile->getName() : 'N/A',
                'client_name' => $signatureFile ? $signatureFile->getClientName() : 'N/A',
                'size' => $signatureFile ? $signatureFile->getSize() : 'N/A',
                'is_valid' => $signatureFile ? ($signatureFile->isValid() ? 'Yes' : 'No') : 'N/A',
                'error' => $signatureFile ? $signatureFile->getErrorString() : 'N/A'
            ],
            'stamp_file' => [
                'received' => $stampFile ? 'Yes' : 'No',
                'name' => $stampFile ? $stampFile->getName() : 'N/A',
                'client_name' => $stampFile ? $stampFile->getClientName() : 'N/A',
                'size' => $stampFile ? $stampFile->getSize() : 'N/A',
                'is_valid' => $stampFile ? ($stampFile->isValid() ? 'Yes' : 'No') : 'N/A',
                'error' => $stampFile ? $stampFile->getErrorString() : 'N/A'
            ],
            'upload_dirs' => [
                'signatures' => [
                    'path' => FCPATH . 'uploads/signatures',
                    'exists' => is_dir(FCPATH . 'uploads/signatures'),
                    'writable' => is_writable(FCPATH . 'uploads/signatures')
                ],
                'stamps' => [
                    'path' => FCPATH . 'uploads/stamps',
                    'exists' => is_dir(FCPATH . 'uploads/stamps'),
                    'writable' => is_writable(FCPATH . 'uploads/stamps')
                ]
            ],
            'server_info' => [
                'php_version' => phpversion(),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads')
            ]
        ];
        
        return $this->respond($debugInfo);
    }

    // ... keep your other methods (edit, delete, etc.) the same but update paths to use FCPATH
    public function editsignatureandstamp()
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
        $signatureandstamp_code = $input['signatureandstamp_code'] ?? null;

        if (!$signatureandstamp_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Signature and stamp code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        $data = [];

        // Update basic fields
        if (isset($input['name'])) {
            $data['name'] = $input['name'];
        }

        // Handle signature image upload
        $signatureFile = $this->request->getFile('signature_img');
        if ($signatureFile && $signatureFile->isValid() && !$signatureFile->hasMoved()) {
            // Delete old signature file if exists
            $oldSignature = $db->table('tbl_signatureandstamp')
                ->where('signatureandstamp_code', $signatureandstamp_code)
                ->get()
                ->getRow();
            
            if ($oldSignature && !empty($oldSignature->signature_img)) {
                $oldFilePath = FCPATH . 'uploads/signatures/' . $oldSignature->signature_img;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            $signatureName = 'signature_' . $signatureandstamp_code . '_' . time() . '.' . $signatureFile->getClientExtension();
            $uploadPath = FCPATH . 'uploads/signatures/';
            
            if ($signatureFile->move($uploadPath, $signatureName)) {
                $data['signature_img'] = $signatureName;
            }
        }

        // Handle stamp image upload
        $stampFile = $this->request->getFile('stamp_img');
        if ($stampFile && $stampFile->isValid() && !$stampFile->hasMoved()) {
            // Delete old stamp file if exists
            $oldStamp = $db->table('tbl_signatureandstamp')
                ->where('signatureandstamp_code', $signatureandstamp_code)
                ->get()
                ->getRow();
            
            if ($oldStamp && !empty($oldStamp->stamp_img)) {
                $oldFilePath = FCPATH . 'uploads/stamps/' . $oldStamp->stamp_img;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            $stampName = 'stamp_' . $signatureandstamp_code . '_' . time() . '.' . $stampFile->getClientExtension();
            $uploadPath = FCPATH . 'uploads/stamps/';
            
            if ($stampFile->move($uploadPath, $stampName)) {
                $data['stamp_img'] = $stampName;
            }
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($data)) {
            return $this->respond([
                'status' => false,
                'message' => 'No fields provided for update.'
            ], 400);
        }

        $signature = $db->table('tbl_signatureandstamp')
            ->where('signatureandstamp_code', $signatureandstamp_code)
            ->get()
            ->getRow();
            
        if (!$signature) {
            return $this->respond([
                'status' => false,
                'message' => 'Signature and stamp not found.'
            ], 404);
        }

        if ($db->table('tbl_signatureandstamp')->where('signatureandstamp_code', $signatureandstamp_code)->update($data)) {
            return $this->respond([
                'status'  => true,
                'message' => 'Signature and stamp updated successfully'
            ]);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to update signature and stamp'
            ], 500);
        }
    }

    public function deletesignatureandstamp()
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

        $request = $this->request->getJSON(true);
        $signatureandstamp_code = $request['signatureandstamp_code'] ?? null;

        if (!$signatureandstamp_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Signature and stamp code is required'
            ], 400);
        }

        $db = \Config\Database::connect();
        
        // Check if signature exists
        $signature = $db->table('tbl_signatureandstamp')
            ->where('signatureandstamp_code', $signatureandstamp_code)
            ->get()
            ->getRow();
            
        if (!$signature) {
            return $this->respond([
                'status' => false,
                'message' => 'Signature and stamp not found'
            ], 404);
        }

        // Soft delete by setting is_active to 'N'
        $data = [
            'is_active' => 'N',
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($db->table('tbl_signatureandstamp')->where('signatureandstamp_code', $signatureandstamp_code)->update($data)) {
            return $this->respond([
                'status'  => true,
                'message' => 'Signature and stamp deleted successfully'
            ]);
        } else {
            return $this->respond([
                'status'  => false,
                'message' => 'Failed to delete signature and stamp'
            ], 500);
        }
    }
}