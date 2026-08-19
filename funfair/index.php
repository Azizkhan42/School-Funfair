<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'FunFair Dashboard';

$statsStmt = $pdo->query("

    SELECT
        (SELECT COUNT(*) FROM lucky_draw_events) AS total_events,

        (SELECT COUNT(*) FROM lucky_draw_tickets) AS total_tickets,

        (SELECT COUNT(*) FROM lucky_draw_prizes) AS total_prizes,

        (SELECT COUNT(*) FROM lucky_draw_winners) AS total_winners

");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                FunFair Dashboard
            </h2>

            <p class="text-muted mb-0">
                Manage the Lucky Draw event
            </p>
        </div>

        <a
            href="event-create.php"
            class="btn btn-primary"
        >
            + Create Event
        </a>

    </div>


    <div class="row g-4">

        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Events
                    </p>

                    <h3 class="fw-bold">
                        <?= number_format($stats['total_events']) ?>
                    </h3>

                    <a
                        href="events.php"
                        class="btn btn-sm btn-outline-primary mt-2"
                    >
                        View Events
                    </a>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Tickets
                    </p>

                    <h3 class="fw-bold">
                        <?= number_format($stats['total_tickets']) ?>
                    </h3>

                    <a
                        href="tickets.php"
                        class="btn btn-sm btn-outline-primary mt-2"
                    >
                        Ticket History
                    </a>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Prizes
                    </p>

                    <h3 class="fw-bold">
                        <?= number_format($stats['total_prizes']) ?>
                    </h3>

                    <a
                        href="prizes.php"
                        class="btn btn-sm btn-outline-primary mt-2"
                    >
                        Manage Prizes
                    </a>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-lg-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Winners
                    </p>

                    <h3 class="fw-bold">
                        <?= number_format($stats['total_winners']) ?>
                    </h3>

                    <a
                        href="winners.php"
                        class="btn btn-sm btn-outline-primary mt-2"
                    >
                        View Winners
                    </a>

                </div>

            </div>

        </div>

    </div>


    <div class="d-flex flex-wrap gap-2 mt-4">

        <a
            href="draw.php"
            class="btn btn-primary"
        >
            🎲 Draw Engine
        </a>

        <a
            href="live.php"
            class="btn btn-outline-danger"
        >
            📺 Live TV / Projector
        </a>

        <a
            href="reports.php"
            class="btn btn-outline-success"
        >
            💰 Cash Collection & Reports
        </a>

        <a
            href="teacher/index.php"
            class="btn btn-outline-secondary"
        >
            👩‍🏫 Teacher Portal
        </a>

    </div>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>