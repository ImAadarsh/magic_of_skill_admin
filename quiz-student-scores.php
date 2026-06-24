<?php
include 'include/session.php';
include 'include/connect.php';
include 'include/quiz-helpers.php';

if (!$connect) {
    die('Connection failed: ' . mysqli_connect_error());
}

$fuzzyGroups = buildFuzzySchoolGroups($connect);
$filters = buildQuizStudentScoreFilters($_GET, $fuzzyGroups);

$selectedSchools = parseSelectedSchoolKeys($_GET);
$startDate = $filters['start_date'];
$endDate = $filters['end_date'];
$selectedGrade = !empty($_GET['grade']) ? $_GET['grade'] : '';
$recordsPerPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $recordsPerPage;

$gradesResult = $connect->query("SELECT DISTINCT grade FROM users WHERE grade IS NOT NULL AND grade != '' ORDER BY grade");
$grades = $gradesResult ? $gradesResult->fetch_all(MYSQLI_ASSOC) : [];

$students = [];
$totalRecords = 0;
$totalPages = 0;
$summary = [
    'students' => 0,
    'attempts' => 0,
    'quizzes' => 0,
    'cumulative_score' => 0,
];

$canQuery = !empty($selectedSchools) && $startDate && $endDate;

if ($canQuery) {
    $baseSql = "FROM user_quiz_attempts uqa
                JOIN users u ON uqa.user_id = u.id
                WHERE " . implode(' AND ', $filters['where']);

    $countSql = "SELECT COUNT(*) FROM (
                    SELECT u.id
                    $baseSql
                    GROUP BY u.id
                 ) AS student_scores";

    $countStmt = $connect->prepare($countSql);
    if ($countStmt) {
        if (!empty($filters['types']) && !empty($filters['params'])) {
            $countStmt->bind_param($filters['types'], ...$filters['params']);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRecords = (int) ($countResult->fetch_row()[0] ?? 0);
        $countStmt->close();
    }

    $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $recordsPerPage) : 0;
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $recordsPerPage;
    }

    $summarySql = "SELECT
                        COUNT(DISTINCT u.id) AS students,
                        COUNT(uqa.attempt_id) AS attempts,
                        COUNT(DISTINCT uqa.quiz_id) AS quizzes,
                        COALESCE(SUM(uqa.score), 0) AS cumulative_score
                   $baseSql";

    $summaryStmt = $connect->prepare($summarySql);
    if ($summaryStmt) {
        if (!empty($filters['types']) && !empty($filters['params'])) {
            $summaryStmt->bind_param($filters['types'], ...$filters['params']);
        }
        $summaryStmt->execute();
        $summaryRow = $summaryStmt->get_result()->fetch_assoc();
        if ($summaryRow) {
            $summary = [
                'students' => (int) $summaryRow['students'],
                'attempts' => (int) $summaryRow['attempts'],
                'quizzes' => (int) $summaryRow['quizzes'],
                'cumulative_score' => round((float) $summaryRow['cumulative_score'], 2),
            ];
        }
        $summaryStmt->close();
    }

    $dataSql = "SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.school,
                    u.grade,
                    u.city,
                    u.email,
                    COUNT(uqa.attempt_id) AS total_attempts,
                    COUNT(DISTINCT uqa.quiz_id) AS quizzes_played,
                    COALESCE(SUM(uqa.score), 0) AS cumulative_score,
                    COALESCE(AVG(uqa.score), 0) AS average_score,
                    COALESCE(MAX(uqa.score), 0) AS best_score
                $baseSql
                GROUP BY u.id, u.first_name, u.last_name, u.school, u.grade, u.city, u.email
                ORDER BY cumulative_score DESC, u.first_name ASC, u.last_name ASC
                LIMIT ? OFFSET ?";

    $dataParams = array_merge($filters['params'], [$recordsPerPage, $offset]);
    $dataTypes = $filters['types'] . 'ii';

    $dataStmt = $connect->prepare($dataSql);
    if ($dataStmt) {
        if (!empty($dataTypes) && !empty($dataParams)) {
            $dataStmt->bind_param($dataTypes, ...$dataParams);
        }
        $dataStmt->execute();
        $students = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $dataStmt->close();
    }
}

$schoolDisplayNames = [];
foreach ($selectedSchools as $schoolKey) {
    if (isset($fuzzyGroups[$schoolKey])) {
        $schoolDisplayNames[] = $fuzzyGroups[$schoolKey]['display'];
    }
}
$schoolSummaryLabel = count($schoolDisplayNames) === 1
    ? $schoolDisplayNames[0]
    : count($schoolDisplayNames) . ' schools selected';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Cumulative Scores - Magic Of Skills Dashboard</title>
    <?php include 'include/meta.php' ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .table-responsive { overflow-x: auto; }
        .table td iconify-icon { pointer-events: none; }
        .mos-school-select-wrap {
            min-width: 240px;
            max-width: 320px;
        }
        .select2-container { width: 100% !important; }
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            min-height: 38px;
            padding: 2px 6px;
            font-size: 13px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #0d6efd;
            border: none;
            color: #fff;
            border-radius: 4px;
            padding: 1px 6px;
            margin-top: 2px;
            font-size: 12px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff;
            margin-right: 4px;
        }
        .select2-multiple-checkboxes .select2-results__option {
            position: relative;
            padding-left: 30px !important;
            font-size: 13px;
        }
        .select2-multiple-checkboxes .select2-results__option::before {
            content: "";
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            background-color: #fff;
        }
        .select2-multiple-checkboxes .select2-results__option--selected::before {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .select2-multiple-checkboxes .select2-results__option--selected::after {
            content: "✓";
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
            font-size: 10px;
            font-weight: bold;
        }
        .summary-card {
            border: 1px solid var(--neutral-300, #e5e7eb);
            border-radius: 12px;
            padding: 16px 20px;
            background: var(--white, #fff);
            height: 100%;
        }
        .summary-card .label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .summary-card .value {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
        }
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <?php include 'include/aside.php' ?>

    <main class="dashboard-main">
        <?php include 'include/header.php' ?>

        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Student Cumulative Scores</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Quiz Scores</li>
                </ul>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div class="mos-card-header">
                    <div class="mos-card-header-left">
                        <button class="mos-filter-toggle d-lg-none" data-target="scoresFilterBody" aria-expanded="false">
                            <iconify-icon icon="heroicons:funnel" style="font-size:15px"></iconify-icon>
                            <span class="toggle-label">Filters</span>
                            <span class="filter-count-badge">0</span>
                        </button>
                        <div class="mos-filter-body d-lg-block" id="scoresFilterBody">
                            <form method="GET" class="mos-filter-row">
                                <div class="mos-school-select-wrap">
                                    <select name="school[]" id="school" class="form-select form-select-sm" multiple="multiple">
                                        <?php foreach ($fuzzyGroups as $key => $group): ?>
                                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, $selectedSchools, true) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($group['display']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>" required>
                                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>" required>
                                <select name="grade" class="form-select form-select-sm">
                                    <option value="">All Grades</option>
                                    <?php foreach ($grades as $gradeRow): ?>
                                        <option value="<?php echo htmlspecialchars($gradeRow['grade']); ?>" <?php echo $selectedGrade === $gradeRow['grade'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($gradeRow['grade']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mos-per-page-wrap">
                                    <span>Show</span>
                                    <select name="per_page">
                                        <option value="10" <?php echo $recordsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $recordsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $recordsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $recordsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                </div>
                                <button type="submit" class="mos-btn-apply">
                                    <iconify-icon icon="heroicons:magnifying-glass" style="font-size:13px"></iconify-icon> Apply
                                </button>
                                <a href="quiz-student-scores.php" class="mos-btn-reset">
                                    <iconify-icon icon="heroicons:x-mark" style="font-size:13px"></iconify-icon> Reset
                                </a>
                            </form>
                        </div>
                    </div>
                    <div class="mos-card-header-right">
                        <button id="downloadExcel" class="mos-btn-export" <?php echo $canQuery ? '' : 'disabled'; ?>>
                            <iconify-icon icon="vscode-icons:file-type-excel" style="font-size:16px"></iconify-icon> Export
                        </button>
                    </div>
                </div>

                <?php if ($canQuery): ?>
                    <div class="card-body border-bottom px-24 py-16">
                        <div class="mb-12">
                            <h6 class="fw-semibold mb-4"><?php echo htmlspecialchars($schoolSummaryLabel); ?></h6>
                            <?php if (count($schoolDisplayNames) > 1): ?>
                                <p class="mb-8 text-secondary-light text-sm">
                                    <?php echo htmlspecialchars(implode(', ', $schoolDisplayNames)); ?>
                                </p>
                            <?php endif; ?>
                            <p class="mb-0 text-secondary-light text-sm">
                                Consolidated quiz results from
                                <strong><?php echo date('d M Y', strtotime($startDate)); ?></strong>
                                to
                                <strong><?php echo date('d M Y', strtotime($endDate)); ?></strong>
                                <?php if ($selectedGrade): ?>
                                    &middot; Grade <strong><?php echo htmlspecialchars($selectedGrade); ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6 col-xl-3">
                                <div class="summary-card">
                                    <div class="label">Students</div>
                                    <div class="value"><?php echo number_format($summary['students']); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="summary-card">
                                    <div class="label">Total Attempts</div>
                                    <div class="value"><?php echo number_format($summary['attempts']); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="summary-card">
                                    <div class="label">Quizzes Played</div>
                                    <div class="value"><?php echo number_format($summary['quizzes']); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="summary-card">
                                    <div class="label">Combined Cumulative Score</div>
                                    <div class="value"><?php echo number_format($summary['cumulative_score'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mos-table-info-bar">
                        <span>Showing <strong><?php echo $totalRecords > 0 ? $offset + 1 : 0; ?></strong> – <strong><?php echo min($offset + $recordsPerPage, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> students</span>
                        <span><?php echo $totalPages; ?> page(s)</span>
                    </div>
                <?php endif; ?>

                <div class="card-body p-24">
                    <?php if (!$canQuery): ?>
                        <div class="empty-state">
                            <iconify-icon icon="heroicons:chart-bar-square" style="font-size:48px;color:#9ca3af"></iconify-icon>
                            <h6 class="mt-16 mb-8 fw-semibold">Select one or more schools and a date range</h6>
                            <p class="mb-0">Choose schools from the dropdown and pick a date range to view each student's total cumulative quiz score for that period.</p>
                        </div>
                    <?php elseif (empty($students)): ?>
                        <div class="empty-state">
                            <h6 class="mb-8 fw-semibold">No quiz results found</h6>
                            <p class="mb-0">No completed quiz attempts were found for the selected school and date range.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive mos-table-wrap">
                            <table class="table bordered-table sm-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Rank</th>
                                        <th scope="col">Student</th>
                                        <th scope="col">School</th>
                                        <th scope="col">Grade</th>
                                        <th scope="col">City</th>
                                        <th scope="col">Quizzes Played</th>
                                        <th scope="col">Attempts</th>
                                        <th scope="col">Cumulative Score</th>
                                        <th scope="col">Average Score</th>
                                        <th scope="col">Best Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium text-secondary-light">
                                                        <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])); ?>
                                                    </span>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['school']); ?></td>
                                            <td><?php echo htmlspecialchars($student['grade'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($student['city'] ?? ''); ?></td>
                                            <td><?php echo (int) $student['quizzes_played']; ?></td>
                                            <td><?php echo (int) $student['total_attempts']; ?></td>
                                            <td><strong><?php echo number_format((float) $student['cumulative_score'], 2); ?></strong></td>
                                            <td><?php echo number_format((float) $student['average_score'], 2); ?></td>
                                            <td><?php echo number_format((float) $student['best_score'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div class="mos-pagination-wrap">
                                <span class="mos-pagination-info">
                                    Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo number_format($totalRecords); ?> entries
                                </span>
                                <ul class="mos-pagination">
                                    <?php if ($page > 1): ?>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" title="First"><iconify-icon icon="ep:d-arrow-left"></iconify-icon></a></li>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">‹</a></li>
                                    <?php else: ?>
                                        <li class="disabled"><a>‹</a></li>
                                    <?php endif; ?>
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($page < $totalPages): ?>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">›</a></li>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" title="Last"><iconify-icon icon="ep:d-arrow-right"></iconify-icon></a></li>
                                    <?php else: ?>
                                        <li class="disabled"><a>›</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include 'include/footer.php' ?>
    </main>

    <?php include 'include/script.php' ?>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#school').select2({
                placeholder: 'Select schools',
                allowClear: true,
                closeOnSelect: false,
                dropdownCssClass: 'select2-multiple-checkboxes'
            });

            $('form.mos-filter-row').on('submit', function (e) {
                const selectedSchools = $('#school').val();
                const startDate = $('input[name="start_date"]').val();
                const endDate = $('input[name="end_date"]').val();

                if (!selectedSchools || selectedSchools.length === 0) {
                    e.preventDefault();
                    Swal.fire('School required', 'Please select at least one school.', 'warning');
                    return;
                }

                if (!startDate || !endDate) {
                    e.preventDefault();
                    Swal.fire('Date range required', 'Please select both start and end dates.', 'warning');
                }
            });
        });

        document.getElementById('downloadExcel').addEventListener('click', function () {
            const btn = this;
            if (btn.disabled) {
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>Exporting...</span>';

            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            params.delete('per_page');

            fetch('export/quiz-student-scores.php?' + params.toString())
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.status !== 'success') {
                        throw new Error(data.message || 'Export failed.');
                    }

                    if (!data.students || data.students.length === 0) {
                        Swal.fire('No data', 'No student scores found for the current filters.', 'info');
                        return;
                    }

                    const ws = XLSX.utils.json_to_sheet(data.students);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Student Scores');
                    const filename = 'student_cumulative_scores_' + new Date().toISOString().slice(0, 10) + '.xlsx';
                    XLSX.writeFile(wb, filename);

                    Swal.fire('Exported!', data.total + ' student score(s) exported successfully.', 'success');
                })
                .catch(function (error) {
                    console.error('Export error:', error);
                    Swal.fire('Error', error.message || 'An error occurred while exporting scores.', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
        });
    </script>
</body>
</html>
