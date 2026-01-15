<?php
session_start();

// Handle login
if (isset($_POST['login'])) {
    $password = trim($_POST['password']);
    if ($password === 'admin123') {
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Invalid password';
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Load data
$dataFile = 'data.json';
if (!file_exists($dataFile)) {
    $defaultData = [
        'customer_rates' => [
            'GBP' => 5200,
            'USD' => 4000,
            'EUR' => 4200
        ],
        'market_rates' => [
            'EUR_to_GBP' => 1.14,
            'EUR_to_USD' => 1.1738,
            'GBP_to_USD' => 1.28,
            'GBP_to_EUR' => 0.877,
            'USD_to_EUR' => 0.852,
            'USD_to_GBP' => 0.781
        ],
        'admin_selling_rates' => [
            'GBP' => 5500,
            'USD' => 4300,
            'EUR' => 4600
        ],
        'bank_rates' => [
            'GBP' => 0,
            'USD' => 0,
            'EUR' => 0
        ],
        'talkremit_rates' => [
            'GBP' => 0,
            'USD' => 0,
            'EUR' => 0
        ],
        'transactions' => [],
        'total_profit' => 0
    ];
    file_put_contents($dataFile, json_encode($defaultData, JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents($dataFile), true);

// Ensure backwards compatibility - add new rate fields if they don't exist
if (!isset($data['bank_rates'])) {
    $data['bank_rates'] = ['GBP' => 0, 'USD' => 0, 'EUR' => 0];
}
if (!isset($data['talkremit_rates'])) {
    $data['talkremit_rates'] = ['GBP' => 0, 'USD' => 0, 'EUR' => 0];
}

// Handle rate updates
if (isset($_SESSION['admin']) && isset($_POST['update_rates'])) {
    // Get base USD selling rate (what admin can sell USD for)
    $usd_selling_rate = floatval($_POST['admin_usd']);
    
    // Market conversion rates (how currencies convert to each other)
    $eur_to_usd = floatval($_POST['eur_to_usd']); // e.g., 1.148
    $gbp_to_usd = floatval($_POST['gbp_to_usd']); // e.g., 1.322
    
    // Customer rates
    $customer_usd = floatval($_POST['customer_usd']);
    $customer_eur = floatval($_POST['customer_eur']);
    $customer_gbp = floatval($_POST['customer_gbp']);
    
    // Bank rates (optional - 0 means not set)
    $bank_usd = floatval($_POST['bank_usd'] ?? 0);
    $bank_eur = floatval($_POST['bank_eur'] ?? 0);
    $bank_gbp = floatval($_POST['bank_gbp'] ?? 0);
    
    // TalkRemit rates (optional - 0 means not set)
    $talkremit_usd = floatval($_POST['talkremit_usd'] ?? 0);
    $talkremit_eur = floatval($_POST['talkremit_eur'] ?? 0);
    $talkremit_gbp = floatval($_POST['talkremit_gbp'] ?? 0);
    
    // Validate that all rates are positive to prevent division by zero and invalid configurations
    if ($eur_to_usd <= 0 || $gbp_to_usd <= 0 || $usd_selling_rate <= 0 || 
        $customer_usd <= 0 || $customer_eur <= 0 || $customer_gbp <= 0) {
        $error = 'All rates must be greater than zero!';
    } else {
        // Auto-calculate other selling rates based on USD rate
        $data['admin_selling_rates']['USD'] = $usd_selling_rate;
        $data['admin_selling_rates']['EUR'] = $usd_selling_rate * $eur_to_usd; // e.g., 4000 * 1.148 = 4592
        $data['admin_selling_rates']['GBP'] = $usd_selling_rate * $gbp_to_usd; // e.g., 4000 * 1.322 = 5288
        
        // Customer rates (what customers get - should be lower than selling rates)
        $data['customer_rates']['USD'] = $customer_usd;
        $data['customer_rates']['EUR'] = $customer_eur;
        $data['customer_rates']['GBP'] = $customer_gbp;
        
        // Bank rates (optional)
        $data['bank_rates']['USD'] = $bank_usd;
        $data['bank_rates']['EUR'] = $bank_eur;
        $data['bank_rates']['GBP'] = $bank_gbp;
        
        // TalkRemit rates (optional)
        $data['talkremit_rates']['USD'] = $talkremit_usd;
        $data['talkremit_rates']['EUR'] = $talkremit_eur;
        $data['talkremit_rates']['GBP'] = $talkremit_gbp;
        
        // Store market conversion rates for calculations
        $data['market_rates']['EUR_to_USD'] = $eur_to_usd;
        $data['market_rates']['GBP_to_USD'] = $gbp_to_usd;
        $data['market_rates']['USD_to_EUR'] = 1 / $eur_to_usd;
        $data['market_rates']['USD_to_GBP'] = 1 / $gbp_to_usd;
        $data['market_rates']['EUR_to_GBP'] = $eur_to_usd / $gbp_to_usd;
        $data['market_rates']['GBP_to_EUR'] = $gbp_to_usd / $eur_to_usd;
        
        file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
        $success = 'All rates updated successfully! EUR and GBP selling rates auto-calculated based on USD rate.';
    }
}

// Clear transactions
if (isset($_SESSION['admin']) && isset($_POST['clear_transactions'])) {
    $data['transactions'] = [];
    $data['total_profit'] = 0;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    $success = 'Transaction history cleared!';
}

// Delete individual transaction
if (isset($_SESSION['admin']) && isset($_POST['delete_transaction'])) {
    $transaction_index = intval($_POST['transaction_index']);
    if (isset($data['transactions'][$transaction_index])) {
        // Subtract the profit from total
        $data['total_profit'] -= $data['transactions'][$transaction_index]['best_profit'];
        // Remove the transaction
        array_splice($data['transactions'], $transaction_index, 1);
        file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
        $success = 'Transaction deleted successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['admin'])): ?>
        <!-- Login Form -->
        <div class="admin-login">
            <h1>Admin Login</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter admin password" required>
                </div>
                <button type="submit" name="login" class="convert-btn">LOGIN</button>
            </form>
            <div class="back-link">
                <a href="index.php">← Back to Calculator</a>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Admin Dashboard -->
        <header>
            <h1>Admin Dashboard</h1>
            <form method="POST" class="logout-form">
                <button type="submit" name="logout" class="logout-btn">Logout</button>
            </form>
        </header>

        <?php if (isset($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Profit Summary -->
        <div class="profit-summary">
            <h2>Total Profit</h2>
            <div class="profit-amount">MWK <?= number_format($data['total_profit'], 2) ?></div>
            <div class="transaction-count"><?= count($data['transactions']) ?> transactions</div>
        </div>

        <!-- Rate Management -->
        <div class="admin-section">
            <h3>Exchange Rate Management</h3>
            <form method="POST" class="rates-form">
                
                <!-- Customer Rates -->
                <div class="rate-section">
                    <h4>📱 Customer Rates (What customers get)</h4>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 GBP = ? MWK</label>
                            <input type="number" name="customer_gbp" step="0.01" 
                                   value="<?= $data['customer_rates']['GBP'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 USD = ? MWK</label>
                            <input type="number" name="customer_usd" step="0.01" 
                                   value="<?= $data['customer_rates']['USD'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 EUR = ? MWK</label>
                            <input type="number" name="customer_eur" step="0.01" 
                                   value="<?= $data['customer_rates']['EUR'] ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Market Conversion Rates -->
                <div class="rate-section">
                    <h4>🌍 Market Conversion Rates</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">Set the current market rates for automatic selling rate calculation</p>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 EUR = ? USD</label>
                            <input type="number" name="eur_to_usd" step="0.00001" 
                                   value="<?= $data['market_rates']['EUR_to_USD'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 GBP = ? USD</label>
                            <input type="number" name="gbp_to_usd" step="0.00001" 
                                   value="<?= $data['market_rates']['GBP_to_USD'] ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Admin Selling Rates -->
                <div class="rate-section">
                    <h4>💰 Your Selling Rates (What you can sell for)</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                        <strong>Set only USD rate - EUR and GBP will be auto-calculated!</strong><br>
                        Example: If 1 USD = 4000 MWK and market rate is 1 EUR = 1.148 USD, then 1 EUR = 4592 MWK
                    </p>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 USD = ? MWK (your rate)</label>
                            <input type="number" name="admin_usd" step="0.01" 
                                   value="<?= $data['admin_selling_rates']['USD'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 EUR = ? MWK (auto-calculated)</label>
                            <input type="number" step="0.01" 
                                   value="<?= $data['admin_selling_rates']['EUR'] ?>" readonly 
                                   style="background: #f0f0f0; cursor: not-allowed;">
                            <small style="color: #666; font-size: 0.75rem;">Auto: USD rate × EUR/USD market rate</small>
                        </div>
                        <div class="rate-input-group">
                            <label>1 GBP = ? MWK (auto-calculated)</label>
                            <input type="number" step="0.01" 
                                   value="<?= $data['admin_selling_rates']['GBP'] ?>" readonly 
                                   style="background: #f0f0f0; cursor: not-allowed;">
                            <small style="color: #666; font-size: 0.75rem;">Auto: USD rate × GBP/USD market rate</small>
                        </div>
                    </div>
                </div>

                <!-- Bank Rates (Optional) -->
                <div class="rate-section">
                    <h4>🏦 Bank Rates (Optional - for comparison)</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">Set to 0 to hide from customer view. When set, customers can compare your rates with bank rates.</p>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 GBP = ? MWK (Bank rate)</label>
                            <input type="number" name="bank_gbp" step="0.01" 
                                   value="<?= $data['bank_rates']['GBP'] ?? 0 ?>" min="0">
                        </div>
                        <div class="rate-input-group">
                            <label>1 USD = ? MWK (Bank rate)</label>
                            <input type="number" name="bank_usd" step="0.01" 
                                   value="<?= $data['bank_rates']['USD'] ?? 0 ?>" min="0">
                        </div>
                        <div class="rate-input-group">
                            <label>1 EUR = ? MWK (Bank rate)</label>
                            <input type="number" name="bank_eur" step="0.01" 
                                   value="<?= $data['bank_rates']['EUR'] ?? 0 ?>" min="0">
                        </div>
                    </div>
                </div>

                <!-- TalkRemit Rates (Optional) -->
                <div class="rate-section">
                    <h4>📲 TalkRemit Rates (Optional - for comparison)</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">Set to 0 to hide from customer view. When set, customers can compare your rates with TalkRemit app rates.</p>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 GBP = ? MWK (TalkRemit rate)</label>
                            <input type="number" name="talkremit_gbp" step="0.01" 
                                   value="<?= $data['talkremit_rates']['GBP'] ?? 0 ?>" min="0">
                        </div>
                        <div class="rate-input-group">
                            <label>1 USD = ? MWK (TalkRemit rate)</label>
                            <input type="number" name="talkremit_usd" step="0.01" 
                                   value="<?= $data['talkremit_rates']['USD'] ?? 0 ?>" min="0">
                        </div>
                        <div class="rate-input-group">
                            <label>1 EUR = ? MWK (TalkRemit rate)</label>
                            <input type="number" name="talkremit_eur" step="0.01" 
                                   value="<?= $data['talkremit_rates']['EUR'] ?? 0 ?>" min="0">
                        </div>
                    </div>
                </div>

                <button type="submit" name="update_rates" class="convert-btn">Update All Rates</button>
            </form>
        </div>

        <!-- Profit Preview -->
        <div class="admin-section">
            <h3>💡 Profit Preview (per 1000 units)</h3>
            <div class="profit-preview-grid">
                <?php foreach (['GBP', 'USD', 'EUR'] as $currency): ?>
                <div class="profit-preview-card">
                    <h4>1000 <?= $currency ?></h4>
                    <div class="preview-details">
                        <?php
                        $amount = 1000;
                        $customer_gets = $amount * $data['customer_rates'][$currency];
                        
                        // Direct sale profit
                        $direct_profit = ($amount * $data['admin_selling_rates'][$currency]) - $customer_gets;
                        
                        echo "<div class='profit-option'>";
                        echo "<span>Direct sale:</span>";
                        echo "<span class='profit-amount'>+" . number_format($direct_profit, 0) . " MWK</span>";
                        echo "</div>";
                        
                        // Conversion profits
                        foreach ($data['market_rates'] as $rate_key => $rate_value) {
                            $from_curr = substr($rate_key, 0, 3);
                            $to_curr = substr($rate_key, -3);
                            
                            if ($from_curr === $currency) {
                                $converted_amount = $amount * $rate_value;
                                $conversion_profit = ($converted_amount * $data['admin_selling_rates'][$to_curr]) - $customer_gets;
                                
                                echo "<div class='profit-option'>";
                                echo "<span>Via $to_curr:</span>";
                                echo "<span class='profit-amount'>+" . number_format($conversion_profit, 0) . " MWK</span>";
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Transaction History -->
        <div class="admin-section">
            <div class="section-header">
                <h3>📋 Recent Transactions</h3>
                <?php if (!empty($data['transactions'])): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_transactions" 
                            class="danger-btn" 
                            onclick="return confirm('Clear all transactions?')">
                        Clear All
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if (empty($data['transactions'])): ?>
                <div class="no-transactions">No transactions yet.</div>
            <?php else: ?>
                <div class="transactions-list">
                    <?php 
                    $transactions = $data['transactions'];
                    $reversed = array_reverse($transactions);
                    $displayed = array_slice($reversed, 0, 10); // Show last 10 transactions
                    foreach ($displayed as $idx => $transaction): 
                        // Calculate original index for deletion
                        $original_index = count($transactions) - 1 - $idx;
                    ?>
                    <div class="transaction-item">
                        <div class="transaction-header">
                            <div class="transaction-amount">
                                <?php if (!empty($transaction['name'])): ?>
                                    <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">
                                        <?= htmlspecialchars($transaction['name']) ?>
                                    </div>
                                <?php endif; ?>
                                <?= $transaction['currency'] ?> <?= number_format($transaction['amount'], 2) ?> 
                                → MWK <?= number_format($transaction['mwk_given_to_customer'], 2) ?>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <div class="transaction-profit positive">
                                    +<?= number_format($transaction['best_profit'], 2) ?> MWK
                                </div>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="transaction_index" value="<?= $original_index ?>">
                                    <button type="submit" name="delete_transaction" 
                                            class="delete-transaction-btn" 
                                            onclick="return confirm('Delete this transaction?')"
                                            title="Delete transaction">✕</button>
                                </form>
                            </div>
                        </div>
                        <div class="transaction-details">
                            <div class="transaction-date"><?= $transaction['date'] ?></div>
                            <div class="transaction-strategy">Best: <?= ucfirst(str_replace('_', ' ', $transaction['best_strategy'])) ?></div>
                        </div>
                        
                        <!-- Show all profit options -->
                        <div class="profit-breakdown">
                            <?php foreach ($transaction['all_options'] as $option_key => $option): ?>
                            <div class="profit-option <?= $option_key === $transaction['best_strategy'] ? 'best-option' : '' ?>">
                                <span class="option-desc"><?= $option['description'] ?></span>
                                <span class="option-profit">+<?= number_format($option['profit'], 2) ?> MWK</span>
                            </div>
                            <?php endforeach; ?>
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