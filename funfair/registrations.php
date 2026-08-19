<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $registrationId = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT);

    if ($registrationId) {

        $fetchStmt = $pdo->prepare("

            SELECT
                r.event_id,
                r.student_id,
                e.event_name,
                s.student_name

            FROM lucky_draw_registrations r

            INNER JOIN lucky_draw_events e
                ON e.event_id = r.event_id

            INNER JOIN test_students s
                ON s.student_id = r.student_id

            WHERE r.registration_id = :registration_id

        ");

        $fetchStmt->execute([
            ':registration_id' => $registrationId
        ]);

        $reg = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if ($reg) {

            $deleteStmt = $pdo->prepare("

                DELETE FROM lucky_draw_registrations

                WHERE registration_id = :registration_id

            ");

            $deleteStmt->execute([
                ':registration_id' => $registrationId
            ]);

            logAudit(
                $pdo,
                1,
                'REGISTRATION_REMOVED',
                'EVENT',
                (int)$reg['event_id'],
                (int)$reg['event_id'],
                'Removed registration of student ' .
                    $reg['student_name'] .
                    ' (ID ' .
                    $reg['student_id'] .
                    ') from event "' .
                    $reg['event_name'] .
                    '".'
            );

            header('Location: registrations.php?removed=1');
            exit;

        }

    }

    header('Location: registrations.php');
    exit;

}

$pageTitle = 'Student Registrations';

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

$eventsStmt = $pdo->query("

    SELECT
        e.event_id,
        e.event_name,
        e.status,
        (
            SELECT COUNT(*)
            FROM lucky_draw_registrations r
            WHERE r.event_id = e.event_id
        ) AS registered_count

    FROM lucky_draw_events e

    ORDER BY e.event_id DESC

");

$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                📋 Student Registrations
            </h2>

            <p class="text-muted mb-0">
                Students who applied for each event — only these students can be issued tickets.
            </p>
        </div>

        <a
            href="register.php"
            class="btn btn-primary"
        >
            + Student Registration
        </a>

    </div>

    <?php if (isset($_GET['removed'])): ?>

        <div class="alert alert-success alert-dismissible fade show">
            Registration removed.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>

    <?php if (count($events) === 0): ?>

        <div class="alert alert-warning">
            No events found.
        </div>

    <?php else: ?>

        <?php foreach ($events as $event): ?>

            <?php

            $listStmt = $pdo->prepare("

                SELECT
                    r.registration_id,
                    r.registered_at,
                    s.student_name,
                    s.class_name,
                    t.teacher_name

                FROM lucky_draw_registrations r

                INNER JOIN test_students s
                    ON s.student_id = r.student_id

                LEFT JOIN test_teachers t
                    ON t.teacher_id = s.teacher_id

                WHERE r.event_id = :event_id

                ORDER BY s.student_name

            ");

            $listStmt->execute([
                ':event_id' => $event['event_id']
            ]);

            $list = $listStmt->fetchAll(PDO::FETCH_ASSOC);

            ?>

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-2">

                        <h5 class="fw-bold mb-0">
                            <?= htmlspecialchars($event['event_name']) ?>
                            <span class="badge bg-<?= $event['status'] === 'OPEN' ? 'success' : 'secondary' ?> ms-1">
                                <?= htmlspecialchars($event['status']) ?>
                            </span>
                        </h5>

                        <span class="badge bg-primary">
                            <?= (int)$event['registered_count'] ?>
                            registered
                        </span>

                    </div>

                    <?php if (count($list) === 0): ?>

                        <p class="text-muted mb-0">
                            No registrations yet.
                        </p>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-sm table-hover align-middle mb-0">

                                <thead class="table-light">

                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Concern Teacher</th>
                                        <th>Registered At</th>
                                        <th></th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php $i = 1; ?>

                                    <?php foreach ($list as $row): ?>

                                        <tr>

                                            <td>
                                                <?= $i++ ?>
                                            </td>

                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($row['student_name']) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['class_name']) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['teacher_name'] ?? '—') ?>
                                            </td>

                                            <td>
                                                <?= date(
                                                    'd M Y h:i A',
                                                    strtotime($row['registered_at'])
                                                ) ?>
                                            </td>

                                            <td class="text-end">

                                                <form
                                                    method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Remove this registration?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="registration_id"
                                                        value="<?= $row['registration_id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                    >
                                                        Remove
                                                    </button>

                                                </form>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>