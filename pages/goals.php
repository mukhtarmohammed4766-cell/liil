<?php

/* =========================================
   LIFEOS - GOALS PAGE
========================================= */


/*
|--------------------------------------------------------------------------
| 1. DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../database/db.php';


/*
|--------------------------------------------------------------------------
| 2. CONFIGURATION
|--------------------------------------------------------------------------
*/

// Temporary user ID for testing.
$userId = 1;

// Page feedback.
$message = '';
$messageType = '';

// Escape output safely.
$escapeGoalOutput = static function ($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};


/*
|--------------------------------------------------------------------------
| 3. ADD NEW GOAL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_goal'])) {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineInput = trim($_POST['deadline'] ?? '');

    if ($title === '') {

        $message = 'Please enter a goal title.';
        $messageType = 'error';

    } else {

        $deadline = null;

        // Validate optional deadline.
        if ($deadlineInput !== '') {

            $date = DateTime::createFromFormat('!Y-m-d', $deadlineInput);

            if (
                !$date ||
                $date->format('Y-m-d') !== $deadlineInput
            ) {

                $message = 'Please enter a valid deadline.';
                $messageType = 'error';

            } else {

                $deadline = $deadlineInput;

            }
        }

        // Insert goal.
        if ($messageType !== 'error') {

            $stmt = $conn->prepare(
                "INSERT INTO goals
                (userid, title, description, progress, deadline, status)
                VALUES (?, ?, ?, 0, ?, 'pending')"
            );

            if ($stmt) {

                $stmt->bind_param(
                    'isss',
                    $userId,
                    $title,
                    $description,
                    $deadline
                );

                if ($stmt->execute()) {

                    $message = 'Goal added successfully!';
                    $messageType = 'success';

                } else {

                    $message = 'Failed to add goal.';
                    $messageType = 'error';

                }

                $stmt->close();

            } else {

                $message = 'Database error. Please try again.';
                $messageType = 'error';

            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| 4. UPDATE GOAL PROGRESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_goal'])) {

    $goalId = (int)($_POST['goal_id'] ?? 0);

    $progress = filter_var(
        $_POST['progress'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $goalId <= 0 ||
        $progress === false ||
        $progress < 0 ||
        $progress > 100
    ) {

        $message = 'Invalid goal progress.';
        $messageType = 'error';

    } else {

        $status = ($progress === 100) ? 'completed' : 'pending';

        $stmt = $conn->prepare(
            "UPDATE goals
             SET progress = ?, status = ?
             WHERE id = ? AND userid = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                'isii',
                $progress,
                $status,
                $goalId,
                $userId
            );

            if ($stmt->execute()) {

                $message = 'Goal updated successfully!';
                $messageType = 'success';

            } else {

                $message = 'Failed to update goal.';
                $messageType = 'error';

            }

            $stmt->close();

        } else {

            $message = 'Database error. Please try again.';
            $messageType = 'error';

        }
    }
}


/*
|--------------------------------------------------------------------------
| 5. DELETE GOAL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_goal'])) {

    $goalId = (int)($_POST['goal_id'] ?? 0);

    if ($goalId <= 0) {

        $message = 'Invalid goal.';
        $messageType = 'error';

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM goals
             WHERE id = ? AND userid = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                'ii',
                $goalId,
                $userId
            );

            if ($stmt->execute()) {

                $message = $stmt->affected_rows > 0
                    ? 'Goal deleted successfully!'
                    : 'Goal not found.';

                $messageType = $stmt->affected_rows > 0
                    ? 'success'
                    : 'error';

            } else {

                $message = 'Failed to delete goal.';
                $messageType = 'error';

            }

            $stmt->close();

        } else {

            $message = 'Database error. Please try again.';
            $messageType = 'error';

        }
    }
}


/*
|--------------------------------------------------------------------------
| 6. GET GOAL STATISTICS
|--------------------------------------------------------------------------
*/

$stats = [
    'total' => 0,
    'completed' => 0,
    'average_progress' => 0
];

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END),
            0
        ) AS completed,
        COALESCE(AVG(progress), 0) AS average_progress
     FROM goals
     WHERE userid = ?"
);

if ($stmt) {

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $stats = $result->fetch_assoc() ?: $stats;

    $stmt->close();

}


/*
|--------------------------------------------------------------------------
| 7. GET ALL GOALS
|--------------------------------------------------------------------------
*/

$goals = [];

$stmt = $conn->prepare(
    "SELECT id, title, description, progress, deadline, status
     FROM goals
     WHERE userid = ?
     ORDER BY id DESC"
);

if ($stmt) {

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($goal = $result->fetch_assoc()) {
        $goals[] = $goal;
    }

    $stmt->close();

}


/*
|--------------------------------------------------------------------------
| 8. DISPLAY GOALS PAGE
|--------------------------------------------------------------------------
*/

?>

<div class="goals-page">

    <!-- Page Header -->
    <header class="goals-header">

        <div class="goals-heading">

            <span class="goals-label">
                LIFEOS / PERSONAL GROWTH
            </span>

            <h1>My Goals <span>🎯</span></h1>

            <p>
                Set your goals, track your progress and achieve more.
            </p>

        </div>

        <button
            type="button"
            class="add-goal-btn"
            onclick="showGoalForm()"
        >
            + Add New Goal
        </button>

    </header>


    <!-- Feedback Message -->
    <?php if ($message !== ''): ?>

        <div class="goal-message <?php echo $escapeGoalOutput($messageType); ?>">

            <?php echo $escapeGoalOutput($message); ?>

        </div>

    <?php endif; ?>


    <!-- Goal Statistics -->
    <section class="goal-stats">

        <article class="goal-stat-card">

            <div class="goal-stat-icon goal-blue">🎯</div>

            <div>
                <span>Total Goals</span>
                <h2><?php echo (int)$stats['total']; ?></h2>
            </div>

        </article>


        <article class="goal-stat-card">

            <div class="goal-stat-icon goal-green">🏆</div>

            <div>
                <span>Completed Goals</span>
                <h2><?php echo (int)$stats['completed']; ?></h2>
            </div>

        </article>


        <article class="goal-stat-card">

            <div class="goal-stat-icon goal-purple">📈</div>

            <div>
                <span>Average Progress</span>
                <h2><?php echo round((float)$stats['average_progress']); ?>%</h2>
            </div>

        </article>

    </section>


    <!-- Add Goal Form -->
    <section class="goal-form-card" id="goalForm">

        <div class="goal-section-heading">
            <div>
                <h2>Create New Goal</h2>
                <p>Add a new personal goal to your list.</p>
            </div>
        </div>

        <form method="POST" action="pages/goals.php">

            <div class="goal-form-grid">

                <div class="goal-input-group">

                    <label for="goalTitle">Goal Title</label>

                    <input
                        type="text"
                        id="goalTitle"
                        name="title"
                        placeholder="Example: Learn PHP"
                        maxlength="255"
                        required
                    >

                </div>


                <div class="goal-input-group">

                    <label for="goalDeadline">Deadline</label>

                    <input
                        type="date"
                        id="goalDeadline"
                        name="deadline"
                    >

                </div>


                <div class="goal-input-group goal-input-wide">

                    <label for="goalDescription">Description</label>

                    <textarea
                        id="goalDescription"
                        name="description"
                        placeholder="Write your goal details..."
                        rows="4"
                    ></textarea>

                </div>

            </div>


            <div class="goal-form-actions">

                <button
                    type="submit"
                    name="add_goal"
                    value="1"
                    class="save-goal-btn"
                >
                    Save Goal
                </button>

                <button
                    type="button"
                    class="cancel-goal-btn"
                    onclick="hideGoalForm()"
                >
                    Cancel
                </button>

            </div>

        </form>

    </section>


    <!-- Goals List -->
    <section class="goals-list">

        <div class="goals-section-heading">

            <div>
                <h2>Your Goals</h2>
                <p>Keep moving forward, one goal at a time.</p>
            </div>

            <span class="goal-total-badge">
                <?php echo count($goals); ?> Goals
            </span>

        </div>


        <?php if (!empty($goals)): ?>

            <?php foreach ($goals as $goal): ?>

                <?php

                $goalId = (int)$goal['id'];

                $progress = max(
                    0,
                    min(100, (int)$goal['progress'])
                );

                $isCompleted = ($goal['status'] === 'completed');

                ?>

                <!-- Single Goal Card -->
                <article class="goal-card">

                    <div class="goal-card-top">

                        <div class="goal-details">

                            <h3>
                                <?php echo $escapeGoalOutput($goal['title']); ?>
                            </h3>

                            <p>
                                <?php echo $escapeGoalOutput(
                                    $goal['description'] ?: 'No description provided.'
                                ); ?>
                            </p>

                        </div>


                        <span class="goal-status <?php echo $isCompleted ? 'completed' : 'pending'; ?>">

                            <?php echo $isCompleted ? 'Completed' : 'In Progress'; ?>

                        </span>

                    </div>


                    <!-- Progress -->
                    <div class="goal-progress-info">

                        <span>Progress</span>

                        <strong><?php echo $progress; ?>%</strong>

                    </div>


                    <div
                        class="goal-progress-track"
                        role="progressbar"
                        aria-valuenow="<?php echo $progress; ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label="Goal progress"
                    >

                        <div
                            class="goal-progress-fill"
                            style="width: <?php echo $progress; ?>%"
                        ></div>

                    </div>


                    <!-- Footer -->
                    <div class="goal-card-footer">

                        <span class="goal-deadline">

                            <span>📅 Deadline:</span>

                            <?php

                            echo !empty($goal['deadline'])
                                ? $escapeGoalOutput($goal['deadline'])
                                : 'Not set';

                            ?>

                        </span>


                        <div class="goal-actions">

                            <!-- Update Progress -->
                            <form
                                method="POST"
                                action="pages/goals.php"
                                class="goal-progress-form"
                            >

                                <input
                                    type="hidden"
                                    name="goal_id"
                                    value="<?php echo $goalId; ?>"
                                >

                                <input
                                    type="number"
                                    name="progress"
                                    min="0"
                                    max="100"
                                    value="<?php echo $progress; ?>"
                                    aria-label="Progress percentage"
                                    required
                                >

                                <button
                                    type="submit"
                                    name="update_goal"
                                    value="1"
                                >
                                    Update
                                </button>

                            </form>


                            <!-- Delete Goal -->
                            <form
                                method="POST"
                                action="pages/goals.php"
                                class="goal-delete-form"
                            >

                                <input
                                    type="hidden"
                                    name="goal_id"
                                    value="<?php echo $goalId; ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_goal"
                                    value="1"
                                    class="delete-goal-btn"
                                >
                                    Delete
                                </button>

                            </form>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>


        <?php else: ?>

            <!-- Empty State -->
            <div class="empty-goals">

                <div class="empty-goals-icon">🎯</div>

                <h3>No goals yet</h3>

                <p>
                    You haven't created any goals.
                    Start by adding your first goal!
                </p>

                <button
                    type="button"
                    onclick="showGoalForm()"
                >
                    Create Your First Goal
                </button>

            </div>

        <?php endif; ?>

    </section>

</div>

<style>/* =========================================
   LIFEOS - GOALS PAGE
========================================= */


/* 1. MAIN PAGE */

.goals-page {
    color: #eaf3ff;
    position: relative;
    animation: goalsAppear .5s ease both;
}

@keyframes goalsAppear {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


/* 2. PAGE HEADER */

.goals-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 28px 32px;
    margin-bottom: 22px;
    border: 1px solid #2366ae;
    border-radius: 20px;

    background:
        radial-gradient(circle at 90% 10%, #1676d955, transparent 35%),
        linear-gradient(120deg, #06172f, #103b70, #08234b);

    box-shadow: 0 12px 30px #00152e66;
}

.goals-label {
    color: #55c7ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
}

.goals-heading h1 {
    color: white;
    font-size: 30px;
    margin: 10px 0;
}

.goals-heading h1 span {
    color: #39c8ff;
}

.goals-heading p {
    color: #c4d9f7;
    font-size: 13px;
    margin: 0;
}


/* 3. ADD GOAL BUTTON */

.add-goal-btn {
    border: 1px solid #8de8ff;
    border-radius: 10px;
    padding: 13px 22px;
    color: #06224a;
    font-weight: 800;
    font-size: 12px;

    background: linear-gradient(110deg, #38bdf8, #a5f3fc);

    box-shadow: 0 5px 0 #0874b9, 0 10px 20px #0284c755;

    cursor: pointer;
    transition: .25s ease;
}

.add-goal-btn:hover {
    transform: translateY(-3px);
}


/* 4. STATISTICS */

.goal-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 22px;
}

.goal-stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;

    border: 1px solid #24558e;
    border-radius: 17px;

    background: linear-gradient(145deg, #102e59, #071a38);

    box-shadow: 0 7px 0 #04132b, 0 12px 25px #00000035;

    transition: .3s ease;
}

.goal-stat-card:hover {
    transform: translateY(-5px);
}

.goal-stat-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;

    display: grid;
    place-items: center;

    border-radius: 15px;
    font-size: 24px;
}

.goal-blue {
    color: #6bd5ff;
    background: linear-gradient(145deg, #0875e8, #0645a0);
}

.goal-green {
    color: #65ffc4;
    background: linear-gradient(145deg, #078a70, #07503f);
}

.goal-purple {
    color: #d3b5ff;
    background: linear-gradient(145deg, #7538d8, #3d1689);
}

.goal-stat-card span {
    color: #a9c5e9;
    font-size: 11px;
}

.goal-stat-card h2 {
    color: white;
    font-size: 27px;
    margin: 5px 0 0;
}


/* 5. FORM PANEL */

.goal-form-card {
    display: none;

    padding: 25px;
    margin-bottom: 22px;

    border: 1px solid #24558e;
    border-radius: 18px;

    background:
        radial-gradient(circle at 100% 0%, #174b8430, transparent 40%),
        linear-gradient(145deg, #0c2850, #061832);

    box-shadow: 0 7px 0 #041329, 0 15px 30px #00000030;
}

.goal-form-card.show {
    display: block;
    animation: goalsAppear .3s ease;
}

.goal-section-heading {
    margin-bottom: 22px;
}

.goal-section-heading h2 {
    color: white;
    font-size: 18px;
    margin: 0;
}

.goal-section-heading p {
    color: #91afd3;
    font-size: 11px;
    margin-top: 6px;
}


/* 6. FORM GRID */

.goal-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.goal-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.goal-input-wide {
    grid-column: 1 / -1;
}

.goal-input-group label {
    color: #c8ddf7;
    font-size: 11px;
    font-weight: 600;
}

.goal-input-group input,
.goal-input-group textarea {
    width: 100%;
    box-sizing: border-box;

    color: white;
    background: #071b38;

    border: 1px solid #285486;
    border-radius: 10px;

    padding: 12px 14px;
    font-size: 12px;

    outline: none;
    transition: .25s;
}

.goal-input-group input::placeholder,
.goal-input-group textarea::placeholder {
    color: #718cab;
}

.goal-input-group input:focus,
.goal-input-group textarea:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px #0ea5e933;
}

.goal-input-group input[type="date"] {
    color-scheme: dark;
}

.goal-input-group textarea {
    resize: vertical;
    min-height: 100px;
}


/* 7. FORM BUTTONS */

.goal-form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.goal-form-actions button {
    border-radius: 10px;
    padding: 12px 22px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .25s;
}

.save-goal-btn {
    color: #06224a;
    background: linear-gradient(110deg, #38bdf8, #a5f3fc);
    border: 1px solid #8de8ff;
}

.cancel-goal-btn {
    color: #d7e9ff;
    background: #12345c;
    border: 1px solid #315d8d;
}

.goal-form-actions button:hover {
    transform: translateY(-3px);
}


/* 8. GOALS SECTION */

.goals-list {
    margin-top: 25px;
}

.goals-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.goals-section-heading h2 {
    color: white;
    font-size: 19px;
    margin: 0;
}

.goals-section-heading p {
    color: #91afd3;
    font-size: 11px;
    margin-top: 6px;
}

.goal-total-badge {
    color: #64d5ff;
    background: #0875d52b;
    border: 1px solid #247bc0;
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 11px;
}


/* 9. GOAL CARD */

.goal-card {
    padding: 22px;
    margin-bottom: 17px;

    border: 1px solid #24558e;
    border-radius: 17px;

    background:
        radial-gradient(circle at 100% 0%, #174b8425, transparent 40%),
        linear-gradient(145deg, #102e59, #071a38);

    box-shadow: 0 7px 0 #041329, 0 12px 25px #00000030;

    transition: .3s ease;
}

.goal-card:hover {
    transform: translateY(-3px);
    border-color: #3686ce;
}

.goal-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.goal-details {
    min-width: 0;
}

.goal-details h3 {
    color: white;
    font-size: 16px;
    margin: 0 0 9px;
    overflow-wrap: anywhere;
}

.goal-details p {
    color: #91afd3;
    font-size: 12px;
    line-height: 1.6;
    margin: 0;
    overflow-wrap: anywhere;
}


/* 10. GOAL STATUS */

.goal-status {
    flex-shrink: 0;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
}

.goal-status.pending {
    color: #ffd18b;
    background: #8a4b102b;
    border: 1px solid #d9770670;
}

.goal-status.completed {
    color: #7cf5bd;
    background: #0478572b;
    border: 1px solid #10b98170;
}


/* 11. PROGRESS */

.goal-progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 22px 0 9px;
}

.goal-progress-info span {
    color: #a9c5e9;
    font-size: 11px;
}

.goal-progress-info strong {
    color: #55d8ff;
    font-size: 13px;
}

.goal-progress-track {
    width: 100%;
    height: 9px;
    overflow: hidden;
    border-radius: 20px;
    background: #071b38;
    border: 1px solid #285486;
}

.goal-progress-fill {
    height: 100%;
    border-radius: 20px;
    background: linear-gradient(90deg, #0875e8, #38bdf8, #a5f3fc);
    box-shadow: 0 0 12px #38bdf888;
    transition: width .4s ease;
}


/* 12. CARD FOOTER */

.goal-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
}

.goal-deadline {
    color: #91afd3;
    font-size: 11px;
}

.goal-deadline span {
    color: #55c7ff;
    font-weight: 700;
}


/* 13. ACTIONS */

.goal-actions {
    display: flex;
    align-items: center;
    gap: 9px;
}

.goal-progress-form {
    display: flex;
    align-items: center;
    gap: 7px;
}

.goal-progress-form input {
    width: 65px;
    box-sizing: border-box;
    padding: 9px;

    color: white;
    background: #071b38;

    border: 1px solid #285486;
    border-radius: 8px;

    outline: none;
}

.goal-progress-form input:focus {
    border-color: #38bdf8;
}

.goal-progress-form button,
.delete-goal-btn {
    padding: 9px 13px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
    transition: .25s;
}

.goal-progress-form button {
    color: #06224a;
    background: linear-gradient(110deg, #38bdf8, #a5f3fc);
    border: 1px solid #8de8ff;
}

.delete-goal-btn {
    color: #ff9ca8;
    background: #e11d4825;
    border: 1px solid #e11d4855;
}

.goal-progress-form button:hover,
.delete-goal-btn:hover {
    transform: translateY(-2px);
}

.delete-goal-btn:hover {
    color: white;
    background: #dc2626;
}


/* 14. EMPTY STATE */

.empty-goals {
    text-align: center;
    padding: 45px 20px;

    border: 1px solid #24558e;
    border-radius: 17px;

    background: linear-gradient(145deg, #102e59, #071a38);
}

.empty-goals-icon {
    font-size: 40px;
    margin-bottom: 12px;
}

.empty-goals h3 {
    color: white;
    font-size: 16px;
}

.empty-goals p {
    color: #91afd3;
    font-size: 12px;
    margin: 10px 0 20px;
}

.empty-goals button {
    color: #06224a;
    background: linear-gradient(110deg, #38bdf8, #a5f3fc);
    border: 1px solid #8de8ff;
    border-radius: 10px;
    padding: 12px 20px;
    font-weight: 800;
    cursor: pointer;
}


/* 15. MESSAGES */

.goal-message {
    padding: 14px 17px;
    margin-bottom: 18px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    animation: goalsAppear .3s ease;
}

.goal-message.success {
    color: #7cf5bd;
    background: #05966920;
    border: 1px solid #10b98170;
}

.goal-message.error {
    color: #ff9caa;
    background: #e11d4820;
    border: 1px solid #e11d4870;
}


/* 16. RESPONSIVE DESIGN */

@media (max-width: 900px) {

    .goal-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 750px) {

    .goals-header {
        padding: 22px;
    }

    .goals-heading h1 {
        font-size: 24px;
    }

    .goal-card-footer {
        flex-direction: column;
        align-items: flex-start;
    }

}

@media (max-width: 500px) {

    .goals-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .goal-stats {
        grid-template-columns: 1fr;
    }

    .goal-form-card {
        padding: 18px;
    }

    .goal-form-grid {
        grid-template-columns: 1fr;
    }

    .goal-input-wide {
        grid-column: auto;
    }

    .goal-card-top {
        flex-direction: column;
    }

    .goal-actions {
        flex-wrap: wrap;
    }

    .goal-card {
        padding: 17px;
    }

    .goals-section-heading {
        align-items: flex-start;
    }

}

@media (prefers-reduced-motion: reduce) {

    .goals-page,
    .goal-card,
    .goal-stat-card {
        animation: none !important;
        transition: none !important;
    }

}</style>