<?php
session_start();

// Simple admin authentication
if ($_POST['login'] ?? false) {
    if ($_POST['password'] === 'admin123') {
        $_SESSION['admin'] = true;
    } else {
        $error = 'Invalid password';
    }
}

if ($_POST['logout'] ?? false) {
    unset($_SESSION['admin']);
}

// Load data
$dataFile = 'data.json';
$data = json_decode(file_get_contents($dataFile), true);

// Handle rate updates
if (($_SESSION['admin'] ?? false) && ($_POST['update_rates'] ?? false)) {
    $data['rates']['GBP'] = floatval($_POST['gbp_rate']);
    $data['rates']['USD'] = floatval($_POST['usd_rate']);
    $data['rates']['EUR'] = floatval($_POST['eur_rate']);
    
    // Update real rates
    $data['real_rates']['GBP']['rate'] = floatval($_POST['gbp_real_rate']);
    $data['real_rates']['USD']['rate'] = floatval($_POST['usd_real_rate']);
    $data['real_rates']['EUR']['rate'] = floatval($_POST['eur_real_rate']);
    $data['usd_to_mwk'] = floatval($_POST['usd_to_mwk']);
    
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    $success = 'Rates updated successfully!';
}

// Clear transactions
if (($_SESSION['admin'] ?? false) && ($_POST['clear_transactions'] ?? false)) {
    $data['transactions'] = [];
    $data['total_profit'] = 0;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    $success = 'Transaction history cleared!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Forex Calculator</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php if (!($_SESSION['admin'] ?? false)): ?>
        <!-- Login Form -->
        <div class="admin-login">
            <h1>🔐 Admin Login</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login" class="convert-btn">Login</button>
            </form>
            <p><a href="index.php">← Back to Calculator</a></p>
        </div>
        <?php else: ?>
        
        <!-- Admin Dashboard -->
        <header>
            <h1>🔧 Admin Dashboard</h1>
            <form method="POST" style="display: inline;">
                <button type="submit" name="logout" class="logout-btn">Logout</button>
            </form>
        </header>

        <?php if (isset($success)): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Profit Summary -->
        <div class="profit-summary">
            <h2>💰 Profit Summary</h2>
            <div class="profit-amount">
                Total Profit: <strong>MWK <?= number_format($data['total_profit'], 2) ?></strong>
            </div>
            <p>Total Transactions: <?= count($data['transactions']) ?></p>
        </div>

        <!-- Rate Management -->
        <div class="admin-section">
            <h3>📊 Manage Exchange Rates</h3>
            <form method="POST" class="rates-form">
                <div class="rates-grid-admin">
                    <div class="rate-group">
                        <h4>British Pound (GBP)</h4>
                        <label>Customer Rate (1 GBP = ? MWK)</label>
                        <input type="number" name="gbp_rate" step="0.01" 
                               value="<?= $data['rates']['GBP'] ?>" required>
                        <label>Real Rate (1 GBP = ? USD)</label>
                        <input type="number" name="gbp_real_rate" step="0.00001" 
                               value="<?= $data['real_rates']['GBP']['rate'] ?>" required>
                    </div>

                    <div class="rate-group">
                        <h4>US Dollar (USD)</h4>
                        <label>Customer Rate (1 USD = ? MWK)</label>
                        <input type="number" name="usd_rate" step="0.01" 
                               value="<?= $data['rates']['USD'] ?>" required>
                        <label>Real Rate (Always 1)</label>
                        <input type="number" name="usd_real_rate" step="0.00001" 
                               value="1" readonly>
                    </div>

                    <div class="rate-group">
                        <h4>Euro (EUR)</h4>
                        <label>Customer Rate (1 EUR = ? MWK)</label>
                        <input type="number" name="eur_rate" step="0.01" 
                               value="<?= $data['rates']['EUR'] ?>" required>
                        <label>Real Rate (1 EUR = ? USD)</label>
                        <input type="number" name="eur_real_rate" step="0.00001" 
                               value="<?= $data['real_rates']['EUR']['rate'] ?>" required>
                    </div>

                    <div class="rate-group">
                        <h4>USD to MWK</h4>
                        <label>Real Market Rate (1 USD = ? MWK)</label>
                        <input type="number" name="usd_to_mwk" step="0.01" 
                               value="<?= $data['usd_to_mwk'] ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_rates" class="convert-btn">Update Rates</button>
            </form>
        </div>

        <!-- Transaction History -->
        <div class="admin-section">
            <h3>📋 Recent Transactions</h3>
            <?php if (empty($data['transactions'])): ?>
                <p>No transactions yet.</p>
            <?php else: ?>
                <div class="transaction-controls">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="clear_transactions" 
                                class="danger-btn" 
                                onclick="return confirm('Are you sure you want to clear all transactions?')">
                            Clear All Transactions
                        </button>
                    </form>
                </div>
                <div class="transactions-list">
                    <?php foreach (array_reverse(array_slice($data['transactions'], -10)) as $transaction): ?>
                    <div class="transaction-item">
                        <div class="transaction-header">
                            <span class="transaction-date"><?= $transaction['date'] ?></span>
                            <span class="transaction-profit profit-positive">
                                +MWK <?= number_format($transaction['profit'], 2) ?>
                            </span>
                        </div>
                        <div class="transaction-details">
                            <p><strong><?= $transaction['currency'] ?> <?= number_format($transaction['amount'], 2) ?></strong> 
                               → MWK <?= number_format($transaction['mwk_given'], 2) ?></p>
                            <p class="small">Real Value: MWK <?= number_format($transaction['real_mwk_value'], 2) ?> 
                               | Rate Used: <?= number_format($transaction['rate_charged']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <footer>
            <a href="index.php">← Back to Calculator</a>
        </footer>
        <?php endif; ?>
    </div>
</body>
</html>
