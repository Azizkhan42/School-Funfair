<?php

/*
|--------------------------------------------------------------------------
| Student Session Helper
|--------------------------------------------------------------------------
|
| Resolves the "current student" from the session, with a simple
| in-page switcher (GET/POST ?student_id=). Later this should come
| from the school's real login system.
|
| Provides: $studentId (int), $currentStudent (array), $allStudents (array)
|
*/

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentId = (int) ($_SESSION['funfair_student_id'] ?? 101);

$switchStudent = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

if (!$switchStudent) {
    $switchStudent = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
}

if ($switchStudent && $switchStudent > 0) {
    $studentId = $switchStudent;
    $_SESSION['funfair_student_id'] = $switchStudent;
}

$studentStmt = $pdo->prepare("

    SELECT
        s.student_id,
        s.student_name,
        s.class_name,
        s.teacher_id,
        t.teacher_name

    FROM test_students s

    LEFT JOIN test_teachers t
        ON t.teacher_id = s.teacher_id

    WHERE s.student_id = :student_id

");

$studentStmt->execute([
    ':student_id' => $studentId
]);

$currentStudent = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentStudent) {

    $studentId = 101;

    $_SESSION['funfair_student_id'] = 101;

    $currentStudent = [
        'student_id' => 101,
        'student_name' => 'Ali Khan',
        'class_name' => 'Grade 7-A',
        'teacher_id' => 1,
        'teacher_name' => 'Mrs. Sara Ahmed'
    ];

}

$allStudents = $pdo->query("

    SELECT
        student_id,
        student_name,
        class_name

    FROM test_students

    ORDER BY student_name

")->fetchAll(PDO::FETCH_ASSOC);