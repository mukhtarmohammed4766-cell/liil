<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/db.php';

$userId = (int)($_SESSION['user_id'] ?? 1);

date_default_timezone_set('Africa/Mogadishu');

function financeEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';

// Add transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $type = $_POST['type'] ?? '';
        $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
        $date = $_POST['transaction_date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        $validDate = DateTime::createFromFormat('Y-m-d', $date);

        if (
            $title === '' ||
            $category === '' ||
            !in_array($type, ['Income', 'Expense'], true) ||
            $amount === false ||
            $amount <= 0 ||
            !$validDate ||
            $validDate->format('Y-m-d') !== $date
        ) {
            $message = 'Please enter valid transaction details.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO finance
                (user_id, title, category, type, amount, transaction_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            if ($stmt) {
                $stmt->bind_param(
                    "isssdss",
                    $userId,
                    $title,
                    $category,
                    $type,
                    $amount,
                    $date,
                    $notes
                );

                if ($stmt->execute()) {
                    $message = 'Transaction added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Could not save the transaction.';
                    $messageType = 'error';
                }

                $stmt->close();
            } else {
                $message = 'Database error while preparing the transaction.';
                $messageType = 'error';
            }
        }
    }

    // Delete transaction
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM finance WHERE id = ? AND user_id = ?"
        );

        if ($stmt) {
            $stmt->bind_param("ii", $id, $userId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'Transaction deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Transaction was not found.';
                $messageType = 'error';
            }

            $stmt->close();
        }
    }
}

// Get financial statistics
$stats = [
    'income' => 0,
    'expenses' => 0,
    'balance' => 0,
    'total' => 0
];

$stmt = $conn->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END), 0) AS expenses,
        COUNT(*) AS total
     FROM finance
     WHERE user_id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = array_merge($stats, $result->fetch_assoc() ?: []);
    $stmt->close();
}

$stats['balance'] = (float)$stats['income'] - (float)$stats['expenses'];

// Get transactions
$transactions = [];

$stmt = $conn->prepare(
    "SELECT id, title, category, type, amount, transaction_date, notes
     FROM finance
     WHERE user_id = ?
     ORDER BY transaction_date DESC, id DESC"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    $stmt->close();
}
?>

<div class="finance-page">

    <div class="finance-header">
        <div>
            <span class="finance-label">MONEY MANAGEMENT</span>
            <h1>Finance Tracker</h1>
            <p>Track your income, control your expenses, and manage your money.</p>
        </div>

        <div class="finance-header-icon">💰</div>
    </div>

    <?php if ($message): ?>
        <div class="finance-message <?= financeEscape($messageType) ?>">
            <?= financeEscape($message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="finance-stats">

        <div class="finance-stat-card">
            <div class="finance-stat-icon income-icon">↗</div>
            <div>
                <span>Total Income</span>
                <h2>$<?= number_format((float)$stats['income'], 2) ?></h2>
                <small>All recorded income</small>
            </div>
        </div>

        <div class="finance-stat-card">
            <div class="finance-stat-icon expense-icon">↘</div>
            <div>
                <span>Total Expenses</span>
                <h2>$<?= number_format((float)$stats['expenses'], 2) ?></h2>
                <small>All recorded expenses</small>
            </div>
        </div>

        <div class="finance-stat-card">
            <div class="finance-stat-icon balance-icon">◆</div>
            <div>
                <span>Current Balance</span>
                <h2 class="<?= $stats['balance'] < 0 ? 'negative-balance' : '' ?>">
                    $<?= number_format((float)$stats['balance'], 2) ?>
                </h2>
                <small>Income minus expenses</small>
            </div>
        </div>

        <div class="finance-stat-card">
            <div class="finance-stat-icon total-icon">▤</div>
            <div>
                <span>Transactions</span>
                <h2><?= (int)$stats['total'] ?></h2>
                <small>Total recorded operations</small>
            </div>
        </div>

    </div>

    <!-- Add Transaction -->
    <section class="finance-form-card">

        <div class="finance-section-heading">
            <div>
                <span class="finance-label">NEW RECORD</span>
                <h2>Add Transaction</h2>
                <p>Enter your income or expense details below.</p>
            </div>
            <span class="finance-heading-icon">＋</span>
        </div>

        <form method="POST" class="finance-form" data-finance-form>
            <input type="hidden" name="action" value="add">

            <div class="finance-form-grid">

                <div class="finance-input-group">
                    <label>Transaction Title</label>
                    <input
                        type="text"
                        name="title"
                        placeholder="Example: Monthly salary"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="finance-input-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="">Choose category</option>
                        <option value="Salary">Salary</option>
                        <option value="Business">Business</option>
                        <option value="Food">Food</option>
                        <option value="Transport">Transport</option>
                        <option value="Shopping">Shopping</option>
                        <option value="Bills">Bills</option>
                        <option value="Education">Education</option>
                        <option value="Health">Health</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="finance-input-group">
                    <label>Transaction Type</label>
                    <select name="type" required>
                        <option value="">Choose type</option>
                        <option value="Income">Income</option>
                        <option value="Expense">Expense</option>
                    </select>
                </div>

                <div class="finance-input-group">
                    <label>Amount ($)</label>
                    <input
                        type="number"
                        name="amount"
                        placeholder="0.00"
                        min="0.01"
                        step="0.01"
                        required
                    >
                </div>

                <div class="finance-input-group">
                    <label>Date</label>
                    <input
                        type="date"
                        name="transaction_date"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div class="finance-input-group finance-input-wide">
                    <label>Notes (Optional)</label>
                    <textarea
                        name="notes"
                        rows="3"
                        placeholder="Add some details about this transaction..."
                    ></textarea>
                </div>

            </div>

            <button type="submit" class="finance-save-btn">
                ＋ Save Transaction
            </button>
        </form>
    </section>

    <!-- Transaction History -->
    <section class="finance-history">

        <div class="finance-section-heading">
            <div>
                <span class="finance-label">YOUR MONEY ACTIVITY</span>
                <h2>Transaction History</h2>
                <p>Review all your recorded income and expenses.</p>
            </div>

            <span class="finance-record-count">
                <?= count($transactions) ?> Records
            </span>
        </div>

        <?php if (empty($transactions)): ?>

            <div class="finance-empty">
                <div>💳</div>
                <h3>No transactions yet</h3>
                <p>Add your first income or expense to start tracking your money.</p>
            </div>

        <?php else: ?>

            <div class="finance-table-wrap">
                <table class="finance-table">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= financeEscape($transaction['title']) ?>
                                    </strong>

                                    <?php if (!empty($transaction['notes'])): ?>
                                        <small class="finance-note">
                                            <?= financeEscape($transaction['notes']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td><?= financeEscape($transaction['category']) ?></td>

                                <td>
                                    <?= date(
                                        'M d, Y',
                                        strtotime($transaction['transaction_date'])
                                    ) ?>
                                </td>

                                <td>
                                    <span class="finance-type <?= strtolower($transaction['type']) ?>">
                                        <?= financeEscape($transaction['type']) ?>
                                    </span>
                                </td>

                                <td class="<?= $transaction['type'] === 'Income' ? 'income-amount' : 'expense-amount' ?>">
                                    <?= $transaction['type'] === 'Income' ? '+' : '-' ?>
                                    $<?= number_format((float)$transaction['amount'], 2) ?>
                                </td>

                                <td>
                                    <form method="POST" data-finance-form
                                          onsubmit="return confirm('Delete this transaction?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$transaction['id'] ?>">

                                        <button type="submit" class="finance-delete-btn">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

</div>


<style>.finance-page {
    color: #eaf3ff;
    padding: 10px 2px 30px;
}

.finance-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 28px;
    margin-bottom: 24px;
    border: 1px solid #2366ae;
    border-radius: 20px;
    background:
        radial-gradient(circle at top right, #1676d955, transparent 40%),
        linear-gradient(120deg, #06172f, #103b70, #08234b);
    box-shadow: 0 12px 30px #00152e66;
}

.finance-label {
    display: block;
    margin-bottom: 8px;
    color: #55c7ff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
}

.finance-header h1 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 30px;
}

.finance-header p,
.finance-section-heading p {
    margin: 0;
    color: #c4d9f7;
    font-size: 14px;
}

.finance-header-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 70px;
    height: 70px;
    border: 1px solid #55c7ff66;
    border-radius: 20px;
    background: #0b3a6c;
    font-size: 34px;
}

.finance-message {
    padding: 13px 17px;
    margin-bottom: 20px;
    border-radius: 12px;
    font-size: 14px;
}

.finance-message.success {
    color: #7cf5bd;
    border: 1px solid #278c69;
    background: #103d36;
}

.finance-message.error {
    color: #ff9caa;
    border: 1px solid #a7475a;
    background: #451e32;
}

/* Statistics */

.finance-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.finance-stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 20px 16px;
    border: 1px solid #24558e;
    border-radius: 17px;
    background: linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 8px 20px #04132b55;
}

.finance-stat-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 43px;
    height: 43px;
    border-radius: 13px;
    font-size: 23px;
    font-weight: 800;
}

.income-icon {
    color: #7cf5bd;
    background: #145b4a;
}

.expense-icon {
    color: #ff9caa;
    background: #642b43;
}

.balance-icon {
    color: #55d8ff;
    background: #124b76;
}

.total-icon {
    color: #c4b5fd;
    background: #42366f;
}

.finance-stat-card span {
    display: block;
    margin-bottom: 7px;
    color: #a9c5e9;
    font-size: 12px;
}

.finance-stat-card h2 {
    margin: 0 0 5px;
    color: #fff;
    font-size: 21px;
    overflow-wrap: anywhere;
}

.finance-stat-card small {
    color: #91afd3;
    font-size: 11px;
}

.negative-balance {
    color: #ff9caa !important;
}

/* Form */

.finance-form-card {
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid #24558e;
    border-radius: 19px;
    background: linear-gradient(145deg, #0c2850, #061832);
    box-shadow: 0 10px 25px #04132b55;
}

.finance-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 22px;
}

.finance-section-heading h2 {
    margin: 0 0 7px;
    color: #fff;
    font-size: 21px;
}

.finance-heading-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    color: #55d8ff;
    background: #124b76;
    font-size: 25px;
}

.finance-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.finance-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.finance-input-wide {
    grid-column: 1 / -1;
}

.finance-input-group label {
    color: #c4d9f7;
    font-size: 13px;
    font-weight: 700;
}

.finance-input-group input,
.finance-input-group select,
.finance-input-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #285486;
    border-radius: 10px;
    outline: none;
    background: #071b38;
    color: #eaf3ff;
    font: inherit;
    font-size: 13px;
    transition: border-color .2s, box-shadow .2s;
}

.finance-input-group input:focus,
.finance-input-group select:focus,
.finance-input-group textarea:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px #38bdf822;
}

.finance-input-group input::placeholder,
.finance-input-group textarea::placeholder {
    color: #718eaf;
}

.finance-input-group select option {
    background: #071b38;
    color: #eaf3ff;
}

.finance-input-group textarea {
    resize: vertical;
}

.finance-save-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 22px;
    margin-top: 20px;
    border: 1px solid #8de8ff;
    border-radius: 10px;
    background: linear-gradient(100deg, #38bdf8, #a5f3fc);
    color: #06224a;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 5px 15px #0874b955;
    transition: transform .2s, filter .2s;
}

.finance-save-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.08);
}

/* History */

.finance-history {
    padding: 24px;
    border: 1px solid #24558e;
    border-radius: 19px;
    background: linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 10px 25px #04132b55;
}

.finance-record-count {
    flex-shrink: 0;
    padding: 8px 12px;
    border: 1px solid #285486;
    border-radius: 20px;
    background: #071b38;
    color: #55d8ff;
    font-size: 12px;
    font-weight: 700;
}

.finance-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.finance-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 720px;
}

.finance-table th {
    padding: 14px 12px;
    border-bottom: 1px solid #285486;
    color: #55c7ff;
    font-size: 12px;
    text-align: left;
    white-space: nowrap;
}

.finance-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #24558e66;
    color: #c4d9f7;
    font-size: 13px;
}

.finance-table tbody tr:hover {
    background: #ffffff08;
}

.finance-table td strong {
    display: block;
    color: #fff;
}

.finance-note {
    display: block;
    max-width: 200px;
    margin-top: 5px;
    overflow: hidden;
    color: #91afd3;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.finance-type {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
}

.finance-type.income {
    color: #7cf5bd;
    background: #145b4a66;
}

.finance-type.expense {
    color: #ff9caa;
    background: #642b4366;
}

.income-amount {
    color: #7cf5bd !important;
    font-weight: 800;
}

.expense-amount {
    color: #ff9caa !important;
    font-weight: 800;
}

.finance-delete-btn {
    padding: 7px 11px;
    border: 1px solid #a7475a;
    border-radius: 8px;
    background: #451e32;
    color: #ffb0bb;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.finance-delete-btn:hover {
    background: #69283f;
}

.finance-empty {
    padding: 45px 15px;
    border: 1px dashed #35689d;
    border-radius: 15px;
    background: linear-gradient(145deg, #102e59, #071a38);
    text-align: center;
}

.finance-empty div {
    margin-bottom: 12px;
    font-size: 38px;
}

.finance-empty h3 {
    margin: 0 0 8px;
    color: #fff;
}

.finance-empty p {
    margin: 0;
    color: #a9c5e9;
    font-size: 13px;
}

/* Responsive */

@media (max-width: 1100px) {
    .finance-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .finance-header {
        padding: 20px;
    }

    .finance-header h1 {
        font-size: 24px;
    }

    .finance-header-icon {
        width: 52px;
        height: 52px;
        font-size: 26px;
    }

    .finance-stats {
        grid-template-columns: 1fr;
    }

    .finance-form-grid {
        grid-template-columns: 1fr;
    }

    .finance-input-wide {
        grid-column: auto;
    }

    .finance-form-card,
    .finance-history {
        padding: 17px;
    }

    .finance-section-heading {
        align-items: flex-start;
    }

    .finance-section-heading h2 {
        font-size: 18px;
    }
}</style>