<?php

namespace App\Models;

use CodeIgniter\Model;

class HomeModel extends Model
{
    public function softDeleteRecord($table, $where)
    {
        if (empty($table) || empty($where)) {
            return false;
        }

        $builder = $this->db->table($table);
        $builder->where($where);

        // Special case for tbl_policy
        if ($table === 'tbl_policy') {
            return $builder->update(['status' => 'Not Active']);
        }
        if ($table === 'tbl_vacancy') {
            return $builder->update(['status' => 'N']);
        }

        return $builder->update(['is_active' => 'N']);
    }
}
