<?php

function buildUsersFilters(array $get): array
{
    $whereClause = [];
    $params = [];
    $types = '';

    if (isset($get['user_type']) && $get['user_type'] != '') {
        $whereClause[] = 'user_type = ?';
        $params[] = $get['user_type'];
        $types .= 's';
    }

    if (isset($get['grade']) && $get['grade'] != '') {
        $whereClause[] = 'grade = ?';
        $params[] = $get['grade'];
        $types .= 's';
    }

    if (isset($get['hide_incomplete']) && $get['hide_incomplete'] == '1') {
        $whereClause[] = "(email IS NOT NULL AND email != '' AND first_name IS NOT NULL AND first_name != '')";
    }

    if (isset($get['search']) && $get['search'] != '') {
        $searchTerm = '%' . $get['search'] . '%';
        $whereClause[] = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR school LIKE ? OR city LIKE ?)';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= 'sssss';
    }

    if (isset($get['joined']) && $get['joined'] != '') {
        $today = date('Y-m-d');
        switch ($get['joined']) {
            case 'today':
                $whereClause[] = 'DATE(created_at) = ?';
                $params[] = $today;
                $types .= 's';
                break;
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $whereClause[] = 'DATE(created_at) = ?';
                $params[] = $yesterday;
                $types .= 's';
                break;
            case 'this_week':
                $weekStart = date('Y-m-d', strtotime('monday this week'));
                $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                $whereClause[] = 'DATE(created_at) BETWEEN ? AND ?';
                $params[] = $weekStart;
                $params[] = $weekEnd;
                $types .= 'ss';
                break;
            case 'this_month':
                $monthStart = date('Y-m-01');
                $monthEnd = date('Y-m-t');
                $whereClause[] = 'DATE(created_at) BETWEEN ? AND ?';
                $params[] = $monthStart;
                $params[] = $monthEnd;
                $types .= 'ss';
                break;
            case 'custom':
                if (isset($get['start_date']) && isset($get['end_date']) && $get['start_date'] != '' && $get['end_date'] != '') {
                    $whereClause[] = 'DATE(created_at) BETWEEN ? AND ?';
                    $params[] = $get['start_date'];
                    $params[] = $get['end_date'];
                    $types .= 'ss';
                }
                break;
        }
    }

    if (isset($get['school']) && $get['school'] != '') {
        $whereClause[] = 'school LIKE ?';
        $params[] = '%' . $get['school'] . '%';
        $types .= 's';
    }

    if (isset($get['city']) && $get['city'] != '') {
        $whereClause[] = 'city LIKE ?';
        $params[] = '%' . $get['city'] . '%';
        $types .= 's';
    }

    return [
        'where' => $whereClause,
        'params' => $params,
        'types' => $types,
    ];
}
