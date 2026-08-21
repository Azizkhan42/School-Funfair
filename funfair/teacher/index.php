<?php

require_once __DIR__ . '/../../includes/teacher-session.php';

$pageTitle = 'Teacher - Lucky Draw';

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/navbar.php';

require_once __DIR__ . '/../../includes/teacher-nav.php';


/*
|--------------------------------------------------------------------------
| Get Events Currently Selling
|--------------------------------------------------------------------------
|
| Only show events that are OPEN *and* whose sales window
| includes the current date/time.
|
*/

$stmt = $pdo->query("

    SELECT
        e.event_id,
        e.event_name,
        e.ticket_price,
        e.max_tickets_per_student,
        e.sales_start,
        e.sales_end,
        e.status,
        (
            SELECT COUNT(*)
            FROM lucky_draw_registrations r
            INNER JOIN test_students s
                ON s.student_id = r.student_id
            WHERE r.event_id = e.event_id
              AND s.teacher_id = " . (int)$teacherId . "
        ) AS my_registered,
        (
            SELECT COUNT(*)
            FROM lucky_draw_tickets t
            WHERE t.event_id = e.event_id
              AND t.teacher_id = " . (int)$teacherId . "
        ) AS my_issued

    FROM lucky_draw_events e

    WHERE e.status = 'OPEN'
      AND e.sales_start <= GETDATE()
      AND e.sales_end > GETDATE()

    ORDER BY e.event_id DESC

");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-0">
                Teacher Lucky Draw
            </h2>

            <p class="text-muted mb-0">
                Issue FunFair tickets to registered students.
            </p>
        </div>

        <a
            href="registrations.php"
            class="btn btn-outline-primary"
        >
            📋 FunFair Registrations
        </a>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form
                method="GET"
                class="row g-3 align-items-center"
            >

                <div class="col-md-6">

                    <label class="form-label fw-semibold mb-1">
                        Current Teacher (concern teacher)
                    </label>

                    <select
                        name="teacher_id"
                        class="form-select"
                        onchange="this.form.submit()"
                    >

                        <?php foreach ($allTeachers as $teacher): ?>

                            <option
                                value="<?= $teacher['teacher_id'] ?>"
                                <?= $teacher['teacher_id'] == $teacherId ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($teacher['teacher_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-6">

                    <div class="border-start ps-3 pt-2 pt-md-0">

                        <div class="fw-bold">
                            👩‍🏫 <?= htmlspecialchars($currentTeacher['teacher_name']) ?>
                        </div>

                        <div class="text-muted small">
                            You can only issue tickets to your own students who have registered for the event.
                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <?php if (empty($events)): ?>

        <div class="alert alert-warning">

            <strong>
                No event is currently selling tickets.
            </strong>

            <div class="mt-1">
                An event only becomes available here when it is
                <strong>OPEN</strong>
                and the current date/time falls inside its
                sales period (sales start → sales end).
            </div>

            <div class="mt-1">
                Admin can open sales from the
                <a href="../events.php">Events</a>
                page.
            </div>

        </div>

    <?php else: ?>


        <?php foreach ($events as $event): ?>

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <div class="row align-items-center">

                        <div class="col-md-8">

                            <h4 class="fw-bold">

                                <?= htmlspecialchars(
                                    $event['event_name']
                                ) ?>

                            </h4>

                            <div class="text-muted">

                                Ticket Price:
                                <strong>
                                    Rs.
                                    <?= number_format(
                                        $event['ticket_price'],
                                        2
                                    ) ?>
                                </strong>

                                <br>

                                Maximum Tickets:
                                <strong>
                                    <?= $event[
                                        'max_tickets_per_student'
                                    ] ?>
                                </strong>

                            </div>

                            <div class="mt-2">

                                <span class="badge bg-info text-dark">
                                    🎓 My students registered:
                                    <?= (int)$event['my_registered'] ?>
                                </span>

                                <span class="badge bg-secondary">
                                    🎟️ Tickets I issued:
                                    <?= (int)$event['my_issued'] ?>
                                </span>

                            </div>

                        </div>


                        <div class="col-md-4 text-md-end mt-3 mt-md-0">

                            <a
                                href="issue-ticket.php?event_id=<?= $event['event_id'] ?>"
                                class="btn btn-primary"
                            >
                                + Issue Ticket
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php

require_once __DIR__ . '/../../includes/footer.php';

?>