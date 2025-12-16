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
class AccountController extends BaseController
{
    protected $db;
    protected $key = 'HS256';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format = 'json';
    protected $homeModel;
    use ResponseTrait;


    public function add_payment()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);



        $db = \Config\Database::connect();
        $company = $db->table('tbl_company')
            ->select('company_code, company_name')
            ->where('company_code', $input['company_code'])
            ->get()
            ->getRow();
        if (!$company) {
            return $this->respond(['status' => false, 'message' => 'Invalid company code'], 400);
        }

        // $prefix = strtoupper(substr($company->company_name, 0, 3));
        // $monthYear = date('mY');
        // $lastVoucher = $db->table('tbl_payment_records')
        //     ->select('voucher_no')
        //     ->like('voucher_no', $prefix . $monthYear, 'after')
        //     ->orderBy('id', 'DESC')
        //     ->get()
        //     ->getRow();
        // if ($lastVoucher) {
        //     $lastSerial = (int) substr($lastVoucher->voucher_no, -3);
        //     $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        // } else {
        //     $newSerial = "001";
        // }
        // $voucher_no = $prefix . '-' . $monthYear . $newSerial;

        $cleanName = preg_replace('/[^A-Za-z]/', '', $company->company_name);
        $prefix = strtoupper(substr($cleanName, 0, 3));

        $monthYear = date('mY');

        $searchPrefix = $prefix . '-' . $monthYear;

        $lastVoucher = $db->table('tbl_payment_records')
            ->select('voucher_no')
            ->like('voucher_no', $searchPrefix, 'after')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();

        if ($lastVoucher) {
            $lastSerial = (int) substr($lastVoucher->voucher_no, -3);
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = "001";
        }

        $voucher_no = $prefix . '-' . $monthYear . $newSerial;

        $payment_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
        $data = [
            'payment_code' => $payment_code,
            'company_code' => $company->company_code,
            'paid_by' => $input['paid_by'] ?? null,
            'payment_method' => $input['payment_method'] ?? null, // ✔ no error
            'voucher_no' => $voucher_no,
            'payment_date' => $input['payment_date'],
            'pay_to' => $input['pay_to'],
            'amount' => $input['amount'],
            'being' => $input['being'] ?? null,
            'authorized_by' => $input['authorized_by'] ?? null,
            'IFSC_code' => $input['IFSC_code'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'upi_id' => $input['upi_id'] ?? null,
            'cheque_no' => $input['cheque_no'] ?? null,
            'transaction_ref' => $input['transaction_ref'] ?? null,
            'bank_name' => $input['bank_name'] ?? null,
            'branch' => $input['branch'] ?? null,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y',
            'expense_bill_no' => $input['expense_bill_no'],
        ];

        try {
            $builder = $db->table('tbl_payment_records');
            $builder->insert($data);

            return $this->respondCreated([
                'status' => true,
                'message' => 'Payment record created successfully',
                'voucher_no' => $voucher_no,
                'payment_code' => $payment_code
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Failed to create payment record: ' . $e->getMessage());
        }
    }

    public function add_tax_invoice()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);

        $db = \Config\Database::connect();
        $company = $db->table('tbl_company')
            ->select('company_code, company_name')
            ->where('company_code', $input['company_code'])
            ->get()
            ->getRow();

        if (!$company) {
            return $this->respond(['status' => false, 'message' => 'Invalid company code'], 400);
        }

        // log_message('info', 'Fetched company => Code: ' . $company->company_code . ' | Name: ' . $company->company_name);


        // Generate invoice code
        // $prefix = strtoupper(substr($company->company_name, 0, 3));
        // $monthYear = date('mY');

        // $lastInvoice = $db->table('tbl_tax_invoice')
        //     ->select('invoice_code')
        //     ->like('invoice_code', $prefix . '-' . $monthYear, 'after')
        //     ->orderBy('id', 'DESC')
        //     ->get()
        //     ->getRow();

        // if ($lastInvoice) {
        //     $lastSerial = (int) substr($lastInvoice->invoice_code, -3);
        //     $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        // } else {
        //     $newSerial = "001";
        // }

        // $invoice_code = $prefix . '-' . $monthYear . $newSerial;

        // $prefix = strtoupper(substr($company->company_name, 0, 3)); // MIT 
        $name = str_replace(' ', '', $company->company_name); // remove spaces
        $prefix = strtoupper(substr($name, 0, 3));


        // ===== Financial Year Logic =====
        $currentMonth = date('n'); // 1-12
        $currentYear = date('y');  // 25
        $nextYear = $currentYear + 1; // 26

        if ($currentMonth <= 3) {
            // Before April → FY belongs to previous year
            $fy = ($currentYear - 1) . $currentYear;
        } else {
            // April & onward
            $fy = $currentYear . $nextYear;
        }

        // Final code part → 2526
        $periodCode = 'G' . $fy;

        //=================================

        $lastInvoice = $db->table('tbl_tax_invoice')
            ->select('invoice_code')
            ->like('invoice_code', $prefix . '-' . $periodCode, 'after')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();

        if ($lastInvoice) {
            $lastSerial = (int) substr($lastInvoice->invoice_code, -3);
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = "001";
        }

        $invoice_code = $prefix . '-' . $periodCode . '-' . $newSerial;



        // Main invoice data
        $invoiceData = [
            'invoice_code' => $invoice_code,
            'invoice_date' => $input['invoice_date'],
            'client_code' => $input['client_code'] ?? null,
            'company_code' => $company->company_code,
            'invoice_type' => $input['invoice_type'],
            'Own_GSTN' => $input['Own_GSTN'] ?? null,
            'bill_to_name' => $input['bill_to_name'],
            'address' => $input['address'] ?? null,
            'gstin_number' => $input['gstin_number'] ?? null,
            'pan_number' => $input['pan_number'] ?? null,
            'email' => $input['email'] ?? null,
            'bank_code' => $input['bank_code'] ?? null,
            'branch_name' => $input['branch_name'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'ifsc' => $input['ifsc'] ?? null,
            'hsn_sac' => $input['hsn_sac'] ?? null,
            'subtotal' => $input['subtotal'] ?? 0.00,
            'sgst' => $input['sgst'] ?? 0.00,
            'scst' => $input['scst'] ?? 0.00,
            'total' => $input['total'] ?? 0.00,
            'notes' => $input['notes'] ?? null,
            'signature_code' => $input['signature_code'] ?? null,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y',
            'client_phone_number' => $input['client_phone_number'] ?? null,
        ];

        $db->transStart(); // Start transaction

        try {
            // Insert into tbl_tax_invoice
            $db->table('tbl_tax_invoice')->insert($invoiceData);

            // Insert invoice items (array expected in request)
            if (!empty($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $index => $item) {
                    $itemRelationCode = $invoice_code . '-ITM' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

                    $itemData = [
                        'invoice_code' => $invoice_code,
                        'item_relation_code' => $itemRelationCode,
                        'item' => $item['item'] ?? null,
                        'quantity' => $item['quantity'] ?? 0,
                        'price' => $item['price'] ?? 0.00,
                        'total' => ($item['quantity'] ?? 0) * ($item['price'] ?? 0.00),
                        'created_by' => $created_by,
                        'created_at' => date('Y-m-d H:i:s'),
                        'is_active' => 'Y'
                    ];

                    $db->table('tbl_tax_invoice_item_relation')->insert($itemData);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->failServerError('Failed to create tax invoice with items.');
            }

            return $this->respondCreated([
                'status' => true,
                'message' => 'Tax invoice created successfully',
                'invoice_code' => $invoice_code
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->failServerError('Failed to create tax invoice: ' . $e->getMessage());
        }
    }

    public function add_bank()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);

        $db = \Config\Database::connect();

        $lastBank = $db->table('tbl_bank')
            ->select('bank_code')
            ->like('bank_code', 'BANK', 'after')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();

        if ($lastBank) {
            $lastSerial = (int) substr($lastBank->bank_code, 4);
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = "001";
        }

        $bank_code = "BANK" . $newSerial;

        $data = [
            'bank_code' => $bank_code,
            'bank_name' => $input['bank_name'],
            'branch_name' => $input['branch_name'] ?? null,
            'ifsc' => $input['ifsc'] ?? null,
            'account_holder_name' => $input['account_holder_name'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'account_type' => $input['account_type'] ?? null,
            'description' => $input['description'] ?? null,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y'
        ];

        try {
            $builder = $db->table('tbl_bank');
            $builder->insert($data);

            return $this->respondCreated([
                'status' => true,
                'message' => 'Bank added successfully',
                'bank_code' => $bank_code
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Failed to add bank: ' . $e->getMessage());
        }
    }
    public function get_bank_by_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        $input = $this->request->getJSON(true);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $bank_code = $input['bank_code'] ?? null;
        if (!$bank_code) {
            return $this->respond(['status' => false, 'message' => 'Bank code is required'], 400);
        }

        $db = \Config\Database::connect();
        $bank = $db->table('tbl_bank')
            ->where('bank_code', $bank_code)
            ->where('is_active', 'Y')
            ->get()
            ->getRow();

        if ($bank) {
            return $this->respond(['status' => true, 'data' => $bank], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'Bank not found'], 404);
        }
    }
    public function purchase_order()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);
        $db = \Config\Database::connect();

        $company = $db->table('tbl_company')
            ->select('company_code, company_name')
            ->where('company_code', $input['company_code'])
            ->get()
            ->getRow();

        if (!$company) {
            return $this->respond(['status' => false, 'message' => 'Invalid company code'], 400);
        }

        $prefix = strtoupper(substr($company->company_name, 0, 3));
        $monthYear = date('mY');
        $lastPO = $db->table('tbl_purchase_order')
            ->select('po_number')
            ->like('po_number', $prefix . '-' . $monthYear, 'after')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();
        if ($lastPO) {
            $lastSerial = (int) substr($lastPO->po_number, -3);
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = "001";
        }
        $po_number = $prefix . '-' . $monthYear . $newSerial;
        $purchase_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $poData = [
            'purchase_code' => $purchase_code,
            'company_code' => $company->company_code,
            'po_number' => $po_number,
            'po_title' => $input['po_title'],
            'po_date' => $input['po_date'],
            'supplier_name' => $input['supplier_name'],
            'address' => $input['address'] ?? null,
            'gstin_number' => $input['gstin_number'] ?? null,
            'pan_number' => $input['pan_number'] ?? null,
            'email' => $input['email'] ?? null,
            'phone_number' => $input['phone_number'] ?? null,
            'notes' => $input['notes'] ?? null,
            'subtotal' => $input['subtotal'] ?? 0.00,
            'shipping' => $input['shipping'] ?? 0.00,
            'discount' => $input['discount'] ?? 0.00,
            'paymentstatus' => $input['paymentstatus'] ?? 'pending',
            'tax' => $input['tax'] ?? 0.00,
            'total' => $input['total'] ?? 0.00,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y',
            'signature_code' => $input['signature_code'] ?? null,

        ];
        try {
            $db->transStart();
            $db->table('tbl_purchase_order')->insert($poData);
            if (!empty($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $item) {
                    $item_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                    $itemData = [
                        'purchase_code' => $purchase_code,
                        'item_relation_code' => $item_code,
                        'item' => $item['item'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'subtotal' => $item['subtotal'],
                        'shipping' => $item['shipping'] ?? 0.00,
                        'discount' => $item['discount'] ?? 0.00,
                        'tax' => $item['tax'] ?? 0.00,
                        'total' => $item['total'],
                        'created_by' => $created_by,
                        'created_at' => date('Y-m-d H:i:s'),
                        'is_active' => 'Y'
                    ];
                    $db->table('tbl_purchase_order_item_relation')->insert($itemData);
                }
            }
            $db->transComplete();
            return $this->respondCreated([
                'status' => true,
                'message' => 'Purchase order created successfully',
                'purchase_code' => $purchase_code,
                'po_number' => $po_number
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->failServerError('Failed to create purchase order: ' . $e->getMessage());
        }
    }


    public function add_credit_note()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);
        $db = \Config\Database::connect();
        $creditnote_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $company = $db->table('tbl_company')
            ->select('company_code, company_name')
            ->where('company_code', $input['company_code'])
            ->get()
            ->getRow();

        if (!$company) {
            return $this->respond(['status' => false, 'message' => 'Invalid company code'], 400);
        }
        $prefix = strtoupper(substr($company->company_name, 0, 3));
        $monthYear = date('mY');
        $lastCreditNote = $db->table('tbl_creditnote')
            ->select('credit_note_no')
            ->like('credit_note_no', $prefix . '-' . $monthYear, 'after')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();
        if ($lastCreditNote) {
            $lastSerial = (int) substr($lastCreditNote->credit_note_no, -3);
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = "001";
        }
        $credit_note_no = $prefix . '-' . $monthYear . $newSerial;
        $creditNoteData = [
            'creditnote_code' => $creditnote_code,
            'credit_note_date' => $input['credit_note_date'],
            'credit_note_no' => $credit_note_no,
            'company_code' => $company->company_code,
            'invoice_code' => $input['invoice_code'] ?? null,
            'supplier_name' => $input['supplier_name'],
            'address' => $input['address'] ?? null,
            'gstin_number' => $input['gstin_number'] ?? null,
            'pan_number' => $input['pan_number'] ?? null,
            'email' => $input['email'] ?? null,
            'phone_number' => $input['phone_number'] ?? null,
            'subtotal' => $input['subtotal'],
            'paymentstatus' => $input['paymentstatus'] ?? 'pending',
            'discount' => $input['discount'],
            'tax' => $input['tax'],
            'total' => $input['total'],
            'notes' => $input['notes'] ?? null,
            'created_by' => $created_by,
            'signature_code' => $input['signature_code'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y'
        ];
        $db->transStart();
        try {
            $db->table('tbl_creditnote')->insert($creditNoteData);
            if (!empty($input['items'])) {
                foreach ($input['items'] as $item) {
                    $itemData = [
                        'creditnote_code' => $creditnote_code,
                        'item_relation_code' => substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8),
                        'item' => $item['item'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'amount' => $item['amount'],
                        'created_by' => $created_by,
                        'created_at' => date('Y-m-d H:i:s'),
                        'is_active' => 'Y'
                    ];
                    $db->table('tbl_creditnote_item_relation')->insert($itemData);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->failServerError('Failed to create credit note.');
            }

            return $this->respondCreated([
                'status' => true,
                'message' => 'Credit note created successfully',
                'credit_note_no' => $credit_note_no,
                'creditnote_code' => $creditnote_code
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->failServerError('Error: ' . $e->getMessage());
        }
    }

    public function add_debit_note()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);
        $db = \Config\Database::connect();
        $debitnote_code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        $prefix = "DBN-";
        $monthYear = date('mY');
        $lastNote = $db->table('tbl_debitnote')
            ->select('debit_note_no')
            ->like('debit_note_no', $prefix . $monthYear, 'after')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();
        if ($lastNote) {
            $lastSerial = (int) substr($lastNote->debit_note_no, -3);
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = "001";
        }
        $debit_note_no = $prefix . $monthYear . $newSerial;
        $parentData = [
            'debitnote_code' => $debitnote_code,
            'debit_note_date' => $input['debit_note_date'],
            'debit_note_no' => $debit_note_no,
            'invoice_code' => $input['invoice_code'] ?? null,
            'company_code' => $input['company_code'],
            'supplier_name' => $input['supplier_name'],
            'address' => $input['address'] ?? null,
            'gstin_number' => $input['gstin_number'] ?? null,
            'pan_number' => $input['pan_number'] ?? null,
            'email' => $input['email'] ?? null,
            'phone_number' => $input['phone_number'] ?? null,
            'notes' => $input['notes'] ?? null,
            'subtotal' => $input['subtotal'] ?? 0,
            'tax' => $input['tax'] ?? 0,
            'total' => $input['total'] ?? 0,
            'Paymentstatus' => "pending",
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y',
            'signature_code' => $input['signature_code'] ?? null,
        ];
        $db->table('tbl_debitnote')->insert($parentData);
        if (!empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                $itemData = [
                    'debitnote_code' => $debitnote_code,
                    'item_relation_code' => strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)),
                    'item' => $item['item'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'amount' => $item['amount'],
                    'created_by' => $created_by,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => 'Y'
                ];
                $db->table('tbl_debitnote_item_relation')->insert($itemData);
            }
        }

        return $this->respondCreated([
            'status' => true,
            'message' => 'Debit Note created successfully',
            'debit_note_no' => $debit_note_no,
            'debitnote_code' => $debitnote_code
        ]);
    }

    public function add_yearly_budget()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);
        $db = \Config\Database::connect();
        $budget_code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        $data = [
            'budget_code' => $budget_code,
            'company_code' => $input['company_code'],
            'alter_type' => $input['alter_type'],
            'budget_date' => $input['budget_date'],
            'budget_amount' => $input['budget_amount'],
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 'Y',
            'Deatils' => $input['Deatils'] ?? null
        ];

        try {
            $db->table('tbl_yearly_budget')->insert($data);

            return $this->respondCreated([
                'status' => true,
                'message' => 'Yearly budget added successfully',
                'budget_code' => $budget_code
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Failed to create yearly budget: ' . $e->getMessage());
        }
    }

    public function add_alerts()
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
        $data = $this->request->getJSON(true);
        if (!isset($data['company_code'], $data['alerts_type'], $data['start_date'], $data['due_date'], $data['amount'])) {
            return $this->respond([
                'status' => false,
                'message' => 'Missing required fields'
            ], 400);
        }
        $prefix = "ALT-";
        $monthYear = date("mY");
        $builder = $db->table('tbl_alerts_list');
        $builder->selectMax('alerts_code');
        $builder->like('alerts_code', $prefix . $monthYear, 'after');
        $query = $builder->get()->getRowArray();
        $lastCode = $query['alerts_code'] ?? null;
        if ($lastCode) {
            $lastSerial = intval(substr($lastCode, -3));
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = "001";
        }
        $alerts_code = $prefix . $monthYear . $newSerial;
        $insertData = [
            'alerts_code' => $alerts_code,
            'company_code' => $data['company_code'],
            'alerts_type' => $data['alerts_type'],
            'Details' => $data['Details'] ?? null,
            'policy' => $data['policy'] ?? null,
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'created_by' => $decodedToken->user_id ?? 'system',
            'is_active' => 'Y',
        ];

        if ($db->table('tbl_alerts_list')->insert($insertData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Alert added successfully',
                'alerts_code' => $alerts_code
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to insert alert'
            ], 500);
        }
    }

    public function get_all_alerts()
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
        $company_code = $this->request->getGet('company_code');

        $builder = $db->table('tbl_alerts_list as a')
            ->select('a.*, c.company_name')
            ->join('tbl_company as c', 'a.company_code = c.company_code', 'left')
            ->where('a.is_active', 'Y');

        if ($company_code) {
            $builder->where('a.company_code', $company_code);
        }

        $alerts = $builder->get()->getResultArray();

        return $this->respond([
            'status' => true,
            'data' => $alerts
        ], 200);
    }

    public function get_all_yearly_budgets()
    {
        $db = \Config\Database::connect();
        $company_code = $this->request->getGet('company_code');
        $builder = $db->table('tbl_yearly_budget as y')
            ->select('y.*, c.company_name')
            ->join('tbl_company as c', 'y.company_code = c.company_code', 'left')
            ->where('y.is_active', 'Y');
        if ($company_code) {
            $builder->where('y.company_code', $company_code);
        }
        $budgets = $builder->get()->getResultArray();
        return $this->respond([
            'status' => true,
            'data' => $budgets
        ], 200);
    }
    public function get_all_credit_notes()
    {
        $db = \Config\Database::connect();
        $company_code = $this->request->getGet('company_code');
        $builder = $db->table('tbl_creditnote as c')
            ->select('c.*, co.company_name')
            ->join('tbl_company as co', 'c.company_code = co.company_code', 'left')

            ->where('c.is_active', 'Y');
        if ($company_code) {
            $builder->where('c.company_code', $company_code);
        }
        $creditNotes = $builder->get()->getResultArray();
        foreach ($creditNotes as &$note) {
            $note['items'] = $db->table('tbl_creditnote_item_relation')
                ->where('creditnote_code', $note['creditnote_code'])
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
        }

        return $this->respond([
            'status' => true,
            'data' => $creditNotes
        ], 200);
    }
    public function get_all_debit_notes()
    {
        $db = \Config\Database::connect();
        $company_code = $this->request->getGet('company_code');

        $builder = $db->table('tbl_debitnote as d')
            ->select('d.*, co.company_name')
            ->join('tbl_company as co', 'd.company_code = co.company_code', 'left')
            ->where('d.is_active', 'Y');

        if ($company_code) {
            $builder->where('d.company_code', $company_code);
        }

        $debitNotes = $builder->get()->getResultArray();
        foreach ($debitNotes as &$note) {
            $note['items'] = $db->table('tbl_debitnote_item_relation')
                ->where('debitnote_code', $note['debitnote_code'])
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
        }

        return $this->respond([
            'status' => true,
            'data' => $debitNotes
        ], 200);
    }
    public function get_all_payments()
    {
        $db = \Config\Database::connect();
        $company_code = $this->request->getGet('company_code');

        $builder = $db->table('tbl_payment_records as p')
            ->select('p.*, c.company_name, 
                 CONCAT(
                     COALESCE(r.First_Name, ""), 
                     " ", 
                     COALESCE(r.Middle, ""), 
                     " ", 
                     COALESCE(r.Last_Name, "")
                 ) as paid_by_name,
                 r.user_code')
            ->join('tbl_company as c', 'p.company_code = c.company_code', 'left')
            ->join('tbl_register as r', 'p.paid_by = r.user_code', 'left')
            ->where('p.is_active', 'Y');

        if ($company_code) {
            $builder->where('p.company_code', $company_code);
        }

        $payments = $builder->get()->getResultArray();

        return $this->respond([
            'status' => true,
            'data' => $payments
        ], 200);
    }
    public function get_all_tax_invoices()
    {
        $db = \Config\Database::connect();
        $company_code = $this->request->getGet('company_code');

        $builder = $db->table('tbl_tax_invoice as t')
            ->select('t.*, c.company_name')
            ->join('tbl_company as c', 't.company_code = c.company_code', 'left')
            ->where('t.is_active', 'Y');

        if ($company_code) {
            $builder->where('t.company_code', $company_code);
        }

        $invoices = $builder->get()->getResultArray();

        return $this->respond([
            'status' => true,
            'data' => $invoices
        ], 200);
    }
    public function get_all_banks()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('tbl_bank')->where('is_active', 'Y');

        $banks = $builder->get()->getResultArray();

        return $this->respond([
            'status' => true,
            'data' => $banks
        ], 200);
    }
    public function get_all_purchase_orders()
    {
        $db = \Config\Database::connect();
        $company_code = $this->request->getGet('company_code');

        $builder = $db->table('tbl_purchase_order as po')
            ->select('po.*, c.company_name')
            ->join('tbl_company as c', 'po.company_code = c.company_code', 'left')
            ->where('po.is_active', 'Y');

        if ($company_code) {
            $builder->where('po.company_code', $company_code);
        }

        $orders = $builder->get()->getResultArray();

        foreach ($orders as &$order) {
            $order['items'] = $db->table('tbl_purchase_order_item_relation')
                ->where('purchase_code', $order['purchase_code'])
                ->where('is_active', 'Y')
                ->get()
                ->getResultArray();
        }

        return $this->respond([
            'status' => true,
            'data' => $orders
        ], 200);
    }

    public function update_payment()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $input = $this->request->getJSON(true);
        if (!isset($input['payment_code'])) {
            return $this->respond(['status' => false, 'message' => 'payment_code is required'], 400);
        }
        $db = \Config\Database::connect();
        $data = [
            'payment_method' => $input['payment_method'] ?? null,
            'payment_date' => $input['payment_date'] ?? null,
            'cheque_no' => $input['cheque_no'] ?? null,
            'company_code' => $input['company_code'] ?? null,
            'paid_by' => $input['paid_by'] ?? null,
            'pay_to' => $input['pay_to'] ?? null,
            'amount' => $input['amount'] ?? null,
            'being' => $input['being'] ?? null,
            'authorized_by' => $input['authorized_by'] ?? null,
            'IFSC_code' => $input['IFSC_code'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'bank_name' => $input['bank_name'] ?? null,
            'branch' => $input['branch'] ?? null,
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
            'expense_bill_no' => $input['expense_bill_no'],
        ];
        if ($db->table('tbl_payment_records')->where('payment_code', $input['payment_code'])->update($data)) {
            return $this->respond(['status' => true, 'message' => 'Payment record updated successfully']);
        } else {
            return $this->failServerError('Failed to update payment record');
        }
    }
    public function update_tax_invoice()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        if (!isset($input['invoice_code'])) {
            return $this->respond(['status' => false, 'message' => 'invoice_code is required'], 400);
        }
        $db = \Config\Database::connect();
        $data = [
            'invoice_date' => $input['invoice_date'] ?? null,
            'invoice_type' => $input['invoice_type'] ?? null,
            'bill_to_name' => $input['bill_to_name'] ?? null,
            'Own_GSTN' => $input['Own_GSTN'] ?? null,
            'address' => $input['address'] ?? null,
            'gstin_number' => $input['gstin_number'] ?? null,
            'pan_number' => $input['pan_number'] ?? null,
            'email' => $input['email'] ?? null,
            'bank_code' => $input['bank_code'] ?? null,
            'branch_name' => $input['branch_name'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'signature_code' => $input['signature_code'] ?? null,
            'ifsc' => $input['ifsc'] ?? null,
            'hsn_sac' => $input['hsn_sac'] ?? null,
            'subtotal' => $input['subtotal'] ?? 0.00,
            'sgst' => $input['sgst'] ?? 0.00,
            'scst' => $input['scst'] ?? 0.00,
            'total' => $input['total'] ?? 0.00,
            'notes' => $input['notes'] ?? null,

            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $db->table('tbl_tax_invoice')
            ->where('invoice_code', $input['invoice_code'])
            ->update($data);
        if (!empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                $itemData = [
                    'item' => $item['item'] ?? null,
                    'Quantity' => $item['Quantity'] ?? 0,
                    'Price' => $item['Price'] ?? 0,
                    'Total' => $item['Total'] ?? 0,
                    'updated_by' => $decodedToken->user_id ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'is_active' => 'Y'
                ];

                if (!empty($item['item_relation_code'])) {
                    $exists = $db->table('tbl_tax_invoice_item_relation')
                        ->where('invoice_code', $input['invoice_code'])
                        ->where('item_relation_code', $item['item_relation_code'])
                        ->get()
                        ->getRow();

                    if ($exists) {
                        $db->table('tbl_tax_invoice_item_relation')
                            ->where('invoice_code', $input['invoice_code'])
                            ->where('item_relation_code', $item['item_relation_code'])
                            ->update($itemData);
                    } else {
                        $itemData['invoice_code'] = $input['invoice_code'];
                        $itemData['item_relation_code'] = $item['item_relation_code'];
                        $itemData['created_by'] = $decodedToken->user_id ?? null;
                        $itemData['created_at'] = date('Y-m-d H:i:s');
                        $db->table('tbl_tax_invoice_item_relation')->insert($itemData);
                    }
                } else {
                    $itemData['invoice_code'] = $input['invoice_code'];
                    $itemData['item_relation_code'] = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
                    $itemData['created_by'] = $decodedToken->user_id ?? null;
                    $itemData['created_at'] = date('Y-m-d H:i:s');
                    $db->table('tbl_tax_invoice_item_relation')->insert($itemData);
                }
            }
        }

        return $this->respond(['status' => true, 'message' => 'Tax invoice & items updated successfully']);
    }
    public function update_bank()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $input = $this->request->getJSON(true);
        if (!isset($input['bank_code'])) {
            return $this->respond(['status' => false, 'message' => 'bank_code is required'], 400);
        }
        $data = [
            'account_holder_name' => $input['account_holder_name'] ?? null,
            'account_type' => $input['account_type'] ?? null,
            'description' => $input['description'] ?? null,
            'bank_name' => $input['bank_name'] ?? null,
            'branch_name' => $input['branch_name'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'ifsc' => $input['ifsc'] ?? null,
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $db = \Config\Database::connect();
        if ($db->table('tbl_bank')->where('bank_code', $input['bank_code'])->update($data)) {
            return $this->respond(['status' => true, 'message' => 'Bank record updated successfully']);
        } else {
            return $this->failServerError('Failed to update bank record');
        }
    }
    public function update_purchase_order()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $input = $this->request->getJSON(true);
        if (!isset($input['purchase_code'])) {
            return $this->respond(['status' => false, 'message' => 'purchase_code is required'], 400);
        }
        $db = \Config\Database::connect();
        $poData = [
            'company_code' => $input['company_code'] ?? null,
            'po_title' => $input['po_title'] ?? null,
            'po_date' => $input['po_date'] ?? null,
            'supplier_name' => $input['supplier_name'] ?? null,
            'address' => $input['address'] ?? null,
            'gstin_number' => $input['gstin_number'] ?? null,
            'pan_number' => $input['pan_number'] ?? null,
            'email' => $input['email'] ?? null,
            'phone_number' => $input['phone_number'] ?? null,
            'notes' => $input['notes'] ?? null,
            'paymentstatus' => $input['paymentstatus'] ?? 'pending',
            'subtotal' => $input['subtotal'] ?? 0.00,
            'shipping' => $input['shipping'] ?? 0.00,
            'discount' => $input['discount'] ?? 0.00,
            'tax' => $input['tax'] ?? 0.00,
            'total' => $input['total'] ?? 0.00,
            'is_active' => $input['status'] ?? 'Y',
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
                        'signature_code' => $input['signature_code'] ?? null,

        ];
        $db->transStart();
        $db->table('tbl_purchase_order')
            ->where('purchase_code', $input['purchase_code'])
            ->update($poData);
        if (!empty($input['items']) && is_array($input['items'])) {
            $db->table('tbl_purchase_order_item_relation')
                ->where('purchase_code', $input['purchase_code'])
                ->update([
                    'is_active' => 'N',
                    'updated_by' => $decodedToken->user_id ?? null,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            foreach ($input['items'] as $item) {
                $item_code = $item['item_relation_code'] ?? substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

                $itemData = [
                    'purchase_code' => $input['purchase_code'],
                    'item_relation_code' => $item_code,
                    'item' => $item['item'] ?? null,
                    'quantity' => $item['quantity'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'subtotal' => $item['subtotal'] ?? 0,
                    'shipping' => $item['shipping'] ?? 0.00,
                    'discount' => $item['discount'] ?? 0.00,
                    'tax' => $item['tax'] ?? 0.00,
                    'total' => $item['total'] ?? 0,
                    'created_by' => $decodedToken->user_id ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => 'Y'
                ];

                $db->table('tbl_purchase_order_item_relation')->insert($itemData);
            }
        }

        $db->transComplete();

        return $this->respond([
            'status' => true,
            'message' => 'Purchase order & items updated successfully'
        ]);
    }

    public function update_credit_note()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        if (!isset($input['credit_code'])) {
            return $this->respond(['status' => false, 'message' => 'credit_code is required'], 400);
        }

        $db = \Config\Database::connect();
        $creditNoteData = [
            'credit_note_date' => $input['credit_note_date'] ?? null,
            'supplier_name' => $input['supplier_name'] ?? null,
            'company_code' => $input['company_code'] ?? null,
            'invoice_code' => $input['invoice_code'] ?? null,
            'address' => $input['address'] ?? null,
            'gstin_number' => $input['gstin_number'] ?? null,
            'pan_number' => $input['pan_number'] ?? null,
            'email' => $input['email'] ?? null,
            'phone_number' => $input['phone_number'] ?? null,
            'subtotal' => $input['subtotal'] ?? 0,
            'paymentstatus' => $input['paymentstatus'] ?? 'pending',
            'discount' => $input['discount'] ?? 0,
            'tax' => $input['tax'] ?? 0,
            'total' => $input['total'] ?? 0,
            'notes' => $input['notes'] ?? null,
            'signature_code' => $input['signature_code'] ?? null,
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $db->transStart();

        $db->table('tbl_creditnote')
            ->where('creditnote_code', $input['credit_code'])
            ->update($creditNoteData);
        if (!empty($input['items']) && is_array($input['items'])) {
            $db->table('tbl_creditnote_item_relation')
                ->where('creditnote_code', $input['credit_code'])
                ->update([
                    'is_active' => 'N',
                    'updated_by' => $decodedToken->user_id ?? null,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            foreach ($input['items'] as $item) {
                $itemData = [
                    'creditnote_code' => $input['credit_code'],
                    'item_relation_code' => $item['item_relation_code'] ?? substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8),
                    'item' => $item['item'] ?? null,
                    'quantity' => $item['quantity'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'amount' => $item['amount'] ?? 0,
                    'created_by' => $decodedToken->user_id ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => 'Y'
                ];
                $db->table('tbl_creditnote_item_relation')->insert($itemData);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Failed to update credit note.');
        }

        return $this->respond([
            'status' => true,
            'message' => 'Credit note & items updated successfully'
        ]);
    }

    public function update_debit_note()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        if (!isset($input['debitnote_code'])) {
            return $this->respond(['status' => false, 'message' => 'debitnote_code is required'], 400);
        }

        $db = \Config\Database::connect();

        try {
            $db->transStart();
            $parentData = [
                'debit_note_date' => $input['debit_note_date'] ?? null,
                'company_code' => $input['company_code'] ?? null,
                'invoice_code' => $input['invoice_code'] ?? null,
                'supplier_name' => $input['supplier_name'] ?? null,
                'address' => $input['address'] ?? null,
                'gstin_number' => $input['gstin_number'] ?? null,
                'pan_number' => $input['pan_number'] ?? null,
                'email' => $input['email'] ?? null,
                'phone_number' => $input['phone_number'] ?? null,
                'notes' => $input['notes'] ?? null,
                'subtotal' => $input['subtotal'] ?? 0,
                'tax' => $input['tax'] ?? 0,
                'total' => $input['total'] ?? 0,
                'updated_by' => $decodedToken->user_id ?? null,
                'Paymentstatus' => $input['Paymentstatus'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
                'signature_code' => $input['signature_code'] ?? null,
            ];

            $db->table('tbl_debitnote')
                ->where('debitnote_code', $input['debitnote_code'])
                ->update($parentData);
            if (!empty($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $item) {
                    if (!empty($item['item_relation_code'])) {
                        $existing = $db->table('tbl_debitnote_item_relation')
                            ->where('item_relation_code', $item['item_relation_code'])
                            ->where('debitnote_code', $input['debitnote_code'])
                            ->get()
                            ->getRow();

                        if ($existing) {
                            $db->table('tbl_debitnote_item_relation')
                                ->where('item_relation_code', $item['item_relation_code'])
                                ->update([
                                    'item' => $item['item'] ?? null,
                                    'quantity' => $item['quantity'] ?? 0,
                                    'rate' => $item['rate'] ?? 0,
                                    'amount' => $item['amount'] ?? 0,
                                    'updated_by' => $decodedToken->user_id ?? null,
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'is_active' => 'Y'
                                ]);
                            continue;
                        }
                    }

                    $itemData = [
                        'debitnote_code' => $input['debitnote_code'],
                        'item_relation_code' => $item['item_relation_code'] ?? strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)),
                        'item' => $item['item'] ?? null,
                        'quantity' => $item['quantity'] ?? 0,
                        'rate' => $item['rate'] ?? 0,
                        'amount' => $item['amount'] ?? 0,
                        'created_by' => $decodedToken->user_id ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'is_active' => 'Y'
                    ];
                    $db->table('tbl_debitnote_item_relation')->insert($itemData);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to update debit note',
                    'error' => $db->error()
                ], 500);
            }

            return $this->respond([
                'status' => true,
                'message' => 'Debit Note & items updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update_budget()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $input = $this->request->getJSON(true);
        if (!isset($input['budget_code'])) {
            return $this->respond(['status' => false, 'message' => 'budget_code is required'], 400);
        }
        $data = [
            'company_code' => $input['company_code'],
            'alter_type' => $input['alter_type'],
            'budget_date' => $input['budget_date'],
            'budget_amount' => $input['budget_amount'],
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $db = \Config\Database::connect();
        if ($db->table('tbl_yearly_budget')->where('budget_code', $input['budget_code'])->update($data)) {
            return $this->respond(['status' => true, 'message' => 'Budget updated successfully']);
        } else {
            return $this->failServerError('Failed to update budget');
        }
    }
    public function update_alert()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        if (!isset($input['alerts_code'])) {
            return $this->respond(['status' => false, 'message' => 'alerts_code is required'], 400);
        }

        $data = [
            'company_code' => $input['company_code'],
            'alerts_type' => $input['alerts_type'],
            'Details' => $input['Details'] ?? null,
            'policy' => $input['policy'] ?? null,
            'start_date' => $input['start_date'],
            'due_date' => $input['due_date'],
            'amount' => $input['amount'],
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $db = \Config\Database::connect();

        if ($db->table('tbl_alerts_list')->where('alerts_code', $input['alerts_code'])->update($data)) {
            return $this->respond(['status' => true, 'message' => 'Alert updated successfully']);
        } else {
            return $this->failServerError('Failed to update alert');
        }
    }
    public function get_tax_invoice_by_code()
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
        $input = $this->request->getJSON(true);
        $invoice_code = $input['invoice_code'] ?? null;

        if (!$invoice_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Invoice code is required'
            ], 400);
        }

        // Fetch invoice with LEFT JOIN to signatureandstamp table
        $invoice = $db->table('tbl_tax_invoice as t')
            ->select('t.*, s.name as signatory_name, s.signature_img, s.stamp_img')
            ->join('tbl_signatureandstamp as s', 's.signatureandstamp_code = t.signature_code', 'left')
            ->where('t.invoice_code', $invoice_code)
            ->where('t.is_active', 'Y')
            ->where('s.is_active', 'Y') // Optional: only get active signatures
            ->get()
            ->getRowArray();

        if (!$invoice) {
            return $this->respond([
                'status' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        // Process signature image URLs if they exist
        if (!empty($invoice['signature_img'])) {
            $invoice['signature_img'] = base_url('uploads/signatures/' . $invoice['signature_img']);
        }

        if (!empty($invoice['stamp_img'])) {
            $invoice['stamp_img'] = base_url('uploads/stamps/' . $invoice['stamp_img']);
        }

        // Fetch company data
        $company = $db->table('tbl_company')
            ->where('company_code', $invoice['company_code'])
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!empty($company['logo'])) {
            $company['logo'] = base_url('companylogo/' . $company['logo']);
        }

        $invoice['company'] = $company ?? [];

        // Fetch bank data
        $bank = $db->table('tbl_bank')
            ->where('bank_code', $invoice['bank_code'])
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        $invoice['bank'] = $bank ?? [];

        // Fetch related invoice items
        $items = $db->table('tbl_tax_invoice_item_relation')
            ->where('invoice_code', $invoice_code)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        $invoice['items'] = $items;

        return $this->respond([
            'status' => true,
            'data' => $invoice
        ], 200);
    }

 public function get_purchase_order_by_code()
{
    helper(['jwtvalidate', 'url']);
    $authHeader = $this->request->getHeaderLine('Authorization');
    $decodedToken = validatejwt($authHeader);

    if (!$decodedToken) {
        return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
    }

    $db = \Config\Database::connect();
    $input = $this->request->getJSON(true);
    $purchase_code = $input['purchase_code'] ?? null;

    if (!$purchase_code) {
        return $this->respond([
            'status' => false,
            'message' => 'purchase_code is required'
        ], 400);
    }

    $order = $db->table('tbl_purchase_order as po')
        // Select all purchase order fields, company fields, and signature fields
        ->select('po.*, 
                  c.company_name, 
                  c.logo as company_logo,
                  s.signatureandstamp_code as signature_code,
                  s.name as signatory_name, 
                  s.signature_img, 
                  s.stamp_img')
        ->join('tbl_company as c', 'po.company_code = c.company_code', 'left')
        ->join('tbl_signatureandstamp as s', 's.signatureandstamp_code = po.signature_code', 'left')
        ->where('po.purchase_code', $purchase_code)
        ->where('po.is_active', 'Y')
        ->get()
        ->getRowArray();

    if (!$order) {
        return $this->respond([
            'status' => false,
            'message' => 'Purchase order not found'
        ], 404);
    }

    // ✅ Format the company logo path
    if (!empty($order['company_logo'])) {
        $order['company_logo'] = base_url('companylogo/' . $order['company_logo']);
    } else {
        $order['company_logo'] = '';
    }

    // ✅ Format the signature image path
    if (!empty($order['signature_img'])) {
        $order['signature_img'] = base_url('uploads/signatures/' . $order['signature_img']);
    } else {
        $order['signature_img'] = '';
    }

    // ✅ Format the stamp image path
    if (!empty($order['stamp_img'])) {
        $order['stamp_img'] = base_url('uploads/stamps/' . $order['stamp_img']);
    } else {
        $order['stamp_img'] = '';
    }

    // Ensure signatory_name is not null
    if (empty($order['signatory_name'])) {
        $order['signatory_name'] = 'Authorized Signatory';
    }

    // ✅ Fetch related items
    $order['items'] = $db->table('tbl_purchase_order_item_relation')
        ->where('purchase_code', $purchase_code)
        ->where('is_active', 'Y')
        ->get()
        ->getResultArray();

    return $this->respond([
        'status' => true,
        'message' => 'Purchase order fetched successfully.',
        'data' => $order
    ], 200);
}

    public function get_credit_note_by_code()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $input = $this->request->getJSON(true);
        $credit_code = $input['credit_code'] ?? null;

        if (!$credit_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Credit code is required'
            ], 400);
        }

        $creditNote = $db->table('tbl_creditnote as c')
            ->select('c.*, co.company_name, co.logo as company_logo,s.name as signatory_name, s.signature_img, s.stamp_img')
            ->join('tbl_company as co', 'c.company_code = co.company_code', 'left')
            ->join('tbl_signatureandstamp as s', 's.signatureandstamp_code = c.signature_code', 'left')

            ->where('c.creditnote_code', $credit_code)
            ->where('c.is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$creditNote) {
            return $this->respond([
                'status' => false,
                'message' => 'Credit note not found'
            ], 404);
        }

        // Fetch related items
        $items = $db->table('tbl_creditnote_item_relation')
            ->where('creditnote_code', $credit_code)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        $creditNote['items'] = $items;

        // ✅ Add formatted company logo path
        if (!empty($creditNote['company_logo'])) {
            $creditNote['company_logo'] = base_url('companylogo/' . $creditNote['company_logo']);
        }

        if (!empty($creditNote['signature_img'])) {
            $creditNote['signature_img'] = base_url('uploads/signatures/' . $creditNote['signature_img']);
        }

        if (!empty($creditNote['stamp_img'])) {
            $creditNote['stamp_img'] = base_url('uploads/stamps/' . $creditNote['stamp_img']);
        }

        return $this->respond([
            'status' => true,
            'data' => $creditNote
        ], 200);
    }

    public function get_debit_note_by_code()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $input = $this->request->getJSON(true);
        $debit_code = $input['debit_code'] ?? null;

        if (!$debit_code) {
            return $this->respond([
                'status' => false,
                'message' => 'Debit code is required'
            ], 400);
        }

        $debitNote = $db->table('tbl_debitnote as d')
            ->select('d.*, co.company_name, co.logo as company_logo')
            ->join('tbl_company as co', 'd.company_code = co.company_code', 'left')
            ->where('d.debitnote_code', $debit_code)
            ->where('d.is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$debitNote) {
            return $this->respond([
                'status' => false,
                'message' => 'Debit note not found'
            ], 404);
        }

        // Fetch related items
        $items = $db->table('tbl_debitnote_item_relation')
            ->where('debitnote_code', $debit_code)
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        $debitNote['items'] = $items;

        // ✅ Add formatted company logo path
        if (!empty($debitNote['company_logo'])) {
            $debitNote['company_logo'] = base_url('companylogo/' . $debitNote['company_logo']);
        }

        return $this->respond([
            'status' => true,
            'data' => $debitNote
        ], 200);
    }


    public function getalltaxinvoicesswithcalculation()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $company_code = $this->request->getGet('company_code');

        // 🔹 Base query: get ALL invoice columns + company name
        $builder = $db->table('tbl_tax_invoice as t')
            ->select('t.*, c.company_name')
            ->join('tbl_company as c', 't.company_code = c.company_code', 'left')
            ->where('t.is_active', 'Y');

        if ($company_code) {
            $builder->where('t.company_code', $company_code);
        }

        $invoices = $builder->get()->getResultArray();

        // 🔹 Process each invoice
        foreach ($invoices as &$invoice) {
            $invoiceCode = $invoice['invoice_code'];

            // --- CREDIT NOTES (Add)
            $creditBuilder = $db->table('tbl_creditnote as cr')
                ->selectSum('cri.amount', 'credit_amount')
                ->join('tbl_creditnote_item_relation as cri', 'cri.creditnote_code = cr.creditnote_code', 'left')
                ->where('cr.invoice_code', $invoiceCode)
                ->where('cr.is_active', 'Y');
            $creditResult = $creditBuilder->get()->getRowArray();
            $creditAmount = $creditResult['credit_amount'] ?? 0;

            // --- DEBIT NOTES (Subtract)
            $debitBuilder = $db->table('tbl_debitnote as dr')
                ->selectSum('dri.amount', 'debit_amount')
                ->join('tbl_debitnote_item_relation as dri', 'dri.debitnote_code = dr.debitnote_code', 'left')
                ->where('dr.invoice_code', $invoiceCode)
                ->where('dr.is_active', 'Y');
            $debitResult = $debitBuilder->get()->getRowArray();
            $debitAmount = $debitResult['debit_amount'] ?? 0;

            // --- Final Amount
            $invoice['credit_amount'] = (float) $creditAmount;
            $invoice['debit_amount'] = (float) $debitAmount;
            $invoice['final_amount'] = (float) $invoice['total'] + (float) $creditAmount - (float) $debitAmount;
        }

        return $this->respond([
            'status' => true,
            'data' => $invoices
        ], 200);
    }
    public function getalltaxinvoicesbycompany()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $input = $this->request->getJSON(true);
        $company_code = $input['company_code'] ?? null;
        if (!$company_code) {
            return $this->respond([
                'status' => false,
                'message' => 'company_code is required'
            ], 400);
        }

        // 🔹 Get all invoices for the company
        $builder = $db->table('tbl_tax_invoice as t')
            ->select('t.*, c.company_name')
            ->join('tbl_company as c', 't.company_code = c.company_code', 'left')
            ->where('t.is_active', 'Y')
            ->where('t.company_code', $company_code);

        $invoices = $builder->get()->getResultArray();
        log_message('info', 'Fetched invoices ' . print_r($invoices, true));

        if (empty($invoices)) {
            return $this->respond([
                'status' => false,
                'message' => 'No invoices found for the given company'
            ], 404);
        }

        // 🔹 Process each invoice
        foreach ($invoices as &$invoice) {
            $invoiceCode = $invoice['invoice_code'];

            // --- CREDIT NOTES (Add)
            $creditBuilder = $db->table('tbl_creditnote as cr')
                ->selectSum('cri.amount', 'credit_amount')
                ->join('tbl_creditnote_item_relation as cri', 'cri.creditnote_code = cr.creditnote_code', 'left')
                ->where('cr.invoice_code', $invoiceCode)
                ->where('cr.is_active', 'Y');
            $creditResult = $creditBuilder->get()->getRowArray();
            $creditAmount = $creditResult['credit_amount'] ?? 0;

            // --- DEBIT NOTES (Subtract)
            $debitBuilder = $db->table('tbl_debitnote as dr')
                ->selectSum('dri.amount', 'debit_amount')
                ->join('tbl_debitnote_item_relation as dri', 'dri.debitnote_code = dr.debitnote_code', 'left')
                ->where('dr.invoice_code', $invoiceCode)
                ->where('dr.is_active', 'Y');
            $debitResult = $debitBuilder->get()->getRowArray();
            $debitAmount = $debitResult['debit_amount'] ?? 0;

            // --- Final Amount
            $invoice['credit_amount'] = (float) $creditAmount;
            $invoice['debit_amount'] = (float) $debitAmount;
            $invoice['final_amount'] = (float) $invoice['total'] + (float) $creditAmount - (float) $debitAmount;
        }

        return $this->respond([
            'status' => true,
            'data' => $invoices
        ], 200);
    }

    public function updateutr_no()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        $input = $this->request->getJSON(true);

        if (!isset($input['invoice_code'])) {
            return $this->respond(['status' => false, 'message' => 'invoice_code is required'], 400);
        }
        $data = [
            'utr_no' => $input['utr_no'] ?? null,
            'payment_status' => $input['payment_status'] ?? null,
            'updated_by' => $decodedToken->user_id ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $db = \Config\Database::connect();
        if ($db->table('tbl_tax_invoice')->where('invoice_code', $input['invoice_code'])->update($data)) {
            return $this->respond(['status' => true, 'message' => 'UTR No updated successfully']);
        } else {
            return $this->failServerError('Failed to update UTR No');
        }
    }

    public function updatePaymentStatus()
    {
        $request = service('request');
        $db = \Config\Database::connect();
        $input = $this->request->getJSON(true);

        $invoiceCode = $input['invoice_code'] ?? null;
        $status = $input['payment_status'] ?? null;
        $utrNo = $input['utr_no'] ?? null;
        $paidAmount = $input['paid_amount'] ?? 0;
        $paymentDate = $input['payment_date'] ?? null;
        $previousPaidAmount = $input['previous_paid_amount'] ?? 0;
        $updatedBy = $input['updated_by'] ?? null;

        // Validation
        if (empty($invoiceCode) || empty($status)) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Missing required fields: invoice_code or payment_status',
            ]);
        }

        if ($status !== 'Pending' && empty($utrNo)) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'UTR number is required for payment status updates',
            ]);
        }

        if (($status === 'Partially Paid' || $status === 'Paid') && $paidAmount <= 0) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Valid paid amount is required for payment status',
            ]);
        }

        // Check if invoice exists
        $invoice = $db->table('tbl_tax_invoice')->where('invoice_code', $invoiceCode)->get()->getRowArray();

        if (!$invoice) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Invoice not found',
            ]);
        }

        // Calculate total paid amount
        $totalPaidAmount = $previousPaidAmount + $paidAmount;
        $finalAmount = $invoice['final_amount'] ?? $invoice['total'];

        // Auto-update status if partially paid amount equals or exceeds total
        if ($status === 'Partially Paid' && $totalPaidAmount >= $finalAmount) {
            $status = 'Paid';
            $totalPaidAmount = $finalAmount; // Cap at final amount
        }

        // Update invoice data
        $updateData = [
            'payment_status' => $status,
            'utr_no' => $utrNo,
            'paid_amount' => $totalPaidAmount,
            'payment_date' => $paymentDate ? date('Y-m-d', strtotime($paymentDate)) : null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updatedBy ?? null,
        ];

        $builder = $db->table('tbl_tax_invoice');
        $builder->where('invoice_code', $invoiceCode);
        $updated = $builder->update($updateData);

        if ($updated) {
            // Insert payment history record
            $this->insertPaymentHistory($invoiceCode, $paidAmount, $utrNo, $paymentDate, $status, $updatedBy);

            return $this->response->setJSON([
                'status' => true,
                'message' => 'Payment status updated successfully',
                'data' => [
                    'invoice_code' => $invoiceCode,
                    'payment_status' => $status,
                    'paid_amount' => $totalPaidAmount,
                    'utr_no' => $utrNo,
                    'payment_date' => $paymentDate,
                    'final_amount' => $finalAmount,
                    'balance_due' => $finalAmount - $totalPaidAmount
                ]
            ]);
        } else {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Failed to update payment status',
            ]);
        }
    }

    // New method to track payment history
    private function insertPaymentHistory($invoiceCode, $paidAmount, $utrNo, $paymentDate, $status, $updatedBy)
    {
        $db = \Config\Database::connect();

        $historyData = [
            'invoice_code' => $invoiceCode,
            'paid_amount' => $paidAmount,
            'utr_number' => $utrNo,
            'payment_date' => $paymentDate ? date('Y-m-d', strtotime($paymentDate)) : date('Y-m-d'),
            'payment_status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $updatedBy ?? null,
        ];

        $db->table('tbl_payment_history')->insert($historyData);
        return $db->insertID();
    }

    // New method to get payment history
    public function getPaymentHistory()
    {
        $request = service('request');
        $db = \Config\Database::connect();
        $input = $this->request->getJSON(true);

        $invoiceCode = $input['invoice_code'] ?? null;

        if (empty($invoiceCode)) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Invoice code is required',
            ]);
        }

        $history = $db->table('tbl_payment_history')
            ->where('invoice_code', $invoiceCode)
            ->orderBy('payment_date', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'status' => true,
            'message' => 'Payment history retrieved successfully',
            'data' => $history
        ]);
    }

    public function updatereimarsmentpaymentstatus()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);

        if (!isset($input['payment_code'])) {
            return $this->respond(['status' => false, 'message' => 'payment_code is required'], 400);
        }

        $db = \Config\Database::connect();

        // Start transaction
        $db->transStart();

        try {
            // Get current payment record
            $currentPayment = $db->table('tbl_payment_records')
                ->where('payment_code', $input['payment_code'])
                ->get()
                ->getRow();

            if (!$currentPayment) {
                return $this->respond(['status' => false, 'message' => 'Payment not found'], 404);
            }

            $totalAmount = $currentPayment->amount;
            $paidAmount = $input['paid_amount'] ?? 0;
            $reimbursementType = $input['reimbursement_type'] ?? 'full';

            // Calculate new totals
            if ($reimbursementType === 'full') {
                $newPaidAmount = $totalAmount;
                $newRemainingAmount = 0;
                $newReimbursementStatus = 'Y';
            } else {
                // For partial payment, add to existing paid amount
                $existingPaidAmount = $currentPayment->paid_amount ?? 0;
                $newPaidAmount = $existingPaidAmount + $paidAmount;
                $newRemainingAmount = $totalAmount - $newPaidAmount;

                // If after this payment, amount becomes fully paid
                if ($newPaidAmount >= $totalAmount) {
                    $newReimbursementStatus = 'Y';
                    $newPaidAmount = $totalAmount;
                    $newRemainingAmount = 0;
                    $reimbursementType = 'full';
                } else {
                    $newReimbursementStatus = 'Y'; // Still mark as reimbursed but with remaining
                }
            }

            // Update main payment record
            $updateData = [
                'reimbursement_status' => $newReimbursementStatus,
                'reimbursement_type' => $reimbursementType,
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => $newRemainingAmount,
                'updated_by' => $input['updated_by'] ?? $decodedToken->user_id,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Add optional fields if provided
            if (isset($input['utr_number'])) {
                $updateData['utr_number'] = $input['utr_number'];
            }
            if (isset($input['reimbursement_date'])) {
                $updateData['reimbursement_date'] = $input['reimbursement_date'];
            }
            if (isset($input['reimbursement_notes'])) {
                $updateData['reimbursement_notes'] = $input['reimbursement_notes'];
            }

            $result = $db->table('tbl_payment_records')
                ->where('payment_code', $input['payment_code'])
                ->update($updateData);

            // Create payment history record
            if ($result && $paidAmount > 0) {
                $paymentHistoryData = [
                    'payment_code' => $input['payment_code'],
                    'payment_amount' => $paidAmount,
                    'payment_date' => $input['reimbursement_date'] ?? date('Y-m-d'),
                    'utr_number' => $input['utr_number'] ?? null,
                    'payment_type' => $reimbursementType,
                    'notes' => $input['reimbursement_notes'] ?? null,
                    'created_by' => $input['updated_by'] ?? $decodedToken->user_id,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $db->table('tbl_payment_historyofreimbraisment')->insert($paymentHistoryData);
            }

            $db->transComplete();

            if ($result) {
                // Get updated payment data
                $updatedPayment = $db->table('tbl_payment_records')
                    ->where('payment_code', $input['payment_code'])
                    ->get()
                    ->getRow();

                return $this->respond([
                    'status' => true,
                    'message' => 'Payment status updated successfully',
                    'data' => $updatedPayment
                ]);
            } else {
                return $this->failServerError('Failed to update payment status');
            }
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->failServerError('Database error: ' . $e->getMessage());
        }
    }


    public function delete_invoice()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $invoice_code = $input['invoice_no'] ?? null;

        if (!$invoice_code) {
            return $this->respond(['status' => false, 'message' => 'Invoice number is required'], 400);
        }

        $db = \Config\Database::connect();
        $updated_by = $decodedToken->user_id ?? null;

        $db->transStart();

        try {
            // Check if invoice exists
            $invoice = $db->table('tbl_tax_invoice')
                ->where('invoice_code', $invoice_code)
                ->where('is_active', 'Y')
                ->get()
                ->getRow();

            if (!$invoice) {
                return $this->respond(['status' => false, 'message' => 'Invoice not found'], 404);
            }

            // Soft delete invoice items first
            $db->table('tbl_tax_invoice_item_relation')
                ->where('invoice_code', $invoice_code)
                ->update([
                    'is_active' => 'N',
                    'updated_by' => $updated_by,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            // Soft delete the main invoice
            $result = $db->table('tbl_tax_invoice')
                ->where('invoice_code', $invoice_code)
                ->update([
                    'is_active' => 'N',
                    'updated_by' => $updated_by,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            $db->transComplete();

            if ($result) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Invoice deleted successfully'
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to delete invoice'
                ], 500);
            }
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->failServerError('Failed to delete invoice: ' . $e->getMessage());
        }
    }


    public function delete_purchase_order()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $purchase_code = $input['purchase_code'] ?? null;

        if (!$purchase_code) {
            return $this->respond(['status' => false, 'message' => 'Purchase code is required'], 400);
        }

        $db = \Config\Database::connect();
        $updated_by = $decodedToken->user_id ?? null;

        $db->transStart();

        try {
            // Check if purchase order exists
            $purchase = $db->table('tbl_purchase_order')
                ->where('purchase_code', $purchase_code)
                ->where('is_active', 'Y')
                ->get()
                ->getRow();

            if (!$purchase) {
                return $this->respond(['status' => false, 'message' => 'Purchase order not found'], 404);
            }

            // Soft delete purchase order items first
            $db->table('tbl_purchase_order_item_relation')
                ->where('purchase_code', $purchase_code)
                ->update([
                    'is_active' => 'N',
                    'updated_by' => $updated_by,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            // Soft delete the main purchase order
            $result = $db->table('tbl_purchase_order')
                ->where('purchase_code', $purchase_code)
                ->update([
                    'is_active' => 'N',
                    'updated_by' => $updated_by,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            $db->transComplete();

            if ($result) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Purchase order deleted successfully'
                ]);
            } else {
                return $this->respond([
                    'status' => false,
                    'message' => 'Failed to delete purchase order'
                ], 500);
            }
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->failServerError('Failed to delete purchase order: ' . $e->getMessage());
        }
    }
}
