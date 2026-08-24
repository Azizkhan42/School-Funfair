<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/draw-helper.php';

$selectedEvent = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Events for selector
|--------------------------------------------------------------------------
*/

$eventsStmt = $pdo->query("

    SELECT
        e.event_id,
        e.event_name,
        e.status,
        (
            SELECT COUNT(*)
            FROM lucky_draw_prizes p
            WHERE p.event_id = e.event_id
            AND p.status = 'AVAILABLE'
        ) AS available_prizes

    FROM lucky_draw_events e

    ORDER BY e.event_id DESC

");

$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Auto-select
|--------------------------------------------------------------------------
|
| 1) An event that is CLOSED/DRAWING and still has prizes (ready to draw)
| 2) Otherwise the most recent OPEN event
| 3) Otherwise the most recent event
|
*/

if (!$selectedEvent && count($events) > 0) {

    foreach ($events as $ev) {

        if (
            in_array($ev['status'], ['CLOSED', 'DRAWING'], true) &&
            (int)$ev['available_prizes'] > 0
        ) {
            $selectedEvent = (int)$ev['event_id'];
            break;
        }

    }

    if (!$selectedEvent) {

        foreach ($events as $ev) {

            if ($ev['status'] === 'OPEN') {
                $selectedEvent = (int)$ev['event_id'];
                break;
            }

        }

    }

    if (!$selectedEvent) {
        $selectedEvent = (int)$events[0]['event_id'];
    }

}


/*
|--------------------------------------------------------------------------
| Load event data for server-side stage
|--------------------------------------------------------------------------
*/

$event = null;

$stageState = 'none';

$nextPrize = null;

$eligibleTickets = [];

$winners = [];

/*
|--------------------------------------------------------------------------
| Demo mode (no database writes) - lets you watch the wheel animation
|--------------------------------------------------------------------------
*/

$demoMode = (bool) filter_input(INPUT_GET, 'demo', FILTER_VALIDATE_BOOLEAN);

$demoEntries = [];

$demoPrizes = [];

$secondsUntilDraw = 0;

if ($selectedEvent) {

    $eventStmt = $pdo->prepare("

        SELECT *

        FROM lucky_draw_events

        WHERE event_id = :event_id

    ");

    $eventStmt->execute([
        ':event_id' => $selectedEvent
    ]);

    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if ($event) {

        if ($event['status'] !== 'OPEN') {

            $secondsUntilDraw = max(
                0,
                strtotime($event['draw_date']) - time()
            );

        }

        $nextPrize = nextDrawablePrize($pdo, $selectedEvent);

        $winners = getWinnersList($pdo, $selectedEvent);

        if ($event['status'] === 'OPEN') {

            $stageState = 'open';

        } elseif ($event['status'] === 'COMPLETED') {

            $stageState = 'completed';

        } elseif (in_array($event['status'], ['CLOSED', 'DRAWING'], true)) {

            $stageState = $nextPrize ? 'ready' : 'completed';

        } else {

            $stageState = 'idle';

        }

        if ($stageState === 'ready') {

            $ticketStmt = $pdo->prepare("

                SELECT
                    t.ticket_number

                FROM lucky_draw_tickets t

                WHERE t.event_id = :event_id
                  AND t.status = 'ACTIVE'
                  AND NOT EXISTS (

                        SELECT 1

                        FROM lucky_draw_winners w

                        WHERE w.event_id = t.event_id
                          AND w.student_id = t.student_id

                  )

                ORDER BY t.ticket_number

            ");

            $ticketStmt->execute([
                ':event_id' => $selectedEvent
            ]);

            $eligibleTickets = $ticketStmt->fetchAll(PDO::FETCH_COLUMN);

        }

        if ($demoMode) {

            $demoEntries = $pdo->prepare("

                SELECT TOP 12
                    t.ticket_number,
                    s.student_name,
                    s.class_name

                FROM lucky_draw_tickets t

                INNER JOIN test_students s
                    ON s.student_id = t.student_id

                WHERE t.event_id = :event_id
                  AND t.status = 'ACTIVE'

                ORDER BY NEWID()

            ");

            $demoEntries->execute([
                ':event_id' => $selectedEvent
            ]);

            $demoEntries = $demoEntries->fetchAll(PDO::FETCH_ASSOC);

            if (count($demoEntries) === 0) {

                $demoEntries = [];

                for ($i = 1; $i <= 8; $i++) {

                    $demoEntries[] = [
                        'ticket_number' => 'DEMO-00' . $i,
                        'student_name'  => 'Student ' . $i,
                        'class_name'    => 'Demo Class'
                    ];

                }

            }

            $demoPrizes = $pdo->prepare("

                SELECT prize_name

                FROM lucky_draw_prizes

                WHERE event_id = :event_id

                ORDER BY prize_position

            ");

            $demoPrizes->execute([
                ':event_id' => $selectedEvent
            ]);

            $demoPrizes = array_column($demoPrizes->fetchAll(PDO::FETCH_ASSOC), 'prize_name');

            if (count($demoPrizes) === 0) {

                $demoPrizes = [
                    'Grand Prize',
                    'Runner-Up Prize',
                    'Consolation Prize',
                    'Mystery Gift',
                    'Star Prize',
                    'Fun Prize'
                ];

            }

            // Demo always shows the wheel, no matter the event state.
            $stageState = 'ready';

            $nextPrize = $nextPrize ?: [
                'prize_name' => $demoPrizes[0]
            ];

        }

    }

}

$pageTitle = 'Live TV / Projector';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container-fluid px-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-broadcast-pin"></i> Live TV / Projector
            </h2>

            <p class="text-muted mb-0">
                Automatic lucky draw - as soon as sales close, the wheel spins and winners are announced one by one.
            </p>
        </div>

        <span class="badge bg-danger live-dot">
            <i class="bi bi-broadcast"></i> LIVE
        </span>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" class="row g-3">

                <div class="col-md-8">

                    <select
                        name="event_id"
                        class="form-select"
                    >

                        <?php foreach ($events as $ev): ?>

                            <option
                                value="<?= $ev['event_id'] ?>"
                                <?= $selectedEvent == $ev['event_id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($ev['event_name']) ?>
                                (<?= htmlspecialchars($ev['status']) ?>) -
                                <?= (int)$ev['available_prizes'] ?> prizes left
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-4 d-flex gap-2">

                    <a
                        href="draw.php?event_id=<?= $selectedEvent ?>"
                        class="btn btn-outline-secondary w-100"
                    >
                        Manual Draw Engine
                    </a>

                    <a
                        href="?event_id=<?= $selectedEvent ?>&demo=1"
                        class="btn btn-outline-warning w-100"
                    >
                        <i class="bi bi-play-fill"></i> Play Demo Animation
                    </a>

                </div>

            </form>

        </div>

    </div>


    <?php if (!$event): ?>

        <div class="alert alert-warning">
            No events available. Create an event first.
        </div>

    <?php else: ?>


        <div
            id="liveStage"
            class="card border-0 shadow mb-4"
            data-state="<?= $stageState ?>"
        >

            <?php if ($demoMode): ?>

                <div class="alert alert-warning border-0 rounded-0 mb-0 text-center fw-semibold">
                    <i class="bi bi-film"></i> DEMO MODE - animations only, no real winners are drawn. <a href="?event_id=<?= $selectedEvent ?>">Exit demo</a>
                </div>

            <?php endif; ?>

            <?php if ($stageState === 'ready'): ?>

                <div class="card-body text-center py-5">

                    <div class="fs-4 text-uppercase text-muted fw-semibold mb-1">
                        Up Next
                    </div>

                    <div class="display-5 fw-bold text-primary mb-4" id="nextPrizeName">
                        <?= htmlspecialchars($nextPrize['prize_name']) ?>
                    </div>

                    <div class="draw-wheel-wrap mb-4">

                        <div class="draw-wheel-rim"></div>

                        <div class="draw-wheel" id="drawWheel">

                            <?php for ($i = 0; $i < 8; $i++): ?>

                                <div
                                    class="wheel-box"
                                    id="wbox<?= $i ?>"
                                    style="transform: rotate(<?= $i * 45 ?>deg) translateY(-170px);"
                                >
                                    ?
                                </div>

                            <?php endfor; ?>

                        </div>

                        <div class="draw-wheel-center" id="wheelCenter">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>

                    </div>

                    <div class="fs-5 text-muted" id="stageStatus">
                        <?= $secondsUntilDraw > 0
                            ? 'Draw starts at ' . date('h:i A', strtotime($event['draw_date']))
                            : 'Ready to draw...' ?>
                    </div>

                    <div id="winnerReveal" style="display:none;"></div>

                </div>

            <?php elseif ($stageState === 'open'): ?>

                <div class="card-body text-center py-5">

                    <div class="display-6 mb-3">
                        <i class="bi bi-hourglass-split"></i>
                    </div>

                    <h4 class="fw-bold">
                        Waiting for ticket sales to close
                    </h4>

                    <p class="text-muted mb-0">
                        <?= htmlspecialchars($event['event_name']) ?>
                        is still OPEN. Close sales from the Events page and the draw will start automatically.
                    </p>

                </div>

            <?php elseif ($stageState === 'completed'): ?>

                <div class="card-body text-center py-5">

                    <div class="display-6 mb-3">
                        <i class="bi bi-stars"></i>
                    </div>

                    <h4 class="fw-bold">
                        All prizes have been drawn!
                    </h4>

                    <p class="text-muted mb-0">
                        Congratulations to all the winners.
                    </p>

                </div>

            <?php else: ?>

                <div class="card-body text-center py-5">

                    <div class="display-6 mb-3">
                        <i class="bi bi-dice-5"></i>
                    </div>

                    <h4 class="fw-bold">
                        This event is not ready for the draw.
                    </h4>

                    <p class="text-muted mb-0">
                        Status: <?= htmlspecialchars($event['status']) ?>
                    </p>

                </div>

            <?php endif; ?>

        </div>


        <div class="card border-0 shadow">

            <div class="card-body">

                <h4 class="fw-bold mb-3">
                    <i class="bi bi-trophy-fill"></i> Winners
                </h4>

                <div id="winnersGrid">

                    <?php if (count($winners) === 0): ?>

                        <p class="text-muted">
                            No winners yet.
                        </p>

                    <?php else: ?>

                        <div class="row g-3">

                            <?php foreach ($winners as $w): ?>

                                <div class="col-md-6 col-lg-4">

                                    <div class="card h-100 border-0 bg-light">

                                        <div class="card-body text-center">

                                            <div class="fw-semibold text-muted small text-uppercase">
                                                <?= htmlspecialchars($w['prize_name']) ?>
                                            </div>

                                            <div class="fs-5 fw-bold mt-2">
                                                <?= htmlspecialchars($w['student_name']) ?>
                                            </div>

                                            <div class="text-muted small">
                                                <?= htmlspecialchars($w['class_name']) ?>
                                            </div>

                                            <div class="badge bg-dark mt-2">
                                                <?= htmlspecialchars($w['ticket_number']) ?>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>


<?php if ($event): ?>

<script>
(function () {

    const EVENT_ID = <?= (int)$selectedEvent ?>;
    const API = 'draw_api.php';

    const SPIN_MS = 2600;
    const EASE_MS = 1800;
    const POLL_MS = 4000;

    const BOX_COUNT = 8;
    const BOX_RADIUS = 170;

    const INITIAL_STATE = <?= json_encode($stageState) ?>;
    const INITIAL_SECONDS_UNTIL_DRAW = <?= (int)$secondsUntilDraw ?>;

    const DEMO_MODE = <?= json_encode($demoMode) ?>;
    const DEMO_ENTRIES = <?= json_encode($demoEntries) ?>;
    const DEMO_PRIZES = <?= json_encode($demoPrizes) ?>;

    let busy = false;

    let demoPrizeIndex = 0;

    const demoWinnersList = [];

    const stage = document.getElementById('liveStage');
    const grid = document.getElementById('winnersGrid');

    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    async function api(action) {
        const res = await fetch(API + '?event_id=' + EVENT_ID + '&action=' + action);
        return res.json();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /*
    |--------------------------------------------------------------------------
    | UI renderers
    |--------------------------------------------------------------------------
    */

    function renderWinners(winners) {
        if (!winners || winners.length === 0) {
            grid.innerHTML = '<p class="text-muted">No winners yet.</p>';
            return;
        }

        let html = '<div class="row g-3">';

        winners.forEach(function (w) {
            html += '' +
                '<div class="col-md-6 col-lg-4">' +
                    '<div class="card h-100 border-0 bg-light">' +
                        '<div class="card-body text-center">' +
                            '<div class="fw-semibold text-muted small text-uppercase">' + escapeHtml(w.prize_name) + '</div>' +
                            '<div class="fs-5 fw-bold mt-2">' + escapeHtml(w.student_name) + '</div>' +
                            '<div class="text-muted small">' + escapeHtml(w.class_name) + '</div>' +
                            '<div class="badge bg-dark mt-2">' + escapeHtml(w.ticket_number) + '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        });

        html += '</div>';

        grid.innerHTML = html;
    }

    function renderStage(s, statusText) {
        let boxes = '';

        for (let i = 0; i < BOX_COUNT; i++) {
            const angle = i * (360 / BOX_COUNT);
            boxes += '<div class="wheel-box" id="wbox' + i + '" style="transform: rotate(' + angle + 'deg) translateY(-' + BOX_RADIUS + 'px);">?</div>';
        }

        const status = statusText || 'Spinning the wheel...';

        stage.innerHTML = '' +
            '<div class="card-body text-center py-5">' +
                '<div class="fs-4 text-uppercase text-muted fw-semibold mb-1">Up Next</div>' +
                '<div class="display-5 fw-bold text-primary mb-4" id="nextPrizeName">' +
                    (s.next_prize ? escapeHtml(s.next_prize.prize_name) : (DEMO_MODE ? escapeHtml(DEMO_PRIZES[0] || 'Demo Prize') : '-')) +
                '</div>' +
                '<div class="draw-wheel-wrap mb-4">' +
                    '<div class="draw-wheel-rim"></div>' +
                    '<div class="draw-wheel" id="drawWheel">' + boxes + '</div>' +
                    '<div class="draw-wheel-center" id="wheelCenter"><i class="bi bi-ticket-perforated"></i></div>' +
                '</div>' +
                '<div class="fs-5 text-muted" id="stageStatus">' + escapeHtml(status) + '</div>' +
                '<div id="winnerReveal" style="display:none;"></div>' +
            '</div>';

        stage.dataset.state = 'ready';
    }

    function renderWaitingSales() {
        stage.innerHTML = '' +
            '<div class="card-body text-center py-5">' +
                '<div class="display-6 mb-3"><i class="bi bi-hourglass-split"></i></div>' +
                '<h4 class="fw-bold">Waiting for ticket sales to close</h4>' +
                '<p class="text-muted mb-0">Close sales from the Events page and the draw will start automatically.</p>' +
            '</div>';
        stage.dataset.state = 'open';
    }

    function renderCompleted() {
        stage.innerHTML = '' +
            '<div class="card-body text-center py-5">' +
                '<div class="display-6 mb-3"><i class="bi bi-stars"></i></div>' +
                '<h4 class="fw-bold">All prizes have been drawn!</h4>' +
                '<p class="text-muted mb-0">Congratulations to all the winners.</p>' +
            '</div>';
        stage.dataset.state = 'completed';
    }

    function renderError(msg) {
        stage.innerHTML = '' +
            '<div class="card-body text-center py-5">' +
                '<div class="display-6 mb-3"><i class="bi bi-exclamation-triangle-fill"></i></div>' +
                '<h4 class="fw-bold text-danger">' + escapeHtml(msg) + '</h4>' +
            '</div>';
        stage.dataset.state = 'error';
    }

    /*
    |--------------------------------------------------------------------------
    | Confetti
    |--------------------------------------------------------------------------
    */

    const COLORS = ['#ff5252', '#ffd740', '#40c4ff', '#69f0ae', '#e040fb', '#ffab40'];

    function confettiBurst() {
        const holder = document.createElement('div');
        holder.className = 'confetti-holder';
        document.body.appendChild(holder);

        for (let i = 0; i < 70; i++) {
            const piece = document.createElement('div');
            piece.className = 'confetti-piece';
            piece.style.left = (Math.random() * 100) + 'vw';
            piece.style.background = COLORS[Math.floor(Math.random() * COLORS.length)];
            piece.style.animationDelay = (Math.random() * 0.6) + 's';
            piece.style.animationDuration = (2.2 + Math.random() * 1.5) + 's';
            holder.appendChild(piece);
        }

        setTimeout(function () {
            holder.remove();
        }, 6000);
    }

    /*
    |--------------------------------------------------------------------------
    | Wheel spin animation (like a real lottery machine)
    |--------------------------------------------------------------------------
    */

    function resetWheel() {
        const wheel = document.getElementById('drawWheel');
        if (wheel) wheel.style.transform = 'rotate(0deg)';
        for (let i = 0; i < BOX_COUNT; i++) {
            const b = document.getElementById('wbox' + i);
            if (b) {
                b.classList.remove('box-winner');
                b.textContent = '?';
                b.style.transform = 'rotate(' + (i * (360 / BOX_COUNT)) + 'deg) translateY(-' + BOX_RADIUS + 'px)';
                b.style.fontSize = '';
            }
        }
    }

    async function spinWheel(tickets, winIndex, wheel) {
        const boxes = [];
        for (let i = 0; i < BOX_COUNT; i++) {
            boxes.push(document.getElementById('wbox' + i));
        }

        const start = Date.now();
        const total = SPIN_MS + EASE_MS;
        const slotAngle = 360 / BOX_COUNT;

        const targetRot = 360 * 4 - winIndex * slotAngle;

        let lastSwap = 0;

        return new Promise(function (resolve) {

            function step() {
                const t = Date.now() - start;
                let r;

                if (t < SPIN_MS) {
                    r = (t / SPIN_MS) * (360 * 4);
                } else if (t < total) {
                    const e = (t - SPIN_MS) / EASE_MS;
                    const eased = 1 - Math.pow(1 - e, 3);
                    r = 360 * 4 + eased * (targetRot - 360 * 4);
                } else {
                    r = targetRot;
                }

                wheel.style.transform = 'rotate(' + r + 'deg)';

                if (t - lastSwap > 100 && t < SPIN_MS + EASE_MS * 0.5) {
                    boxes.forEach(function (b) {
                        b.textContent = tickets[Math.floor(Math.random() * tickets.length)];
                    });
                    lastSwap = t;
                }

                if (t < total) {
                    requestAnimationFrame(step);
                } else {
                    boxes.forEach(function (b) {
                        b.textContent = '';
                    });
                    resolve();
                }
            }

            requestAnimationFrame(step);

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Winner reveal (replaces the wheel)
    |--------------------------------------------------------------------------
    */

    function renderWinnerInPlace(w, completed) {
        const buttonHtml = completed
            ? '<div class="mt-4 fs-4 fw-bold text-success"><i class="bi bi-stars"></i> All prizes have been drawn!</div>'
            : '<button id="btnNextDraw" class="btn btn-primary btn-lg mt-4 px-5"><i class="bi bi-play-fill"></i> Start Next Prize Announcement</button>';

        stage.innerHTML = '' +
            '<div class="card-body text-center py-5">' +
                '<div class="fs-3 text-uppercase text-warning fw-bold mb-2"><i class="bi bi-stars"></i> Winner <i class="bi bi-stars"></i></div>' +
                '<div class="display-5 fw-bold mb-1">' + escapeHtml(w.student_name) + '</div>' +
                '<div class="fs-4 text-muted mb-3">' + escapeHtml(w.class_name) + '</div>' +
                '<div class="d-inline-block winner-prize-tag">' + escapeHtml(w.prize_name) + '</div>' +
                '<div class="fs-5 mt-3"><span class="badge bg-dark">Ticket ' + escapeHtml(w.ticket_number) + '</span></div>' +
                buttonHtml +
            '</div>';

        stage.dataset.state = 'winner';

        confettiBurst();
    }

    function waitForNextClick() {
        return new Promise(function (resolve) {
            const btn = document.getElementById('btnNextDraw');
            if (!btn) {
                resolve();
                return;
            }
            btn.addEventListener('click', function onClick() {
                btn.removeEventListener('click', onClick);
                resolve();
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Draw cycle - one spin per prize, then wait for the button
    |--------------------------------------------------------------------------
    */

    async function runDrawCycle(s) {

        renderStage(s);

        await sleep(600);

        const entries = DEMO_MODE
            ? DEMO_ENTRIES
            : (s.eligible_tickets || []);

        if (entries.length === 0) {
            renderError('No eligible tickets to draw.');
            return;
        }

        const winIndex = Math.floor(Math.random() * BOX_COUNT);

        const spinTickets = DEMO_MODE
            ? entries.map(function (e) { return e.ticket_number; })
            : entries;

        const dPromise = DEMO_MODE ? null : api('draw');

        await spinWheel(spinTickets, winIndex, document.getElementById('drawWheel'));

        let d;

        if (DEMO_MODE) {

            const pick = entries[Math.floor(Math.random() * entries.length)];

            const prizeName = DEMO_PRIZES[demoPrizeIndex % DEMO_PRIZES.length];
            demoPrizeIndex++;

            const demoWinner = {
                ticket_number: pick.ticket_number,
                prize_name: prizeName,
                student_name: pick.student_name,
                class_name: pick.class_name
            };

            demoWinnersList.unshift(demoWinner);

            d = {
                ok: true,
                winner: demoWinner,
                completed: false,
                winners: demoWinnersList
            };

        } else {

            d = await dPromise;

        }

        if (!d.ok) {
            if (d.completed) {
                renderCompleted();
            } else {
                renderError(d.error || 'Draw failed.');
            }
            return;
        }

        renderWinnerInPlace(d.winner, d.completed);

        renderWinners(d.winners || []);

        if (d.completed) {
            return;
        }

        // Wait for the operator to start the next prize.
        await waitForNextClick();

        if (DEMO_MODE) {
            await runDrawCycle(s);
            return;
        }

        const fresh = await api('status');

        if (!fresh.ok) {
            renderError(fresh.error || 'Unknown error.');
            return;
        }

        if (fresh.completed) {
            renderCompleted();
            return;
        }

        if (!fresh.can_draw) {
            renderError('Waiting for the next draw...');
            return;
        }

        await runDrawCycle(fresh);
    }

    /*
    |--------------------------------------------------------------------------
    | Countdown overlay (10, 9, 8 ... 0, then "Let's Start!")
    |--------------------------------------------------------------------------
    */

    function ensureCountdownOverlay() {
        let ov = document.getElementById('countdownOverlay');
        if (!ov) {
            ov = document.createElement('div');
            ov.id = 'countdownOverlay';
            ov.className = 'draw-countdown';
            ov.innerHTML = '<div class="draw-countdown-num"></div>';
            document.body.appendChild(ov);
        }
        return ov;
    }

    function showCountdown(n) {
        const ov = ensureCountdownOverlay();
        const num = ov.querySelector('.draw-countdown-num');
        num.textContent = String(n);
        num.classList.remove('pop');
        void num.offsetWidth;
        num.classList.add('pop');
    }

    function showLetsStart() {
        const ov = ensureCountdownOverlay();
        ov.innerHTML = '<div class="draw-lets-start"><i class="bi bi-stars"></i> Let\'s Start!</div>';
    }

    function clearCountdown() {
        const ov = document.getElementById('countdownOverlay');
        if (ov) ov.remove();
    }

    /*
    |--------------------------------------------------------------------------
    | Wait for the scheduled draw time, then count down and start
    |--------------------------------------------------------------------------
    */

    async function waitForDrawTime(s) {
        const secondsLeft = Math.max(0, s.seconds_until_draw != null ? s.seconds_until_draw : INITIAL_SECONDS_UNTIL_DRAW);

        const target = Date.now() + secondsLeft * 1000;

        renderStage(s, 'Draw starts at ' + (s.draw_date || ''));

        let lastNum = null;

        while (true) {
            const left = Math.ceil((target - Date.now()) / 1000);

            if (left > 10) {
                await sleep(500);
                continue;
            }

            if (left >= 1) {
                if (lastNum !== left) {
                    showCountdown(left);
                    lastNum = left;
                }
                await sleep(250);
                continue;
            }

            if (lastNum !== 0) {
                showCountdown(0);
                lastNum = 0;
            }
            await sleep(800);
            break;
        }

        showLetsStart();
        await sleep(1600);
        clearCountdown();

        const fresh = await api('status');

        if (!fresh.ok) {
            renderError(fresh.error || 'Unknown error.');
            return;
        }

        await runDrawCycle(fresh);
    }

    /*
    |--------------------------------------------------------------------------
    | Main loop
    |--------------------------------------------------------------------------
    */

    async function tick() {
        if (busy) return;

        let s;
        try {
            s = await api('status');
        } catch (e) {
            renderError('Connection error. Retrying...');
            setTimeout(tick, POLL_MS);
            return;
        }

        if (!s.ok) {
            renderError(s.error || 'Unknown error.');
            setTimeout(tick, POLL_MS);
            return;
        }

        renderWinners(s.winners);

        if (DEMO_MODE) {
            busy = true;
            try {
                await runDrawCycle(s);
            } catch (e) {
                renderError('Draw error: ' + e.message);
            } finally {
                busy = false;
            }
            setTimeout(tick, 800);
            return;
        }

        if (s.completed) {
            if (stage.dataset.state !== 'winner') {
                renderCompleted();
            }
            setTimeout(tick, POLL_MS * 3);
            return;
        }

        if (s.status === 'OPEN') {
            renderWaitingSales();
            setTimeout(tick, POLL_MS);
            return;
        }

        const secsLeft = s.seconds_until_draw != null ? s.seconds_until_draw : INITIAL_SECONDS_UNTIL_DRAW;

        if (s.next_prize && secsLeft > 0) {

            // Draw time not reached yet - show the wheel, then count down 10..0.
            busy = true;
            try {
                await waitForDrawTime(s);
            } catch (e) {
                renderError('Draw error: ' + e.message);
            } finally {
                busy = false;
            }
            setTimeout(tick, 800);
            return;
        }

        if (s.can_draw) {

            if (!s.eligible_tickets || s.eligible_tickets.length === 0) {
                renderError('No eligible tickets to draw yet.');
                setTimeout(tick, POLL_MS * 3);
                return;
            }

            busy = true;
            try {
                await runDrawCycle(s);
            } catch (e) {
                renderError('Draw error: ' + e.message);
            } finally {
                busy = false;
            }
            setTimeout(tick, 800);
            return;
        }

        // CLOSED/DRAWING but nothing to draw right now
        renderError('Waiting for the next draw...');
        setTimeout(tick, POLL_MS);
    }

    /*
    |--------------------------------------------------------------------------
    | Start
    |--------------------------------------------------------------------------
    */

    tick();

}());
</script>

<?php endif; ?>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>