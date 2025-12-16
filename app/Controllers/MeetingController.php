<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;

class MeetingController extends BaseController
{
    use ResponseTrait;


    public function add_meeting()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $created_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);

        $rules = [
            'meeting_link' => 'required',
            'meeting_date' => 'required|valid_date',
            'meeting_time' => 'required',
            'host' => 'required',
            'subject' => 'required',
            'employee_ids' => 'required',
        ];

        if (!$this->validate($rules, $input)) {
            return $this->respond(['status' => false, 'errors' => $this->validator->getErrors()], 400);
        }

        $data = [
            'meeting_link' => $input['meeting_link'],
            'meeting_date' => $input['meeting_date'],
            'meeting_time' => $input['meeting_time'],
            'host' => $input['host'],
            'subject' => $input['subject'],
            'client_involve' => $input['client_involve'] ?? null,
            'employee_ids' => json_encode($input['employee_ids']), // array → json
            'project_code'=> $input['project_code'] ?? null,
            // 'status' => $input['status'] ?? 'Pending',
            'created_by' => $created_by,
        ];

        $db = \Config\Database::connect();
        try {
            $db->table('tbl_meetings')->insert($data);
            return $this->respond(['status' => true, 'message' => 'Meeting added successfully'], 201);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Update Meeting
    public function update_meeting()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $updated_by = $decodedToken->user_id ?? null;
        $input = $this->request->getJSON(true);
        $meeting_id = $input['meeting_id'] ?? null;

        if (!$meeting_id) {
            return $this->respond(['status' => false, 'message' => 'Meeting ID required'], 400);
        }

        $db = \Config\Database::connect();
        $meeting = $db->table('tbl_meetings')->where('meeting_id', $meeting_id)->get()->getRowArray();

        if (!$meeting) {
            return $this->respond(['status' => false, 'message' => 'Meeting not found'], 404);
        }

        $data = [];
        $fields = ['meeting_link', 'meeting_date', 'meeting_time', 'host', 'subject', 'client_involve', 'employee_ids', 'status','project_code', 'is_active'];
        foreach ($fields as $f) {
            if (isset($input[$f])) {
                $data[$f] = ($f == 'employee_ids') ? json_encode($input[$f]) : $input[$f];
            }
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $db->table('tbl_meetings')->where('meeting_id', $meeting_id)->update($data);
            return $this->respond(['status' => true, 'message' => 'Meeting updated successfully'], 200);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Delete Meeting (soft delete)
    public function delete_meeting()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $meeting_id = $input['meeting_id'] ?? null;

        if (!$meeting_id) {
            return $this->respond(['status' => false, 'message' => 'Meeting ID required'], 400);
        }

        $db = \Config\Database::connect();
        try {
            $db->table('tbl_meetings')->where('meeting_id', $meeting_id)->update(['is_active' => 'N']);
            return $this->respond(['status' => true, 'message' => 'Meeting deleted successfully'], 200);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Get All Meetings
    public function get_all_meetings()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $meetings = $db->table('tbl_meetings')
            ->select('tbl_meetings.*')
            ->where('tbl_meetings.is_active', 'Y')
            ->orderBy('meeting_date', 'DESC')
            ->get()
            ->getResultArray();

        // Process each meeting to get employee names
        foreach ($meetings as &$meeting) {
            $employeeIds = json_decode($meeting['employee_ids'], true);

            if (!empty($employeeIds)) {
                $employees = $db->table('tbl_register') // Replace with your actual employee table name
                    ->select('user_code, First_Name, Middle, Last_Name') // Replace with actual column names
                    ->whereIn('user_code', $employeeIds)
                    ->get()
                    ->getResultArray();

                $meeting['employee_details'] = $employees;
            } else {
                $meeting['employee_details'] = [];
            }
        }

        return $this->respond(['status' => true, 'data' => $meetings], 200);
    }

    // Get Meeting By ID
    public function get_meeting_by_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $meeting_id = $input['meeting_id'] ?? null;

        if (!$meeting_id) {
            return $this->respond(['status' => false, 'message' => 'Meeting ID required'], 400);
        }

        $db = \Config\Database::connect();
        $meeting = $db->table('tbl_meetings')->where('meeting_id', $meeting_id)->where('is_active', 'Y')->get()->getRowArray();

        if (!$meeting) {
            return $this->respond(['status' => false, 'message' => 'Meeting not found'], 404);
        }

        return $this->respond(['status' => true, 'data' => $meeting], 200);
    }

    public function get_meetings_by_employee_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $employee_id = $input['employee_id'] ?? null;

        if (!$employee_id) {
            return $this->respond(['status' => false, 'message' => 'Employee ID required'], 400);
        }

        $db = \Config\Database::connect();

        // Since employee_ids is stored as JSON, use LIKE to check presence
        $meetings = $db->table('tbl_meetings')
            ->where('is_active', 'Y')
            ->like('employee_ids', '"' . $employee_id . '"') // match inside JSON array
            ->orderBy('meeting_date', 'DESC')
            ->get()
            ->getResultArray();

        // decode employee_ids back to array
        foreach ($meetings as &$m) {
            $m['employee_ids'] = json_decode($m['employee_ids'], true);
        }

        return $this->respond(['status' => true, 'data' => $meetings], 200);
    }

    public function get_upcoming_meetings()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s'); // current datetime

        $meetings = $db->table('tbl_meetings')
            ->where('is_active', 'Y')
            ->where("CONCAT(meeting_date, ' ', meeting_time) >", $now)
            ->orderBy('meeting_date', 'ASC')
            ->orderBy('meeting_time', 'ASC')
            ->get()
            ->getResultArray();

        // Decode employee_ids into array
        foreach ($meetings as &$m) {
            $m['employee_ids'] = json_decode($m['employee_ids'], true);
        }

        return $this->respond(['status' => true, 'data' => $meetings], 200);
    }
}
