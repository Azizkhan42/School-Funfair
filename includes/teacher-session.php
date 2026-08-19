<?php

/*
|--------------------------------------------------------------------------
| Teacher Session Helper
|--------------------------------------------------------------------------
|
| Resolves the "current teacher" from the session, with a simple
| in-page switcher (GET/POST ?teacher_id=). Later this should come
| from the school's real login system.
|
| Provides: $teacherId (int), $currentTeacher (array), $allTeachers (array)
|
*/

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$teacherId = (int) ($_SESSION['funfair_teacher_id'] ?? 1);

$switchTeacher = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);

if (!$switchTeacher) {
    $switchTeacher = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);
}

if ($switchTeacher && $switchTeacher > 0) {
    $teacherId = $switchTeacher;
    $_SESSION['funfair_teacher_id'] = $switchTeacher;
}

$teacherStmt = $pdo->prepare("

    SELECT teacher_id, teacher_name

    FROM test_teachers

    WHERE teacher_id = :teacher_id

");

$teacherStmt->execute([
    ':teacher_id' => $teacherId
]);

$currentTeacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentTeacher) {

    $teacherId = 1;

    $_SESSION['funfair_teacher_id'] = 1;

    $currentTeacher = [
        'teacher_id' => 1,
        'teacher_name' => 'Mrs. Sara Ahmed'
    ];

}

$allTeachers = $pdo->query("

    SELECT teacher_id, teacher_name

    FROM test_teachers

    ORDER BY teacher_id

")->fetchAll(PDO::FETCH_ASSOC);