<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'FunFair Events';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$stmt = $pdo->query("
    SELECT
        event_id,
        event_name,
        ticket_price,
        max_tickets_per_student,
        sales_start,
        sales_end,
        draw_date,
        status
    FROM lucky_draw_events
    ORDER BY event_id DESC
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                FunFair Events
            </h2>

            <p class="text-muted mb-0">
                Manage Lucky Draw events
            </p>
        </div>

        <a
            href="event-create.php"
            class="btn btn-primary"
        >
            + Create Event
        </a>

    </div>

    <?php if (isset($_GET['deleted'])): ?>

        <div class="alert alert-success alert-dismissible fade show">
            Event deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

    <?php elseif (isset($_GET['error'])): ?>

        <div class="alert alert-danger alert-dismissible fade show">
            Could not delete the event.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <?php if (count($events) === 0): ?>

                <div class="text-center py-5">

                    <h5>
                        No events found
                    </h5>

                    <p class="text-muted">
                        Create your first FunFair event.
                    </p>

                    <a
                        href="event-create.php"
                        class="btn btn-primary"
                    >
                        Create Event
                    </a>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Event</th>

                                <th>Ticket Price</th>

                                <th>Max Tickets</th>

                                <th>Sales End</th>

                                <th>Draw Date</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($events as $event): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($event['event_id']) ?>
                                </td>

                                <td class="fw-semibold">
                                    <?= htmlspecialchars($event['event_name']) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format($event['ticket_price'], 2) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($event['max_tickets_per_student']) ?>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y h:i A',
                                        strtotime($event['sales_end'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y h:i A',
                                        strtotime($event['draw_date'])
                                    ) ?>
                                </td>

                                <td>

                                    <?php

                                    $badgeClass = match ($event['status']) {

                                        'DRAFT' => 'bg-secondary',

                                        'OPEN' => 'bg-success',

                                        'CLOSED' => 'bg-warning text-dark',

                                        'DRAWING' => 'bg-primary',

                                        'COMPLETED' => 'bg-dark',

                                        'CANCELLED' => 'bg-danger',

                                        default => 'bg-secondary'

                                    };

                                    ?>

                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($event['status']) ?>
                                    </span>

                                </td>

                                <td>

                                    <div class="d-flex gap-1">

    <a
        href="event-edit.php?id=<?= $event['event_id'] ?>"
        class="btn btn-sm btn-outline-primary"
    >
        Edit
    </a>

    <a
        href="prizes.php?event_id=<?= $event['event_id'] ?>"
        class="btn btn-sm btn-outline-success"
    >
        Prizes
    </a>

    <a
        href="draw.php?event_id=<?= $event['event_id'] ?>"
        class="btn btn-sm btn-outline-dark"
    >
        Draw
    </a>


    <?php if ($event['status'] === 'DRAFT'): ?>

        <a
            href="event-status.php?id=<?= $event['event_id'] ?>&action=open"
            class="btn btn-sm btn-outline-success"
        >
            Open Sales
        </a>

    <?php elseif ($event['status'] === 'OPEN'): ?>

        <a
            href="event-status.php?id=<?= $event['event_id'] ?>&action=close"
            class="btn btn-sm btn-outline-warning"
        >
            Close Sales
        </a>

    <?php endif; ?>

    <form
        method="POST"
        action="event-delete.php"
        class="d-inline"
        onsubmit="return confirm('Delete this event? This will permanently remove its prizes, tickets and winners.');"
    >
        <input
            type="hidden"
            name="event_id"
            value="<?= $event['event_id'] ?>"
        >

        <button
            type="submit"
            class="btn btn-sm btn-outline-danger"
        >
            Delete
        </button>
    </form>

</div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>