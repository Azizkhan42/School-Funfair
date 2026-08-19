<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/audit.php';

/*
|--------------------------------------------------------------------------
| Events students can register for (DRAFT or OPEN)
|--------------------------------------------------------------------------
*/

$eventsStmt = $pdo->query("

    SELECT
        e.event_id,
        e.event_name,
        e.status,
        e.ticket_price,
        (
            SELECT COUNT(*)
            FROM lucky_draw_registrations r
            WHERE r.event_id = e.event_id
        ) AS registered_count

    FROM lucky_draw_events e

    WHERE e.status IN ('DRAFT', 'OPEN')

    ORDER BY e.event_id DESC

");

$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| All students
|--------------------------------------------------------------------------
*/

$studentsStmt = $pdo->query("

    SELECT
        student_id,
        student_name,
        class_name

    FROM test_students

    ORDER BY student_name

");

$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

$success = null;

$registeredList = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    if (!$eventId) {
        $errors[] = 'Please select an event.';
    }

    if (!$studentId) {
        $errors[] = 'Please select your name.';
    }

    if (empty($errors)) {

        $checkStmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM lucky_draw_registrations

            WHERE event_id = :event_id
              AND student_id = :student_id

        ");

        $checkStmt->execute([
            ':event_id' => $eventId,
            ':student_id' => $studentId
        ]);

        if ((int)$checkStmt->fetchColumn() > 0) {

            $errors[] = 'You have already registered for this event.';

        } else {

            try {

                $pdo->beginTransaction();

                $insertStmt = $pdo->prepare("

                    INSERT INTO lucky_draw_registrations
                    (
                        event_id,
                        student_id,
                        registered_at,
                        status
                    )
                    VALUES
                    (
                        :event_id,
                        :student_id,
                        GETDATE(),
                        'REGISTERED'
                    )

                ");

                $insertStmt->execute([
                    ':event_id' => $eventId,
                    ':student_id' => $studentId
                ]);

                $pdo->commit();

                $studentStmt = $pdo->prepare("

                    SELECT student_name, class_name

                    FROM test_students

                    WHERE student_id = :student_id

                ");

                $studentStmt->execute([
                    ':student_id' => $studentId
                ]);

                $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

                $eventStmt = $pdo->prepare("

                    SELECT event_name

                    FROM lucky_draw_events

                    WHERE event_id = :event_id

                ");

                $eventStmt->execute([
                    ':event_id' => $eventId
                ]);

                $eventName = $eventStmt->fetchColumn();

                $success = [
                    'event_id' => $eventId,
                    'event_name' => $eventName,
                    'student_name' => $student['student_name'],
                    'class_name' => $student['class_name']
                ];

                logAudit(
                    $pdo,
                    1,
                    'STUDENT_REGISTERED',
                    'EVENT',
                    $eventId,
                    $eventId,
                    'Student ' .
                        $student['student_name'] .
                        ' (ID ' .
                        $studentId .
                        ') registered for event "' .
                        $eventName .
                        '".'
                );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = 'Unable to register: ' . $e->getMessage();

            }

        }

    }

    if ($eventId) {

        $listStmt = $pdo->prepare("

            SELECT
                s.student_name,
                s.class_name,
                r.registered_at

            FROM lucky_draw_registrations r

            INNER JOIN test_students s
                ON s.student_id = r.student_id

            WHERE r.event_id = :event_id

            ORDER BY s.student_name

        ");

        $listStmt->execute([
            ':event_id' => $eventId
        ]);

        $registeredList = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    }

}

$pageTitle = 'Student Registration';

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="mb-4 text-center">

        <h2 class="fw-bold mb-1">
            🎟️ Student Registration
        </h2>

        <p class="text-muted mb-0">
            Apply for the FunFair Lucky Draw events. Only registered students can be issued tickets.
        </p>

    </div>

    <div class="row justify-content-center">

        <div class="col-lg-6">

            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= htmlspecialchars($error) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <?php if ($success): ?>

                <div class="alert alert-success">

                    <h5 class="alert-heading">
                        ✅ You are registered!
                    </h5>

                    <p class="mb-0">
                        <strong>
                            <?= htmlspecialchars($success['student_name']) ?>
                        </strong>
                        (
                        <?= htmlspecialchars($success['class_name']) ?>
                        ) is registered for
                        <strong>
                            <?= htmlspecialchars($success['event_name']) ?>
                        </strong>.
                    </p>

                    <p class="mb-0 mt-2 text-muted">
                        Your concern teacher will now be able to issue your ticket.
                    </p>

                </div>

            <?php endif; ?>

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Event
                            </label>

                            <select
                                name="event_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Event
                                </option>

                                <?php foreach ($events as $ev): ?>

                                    <option
                                        value="<?= $ev['event_id'] ?>"
                                        <?= isset($eventId) && $eventId == $ev['event_id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($ev['event_name']) ?>
                                        —
                                        <?= (int)$ev['registered_count'] ?>
                                        registered
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Your Name
                            </label>

                            <select
                                name="student_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Your Name
                                </option>

                                <?php foreach ($students as $student): ?>

                                    <option
                                        value="<?= $student['student_id'] ?>"
                                    >
                                        <?= htmlspecialchars($student['student_name']) ?>
                                        —
                                        <?= htmlspecialchars($student['class_name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <?php if (count($events) === 0): ?>

                            <div class="alert alert-warning">
                                No events are currently accepting registrations.
                            </div>

                        <?php else: ?>

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                Register for Event
                            </button>

                        <?php endif; ?>

                    </form>

                </div>

            </div>

        </div>

        <div class="col-lg-6">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h5 class="fw-bold">
                        Registered Students
                    </h5>

                    <hr>

                    <?php if (count($registeredList) === 0): ?>

                        <p class="text-muted mb-0">
                            Select an event and register to see the list here.
                        </p>

                    <?php else: ?>

                        <ul class="list-group list-group-flush">

                            <?php foreach ($registeredList as $r): ?>

                                <li class="list-group-item d-flex justify-content-between">

                                    <div>
                                        <strong>
                                            <?= htmlspecialchars($r['student_name']) ?>
                                        </strong>

                                        <div class="text-muted small">
                                            <?= htmlspecialchars($r['class_name']) ?>
                                        </div>
                                    </div>

                                    <div class="text-muted small align-self-center">
                                        <?= date(
                                            'd M Y h:i A',
                                            strtotime($r['registered_at'])
                                        ) ?>
                                    </div>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>