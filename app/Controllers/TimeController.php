<?php

namespace App\Controllers;

use DateTime;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use DateTimeZone;
use Exception;

class TimeController extends BaseController
{
    protected $db;
    use ResponseTrait;

    // ✅ Calculate distance in meters
    private function haversineGreatCircleDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // meters
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo   = deg2rad($lat2);
        $lonTo   = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    // ✅ Check Punch Permission based on branch
    // private function checkPunchPermission($userCode, $lat, $lng)
    // {
    //     $this->db = \Config\Database::connect();

    //     // Get user with branch
    //     $user = $this->db->table('tbl_register')
    //         ->select('Designations, branch_code')
    //         ->where('user_code', $userCode) // from tbl_register
    //         ->get()
    //         ->getRow();

    //     if (!$user) {
    //         return ['status' => false, 'message' => 'User not found'];
    //     }

    //     // ✅ If designation is DESGCPL021 -> no location restriction
    //     if ($user->Designations === 'DESGCPL021') {
    //         return ['status' => true, 'distance' => null];
    //     }

    //     // ✅ Get branch location
    //     $branch = $this->db->table('tbl_branch_mst')
    //         ->select('latitude, longitude')
    //         ->where('branch_code', $user->branch_code)
    //         ->get()
    //         ->getRow();

    //     if (!$branch || !$branch->latitude || !$branch->longitude) {
    //         return ['status' => false, 'message' => 'Branch location not set'];
    //     }

    //     // ✅ Calculate distance
    //     $distance = $this->haversineGreatCircleDistance(
    //         $lat,
    //         $lng,
    //         $branch->latitude,
    //         $branch->longitude
    //     );

    //     // Change 200m limit to 100m
    //     if ($distance <= 100) {
    //         return ['status' => true, 'distance' => round($distance, 2)];
    //     } else {
    //         return [
    //             'status' => false,
    //             'message' => 'You are outside allowed location (100m limit)',
    //             'distance' => round($distance, 2)
    //         ];
    //     }
    // }
 private function checkPunchPermission($userCode, $lat, $lng)
{
    $this->db = \Config\Database::connect();

    // Get user details
    $user = $this->db->table('tbl_register')
        ->select('department_code, branch_code, Designations')
        ->where('user_code', $userCode)
        ->get()
        ->getRow();

    if (!$user) {
        return ['status' => false, 'message' => 'User not found'];
    }

    // ✅ If designation is DESGCPL021, always allow (no restriction)
    if ($user->Designations === 'DESGCPL021') {
        return ['status' => true, 'distance' => null, 'message' => 'No location restriction (Designation)'];
    }

    // ❌ Apply location restriction only for DEPTM004 or DEPTM007
    if (in_array($user->department_code, ['DEPTM004', 'DEPTM007'])) {

        // Get branch location
        $branch = $this->db->table('tbl_branch_mst')
            ->select('latitude, longitude')
            ->where('branch_code', $user->branch_code)
            ->get()
            ->getRow();

        if (!$branch || !$branch->latitude || !$branch->longitude) {
            return ['status' => false, 'message' => 'Branch location not set'];
        }

        // Calculate distance
        $distance = $this->haversineGreatCircleDistance(
            $lat,
            $lng,
            $branch->latitude,
            $branch->longitude
        );

        // ✅ Allow within 100 meters
        if ($distance <= 100) {
            return ['status' => true, 'distance' => round($distance, 2)];
        } else {
            return [
                'status' => false,
                'message' => 'You are outside allowed location (100m limit)',
                'distance' => round($distance, 2)
            ];
        }
    }

    // ✅ All other departments can punch from anywhere
    return ['status' => true, 'distance' => null, 'message' => 'No location restriction (Department)'];
}

    // ✅ Punch In
    public function punch_in()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond(['status' => false, 'message' => 'User ID not found in token'], 400);
        }

        $lat = $this->request->getPost('latitude');
        $lng = $this->request->getPost('longitude');

        if (!$lat || !$lng) {
            return $this->respond(['status' => false, 'message' => 'Location required'], 400);
        }

        // ✅ Check permission
        $permission = $this->checkPunchPermission($userCode, $lat, $lng);
        if (!$permission['status']) {
            return $this->respond($permission, 403);
        }

        try {
            $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            $current_time = $dt->format('H:i:s');
            $today_date   = $dt->format('Y-m-d');

            $data = [
                'user_ref_code' => $userCode,   // ✅ matches tbl_time
                'punch_in'      => $current_time,
                'today_date'    => $today_date,
                'latitude_in'   => $lat,
                'longitude_in'  => $lng,
                'is_active'     => 'Y'
            ];

            $this->db = \Config\Database::connect();
            $builder = $this->db->table('tbl_time');
            $builder->insert($data);

            return $this->respond([
                'status'   => true,
                'message'  => 'Punch in recorded',
                'punch_in' => $current_time,
                'distance' => $permission['distance']
            ], 200);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ✅ Punch Out
    public function punch_out()
    {
        helper('jwtvalidate');
        $authHeader = $this->request->getHeaderLine('Authorization');
        $decodedToken = validatejwt($authHeader);

        if (!$decodedToken) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing JWT'], 401);
        }

        $userCode = $decodedToken->user_id ?? null;
        if (!$userCode) {
            return $this->respond(['status' => false, 'message' => 'User ID not found in token'], 400);
        }

        $lat = $this->request->getPost('latitude');
        $lng = $this->request->getPost('longitude');

        if (!$lat || !$lng) {
            return $this->respond(['status' => false, 'message' => 'Location required'], 400);
        }

        // ✅ Check permission
        $permission = $this->checkPunchPermission($userCode, $lat, $lng);
        if (!$permission['status']) {
            return $this->respond($permission, 403);
        }

        try {
            $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            $current_time = $dt->format('H:i:s');
            $today_date   = $dt->format('Y-m-d');

            $this->db = \Config\Database::connect();
            $builder = $this->db->table('tbl_time');
            $builder->where('user_ref_code', $userCode)   // ✅ matches tbl_time
                ->where('today_date', $today_date)
                ->update([
                    'punch_out'     => $current_time,
                    'latitude_out'  => $lat,
                    'longitude_out' => $lng
                ]);

            if ($this->db->affectedRows() === 0) {
                return $this->respond(['status' => false, 'message' => 'No punch in record found for today'], 404);
            }

            return $this->respond([
                'status'    => true,
                'message'   => 'Punch out recorded',
                'punch_out' => $current_time,
                'distance'  => $permission['distance']
            ], 200);
        } catch (DatabaseException $e) {
            return $this->respond(['status' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return $this->respond(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    // ✅ Convert Google Maps short URL to latitude & longitude
public function get_latlng_from_url()
{
    $input = $this->request->getJSON(true); // decode JSON to array
    $url   = $input['maps_url'] ?? null;

    if (!$url) {
        return $this->respond(['status' => false, 'message' => 'maps_url is required'], 400);
    }

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if (preg_match('/@([-0-9.]+),([-0-9.]+)/', $finalUrl, $matches)) {
            return $this->respond([
                'status'   => true,
                'latitude' => $matches[1],
                'longitude'=> $matches[2]
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Could not extract coordinates from URL'
            ], 400);
        }
    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

}
