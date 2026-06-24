<?php

if (!function_exists('getFuzzySchoolKey')) {
    function getFuzzySchoolKey($schoolName)
    {
        $name = trim(strtolower($schoolName));
        $name = preg_replace('/[^\w\s]/', '', $name);
        $stopWords = ['school', 'schools', 'international', 'public', 'private', 'high', 'sr', 'sec', 'secondary', 'primary', 'vadodara', 'vadhodara', 'udaipur', 'co', 'ed'];

        $words = preg_split('/\s+/', $name);
        $filteredWords = array_filter($words, function ($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) > 0;
        });

        $key = implode(' ', $filteredWords);
        return !empty($key) ? $key : $name;
    }
}

if (!function_exists('buildFuzzySchoolGroups')) {
    function buildFuzzySchoolGroups(mysqli $connect): array
    {
        $fuzzyGroups = [];
        $result = $connect->query("SELECT DISTINCT school FROM users WHERE school IS NOT NULL AND school != ''");

        if (!$result) {
            return $fuzzyGroups;
        }

        while ($row = $result->fetch_assoc()) {
            $orig = trim($row['school']);
            $norm = trim(strtolower($orig));
            $key = getFuzzySchoolKey($orig);

            if (!isset($fuzzyGroups[$key])) {
                $fuzzyGroups[$key] = [
                    'display' => $orig,
                    'original_names' => [],
                ];
            }

            $fuzzyGroups[$key]['original_names'][] = $norm;

            if (strlen($orig) > strlen($fuzzyGroups[$key]['display'])) {
                $fuzzyGroups[$key]['display'] = $orig;
            }
        }

        uasort($fuzzyGroups, function ($a, $b) {
            return strcasecmp($a['display'], $b['display']);
        });

        return $fuzzyGroups;
    }
}

if (!function_exists('getSchoolNamesForFuzzyKey')) {
    function getSchoolNamesForFuzzyKey(string $fuzzyKey, array $fuzzyGroups): array
    {
        if (isset($fuzzyGroups[$fuzzyKey])) {
            return array_values(array_unique($fuzzyGroups[$fuzzyKey]['original_names']));
        }

        return [trim(strtolower($fuzzyKey))];
    }
}

if (!function_exists('buildQuizStudentScoreFilters')) {
    function buildQuizStudentScoreFilters(array $get, array $fuzzyGroups): array
    {
        $whereClause = [
            'uqa.score IS NOT NULL',
            "u.school IS NOT NULL AND u.school != ''",
        ];
        $params = [];
        $types = '';

        $startDate = !empty($get['start_date']) ? $get['start_date'] : null;
        $endDate = !empty($get['end_date']) ? $get['end_date'] : null;

        if ($startDate && $endDate) {
            $whereClause[] = 'DATE(uqa.start_time) BETWEEN ? AND ?';
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= 'ss';
        }

        if (!empty($get['school'])) {
            $schoolNames = getSchoolNamesForFuzzyKey($get['school'], $fuzzyGroups);
            if (!empty($schoolNames)) {
                $placeholders = implode(',', array_fill(0, count($schoolNames), '?'));
                $whereClause[] = "TRIM(LOWER(u.school)) IN ($placeholders)";
                foreach ($schoolNames as $schoolName) {
                    $params[] = $schoolName;
                    $types .= 's';
                }
            }
        }

        if (!empty($get['grade'])) {
            $whereClause[] = 'u.grade = ?';
            $params[] = $get['grade'];
            $types .= 's';
        }

        return [
            'where' => $whereClause,
            'params' => $params,
            'types' => $types,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
