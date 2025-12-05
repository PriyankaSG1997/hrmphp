<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;

class EventController extends BaseController
{
    use ResponseTrait;

    public function add_event()
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
            'event_name'        => 'required',
            'event_description' => 'required',
            'from_date'         => 'required|valid_date',
            'to_date'           => 'required|valid_date',
            'event_location'    => 'required',
        ];

        if (!$this->validate($rules, $input)) {
            return $this->respond(['status' => false, 'message' => 'Validation Error', 'errors' => $this->validator->getErrors()], 400);
        }

        if (strtotime($input['to_date']) < strtotime($input['from_date'])) {
            return $this->respond(['status' => false, 'message' => 'Validation Error', 'errors' => ['to_date' => 'The to date must be on or after the from date.']], 400);
        }

        $event_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
        $data = [
            'event_code'        => $event_code,
            'event_name'        => $input['event_name'],
            'event_description' => $input['event_description'],
            'from_date'         => $input['from_date'],
            'to_date'           => $input['to_date'],
            'event_location'    => $input['event_location'],
            'is_active'         => 'Y',
            'created_by'        => $created_by
        ];

        $db = \Config\Database::connect();
        try {
            $db->table('tbl_event_mst')->insert($data);
            return $this->respond(['status' => true, 'message' => 'Event added successfully', 'event_code' => $event_code], 201);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function update_event()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $updated_by = $decodedToken->user_id ?? null;

        $input = $this->request->getJSON(true);

        $event_code = $input['event_code'] ?? null;
        if (empty($event_code)) {
            return $this->respond(['status' => false, 'message' => 'Event code is required'], 400);
        }

        $db = \Config\Database::connect();
        $event = $db->table('tbl_event_mst')->where('event_code', $event_code)->get()->getRowArray();

        if (!$event) {
            return $this->respond(['status' => false, 'message' => 'Event not found'], 404);
        }

        if ($event['is_active'] === 'N') {
            return $this->respond(['status' => false, 'message' => 'This event is inactive and cannot be updated'], 400);
        }

        $data = [];
        $inputFields = ['event_name', 'event_description', 'from_date', 'to_date', 'event_location', 'is_active'];
        foreach ($inputFields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (isset($data['from_date']) && isset($data['to_date'])) {
            if (strtotime($data['to_date']) < strtotime($data['from_date'])) {
                return $this->respond(['status' => false, 'message' => 'Validation Error', 'errors' => ['to_date' => 'The to date must be on or after the from date.']], 400);
            }
        }

        if (empty($data)) {
            return $this->respond(['status' => false, 'message' => 'No data provided to update'], 400);
        }

        $data['updated_by'] = $updated_by;
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $db->table('tbl_event_mst')->where('event_code', $event_code)->update($data);
            if ($db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Event updated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Event not found or no change'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }

    public function get_all_events()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $db = \Config\Database::connect();
        $events = $db->table('tbl_event_mst')
            ->where('is_active', 'Y')
            ->get()
            ->getResultArray();

        return $this->respond(['status' => true, 'data' => $events], 200);
    }

    public function get_event_by_id()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $request = $this->request->getJSON(true);
        $event_code = $request['event_code'] ?? null;

        if (!$event_code) {
            return $this->respond(['status' => false, 'message' => 'Event code is required'], 400);
        }

        $db = \Config\Database::connect();
        $event = $db->table('tbl_event_mst')
            ->where('event_code', $event_code)
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();

        if (!$event) {
            return $this->respond(['status' => false, 'message' => 'Event not found or is inactive'], 404);
        }

        return $this->respond(['status' => true, 'data' => $event], 200);
    }

    public function delete_event()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $input = $this->request->getJSON(true);
        $event_code = $input['event_code'] ?? null;

        if (!$event_code) {
            return $this->respond(['status' => false, 'message' => 'Event code is required'], 400);
        }

        $db = \Config\Database::connect();
        try {
            $db->table('tbl_event_mst')->where('event_code', $event_code)->update([
                'is_active' => 'N'
            ]);

            if ($db->affectedRows() > 0) {
                return $this->respond(['status' => true, 'message' => 'Event deactivated successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Event not found'], 404);
            }
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()], 500);
        }
    }
    public function get_upcoming_events()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);
        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }
        try {
            $db = \Config\Database::connect();
            $today = date('Y-m-d');
            $events = $db->table('tbl_event_mst')
                ->where('is_active', 'Y')
                ->where('DATE(to_date) >=', $today)
                ->orderBy('from_date', 'ASC')
                ->get()
                ->getResultArray();
            return $this->respond([
                'status'  => true,
                'message' => 'Upcoming events fetched successfully.',
                'count'   => count($events),
                'data'    => $events
            ], 200);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error fetching events: ' . $e->getMessage()
            ], 500);
        }
    }
}
