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
            'EUR_to_GBP' => 0.877,     // 1 EUR = 0.877 GBP (inverse of GBP_to_EUR)
            'EUR_to_USD' => 1.1738,    // 1 EUR = 1.1738 USD
            'GBP_to_USD' => 1.28,      // 1 GBP = 1.28 USD
            'GBP_to_EUR' => 1.14,      // 1 GBP = 1.14 EUR (PRIMARY - was EUR_to_GBP)
            'USD_to_EUR' => 0.852,     // 1 USD = 0.852 EUR
            'USD_to_GBP' => 0.781      // 1 USD = 0.781 GBP
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
    // Market conversion rates (how currencies convert to each other) - NOW USING GBP_to_EUR
    $eur_to_usd = floatval($_POST['eur_to_usd']); // e.g., 1.148
    $gbp_to_usd = floatval($_POST['gbp_to_usd']); // e.g., 1.322
    $gbp_to_eur = floatval($_POST['gbp_to_eur']); // e.g., 1.14 (changed from EUR_to_GBP)
    
    // Admin selling rates (all can be manually set now)
    $admin_usd = floatval($_POST['admin_usd']);
    $admin_eur = floatval($_POST['admin_eur']);
    $admin_gbp = floatval($_POST['admin_gbp']);
    
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
    if ($eur_to_usd <= 0 || $gbp_to_usd <= 0 || $gbp_to_eur <= 0 || 
        $admin_usd <= 0 || $admin_eur <= 0 || $admin_gbp <= 0 ||
        $customer_usd <= 0 || $customer_eur <= 0 || $customer_gbp <= 0) {
        $error = 'All rates must be greater than zero!';
    } elseif ($bank_usd < 0 || $bank_eur < 0 || $bank_gbp < 0 || 
              $talkremit_usd < 0 || $talkremit_eur < 0 || $talkremit_gbp < 0) {
        $error = 'Bank and TalkRemit rates cannot be negative!';
    } else {
        // Admin selling rates (manually editable)
        $data['admin_selling_rates']['USD'] = $admin_usd;
        $data['admin_selling_rates']['EUR'] = $admin_eur;
        $data['admin_selling_rates']['GBP'] = $admin_gbp;
        
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
        
        // Store market conversion rates for calculations - NOW USING GBP_to_EUR
        $data['market_rates']['EUR_to_USD'] = $eur_to_usd;
        $data['market_rates']['GBP_to_USD'] = $gbp_to_usd;
        $data['market_rates']['GBP_to_EUR'] = $gbp_to_eur;
        $data['market_rates']['USD_to_EUR'] = 1 / $eur_to_usd;
        $data['market_rates']['USD_to_GBP'] = 1 / $gbp_to_usd;
        $data['market_rates']['EUR_to_GBP'] = 1 / $gbp_to_eur;
        
        file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
        $success = 'All rates updated successfully!';
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

        <!-- Transaction History (moved to top) -->
        <div class="admin-section">
            <div class="section-header">
                <h3>📋 Transaction History</h3>
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
                    $displayed = array_slice($reversed, 0, 10); // Show 10 most recent transactions
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
                                    Profit: +<?= number_format($transaction['best_profit'], 2) ?> MWK
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

        <!-- Profit Summary -->
        <div class="profit-summary-container">
            <div class="profit-summary total-profit">
                <h2>Total Profit (All Time)</h2>
                <div class="profit-amount">MWK <?= number_format($data['total_profit'], 2) ?></div>
                <div class="transaction-count"><?= count($data['transactions']) ?> transactions</div>
            </div>
            
            <?php
            // Calculate today's profit
            $today = date('Y-m-d');
            $today_profit = 0;
            $today_count = 0;
            foreach ($data['transactions'] as $transaction) {
                $transaction_date = date('Y-m-d', strtotime($transaction['date']));
                if ($transaction_date === $today) {
                    $today_profit += $transaction['best_profit'];
                    $today_count++;
                }
            }
            ?>
            
            <div class="profit-summary today-profit">
                <h2>Today's Profit</h2>
                <div class="profit-amount">MWK <?= number_format($today_profit, 2) ?></div>
                <div class="transaction-count"><?= $today_count ?> transactions today</div>
            </div>
        </div>

        <!-- Rate Management -->
        <div class="admin-section">
            <h3>Exchange Rate Management</h3>
            <form method="POST" class="rates-form">
                
                <!-- Customer Rates -->
                <div class="rate-section">
                    <h4>📱 Customer Rates (What customers get)</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                        <strong>Auto-calculation enabled:</strong> Change any rate and the others will auto-calculate based on market rates. You can still manually override any field.
                    </p>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 GBP = ? MWK</label>
                            <input type="number" name="customer_gbp" id="customer_gbp" step="0.01" 
                                   value="<?= $data['customer_rates']['GBP'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 USD = ? MWK</label>
                            <input type="number" name="customer_usd" id="customer_usd" step="0.01" 
                                   value="<?= $data['customer_rates']['USD'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 EUR = ? MWK</label>
                            <input type="number" name="customer_eur" id="customer_eur" step="0.01" 
                                   value="<?= $data['customer_rates']['EUR'] ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Market Conversion Rates -->
                <div class="rate-section">
                    <h4>🌍 Market Conversion Rates</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">Set the current market rates. Changing these will auto-update customer rates and other calculations.</p>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 EUR = ? USD</label>
                            <input type="number" name="eur_to_usd" id="market_eur_to_usd" step="0.00001" 
                                   value="<?= $data['market_rates']['EUR_to_USD'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 GBP = ? USD</label>
                            <input type="number" name="gbp_to_usd" id="market_gbp_to_usd" step="0.00001" 
                                   value="<?= $data['market_rates']['GBP_to_USD'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 GBP = ? EUR</label>
                            <input type="number" name="gbp_to_eur" id="market_gbp_to_eur" step="0.00001" 
                                   value="<?= $data['market_rates']['GBP_to_EUR'] ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Admin Selling Rates -->
                <div class="rate-section">
                    <h4>💰 Your Selling Rates (What you can sell for)</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                        <strong>All rates can be set manually!</strong> Initial values are auto-populated, but you have full control to adjust each rate individually.
                    </p>
                    <div class="rate-grid">
                        <div class="rate-input-group">
                            <label>1 GBP = ? MWK</label>
                            <input type="number" name="admin_gbp" id="admin_gbp" step="0.01" 
                                   value="<?= $data['admin_selling_rates']['GBP'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 USD = ? MWK</label>
                            <input type="number" name="admin_usd" id="admin_usd" step="0.01" 
                                   value="<?= $data['admin_selling_rates']['USD'] ?>" required>
                        </div>
                        <div class="rate-input-group">
                            <label>1 EUR = ? MWK</label>
                            <input type="number" name="admin_eur" id="admin_eur" step="0.01" 
                                   value="<?= $data['admin_selling_rates']['EUR'] ?>" required>
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
            <h3>💡 Profit Preview</h3>
            <p style="font-size: 0.9rem; color: #666; margin-bottom: 16px;">Calculate profit for any amount. Enter an amount in one currency to see conversions and profit breakdown.</p>
            
            <div class="profit-preview-controls">
                <div class="profit-input-group">
                    <label for="profit_gbp_amount">GBP Amount:</label>
                    <input type="number" id="profit_gbp_amount" value="0" min="0" step="0.01">
                </div>
                <div class="profit-input-group">
                    <label for="profit_usd_amount">USD Amount:</label>
                    <input type="number" id="profit_usd_amount" value="0" min="0" step="0.01">
                </div>
                <div class="profit-input-group">
                    <label for="profit_eur_amount">EUR Amount:</label>
                    <input type="number" id="profit_eur_amount" value="0" min="0" step="0.01">
                </div>
                <button onclick="updateProfitPreview()" class="calculate-btn" style="margin-top: 20px;">Calculate Profit</button>
            </div>
            
            <div id="profit-preview-results" style="margin-top: 20px;">
                <!-- Results will be dynamically inserted here -->
            </div>
        </div>

        <footer>
            <a href="index.php">← Back to Calculator</a>
        </footer>
        <?php endif; ?>
    </div>
    
    <script>
    // Auto-calculation for customer rates and selling rates
    <?php if (isset($_SESSION['admin'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const customerGBP = document.getElementById('customer_gbp');
        const customerUSD = document.getElementById('customer_usd');
        const customerEUR = document.getElementById('customer_eur');
        
        const adminGBP = document.getElementById('admin_gbp');
        const adminUSD = document.getElementById('admin_usd');
        const adminEUR = document.getElementById('admin_eur');
        
        const marketEurToUsd = document.getElementById('market_eur_to_usd');
        const marketGbpToUsd = document.getElementById('market_gbp_to_usd');
        const marketGbpToEur = document.getElementById('market_gbp_to_eur');
        
        // Get market rates from PHP (initial values)
        let marketRates = {
            EUR_to_USD: <?= $data['market_rates']['EUR_to_USD'] ?>,
            EUR_to_GBP: <?= $data['market_rates']['EUR_to_GBP'] ?>,
            GBP_to_USD: <?= $data['market_rates']['GBP_to_USD'] ?>,
            GBP_to_EUR: <?= $data['market_rates']['GBP_to_EUR'] ?>,
            USD_to_EUR: <?= $data['market_rates']['USD_to_EUR'] ?>,
            USD_to_GBP: <?= $data['market_rates']['USD_to_GBP'] ?>
        };
        
        // Update market rates object when inputs change
        function updateMarketRates() {
            const eurToUsd = parseFloat(marketEurToUsd.value);
            const gbpToUsd = parseFloat(marketGbpToUsd.value);
            const gbpToEur = parseFloat(marketGbpToEur.value);
            
            if (eurToUsd > 0 && gbpToUsd > 0 && gbpToEur > 0) {
                marketRates.EUR_to_USD = eurToUsd;
                marketRates.GBP_to_USD = gbpToUsd;
                marketRates.GBP_to_EUR = gbpToEur;
                marketRates.USD_to_EUR = 1 / eurToUsd;
                marketRates.USD_to_GBP = 1 / gbpToUsd;
                marketRates.EUR_to_GBP = 1 / gbpToEur;
            }
        }
        
        // Auto-populate admin selling rates based on USD rate (initial population)
        function autoPopulateAdminRates() {
            const usdRate = parseFloat(adminUSD.value);
            if (usdRate > 0) {
                adminEUR.value = (usdRate * marketRates.EUR_to_USD).toFixed(2);
                adminGBP.value = (usdRate * marketRates.GBP_to_USD).toFixed(2);
            }
        }
        
        // Auto-populate customer rates when market rates change
        function autoPopulateCustomerRates() {
            // Get a reference rate (we'll use USD as base)
            const usdRate = parseFloat(customerUSD.value);
            if (usdRate > 0) {
                customerEUR.value = (usdRate * marketRates.EUR_to_USD).toFixed(2);
                customerGBP.value = (usdRate * marketRates.GBP_to_USD).toFixed(2);
            }
        }
        
        // When market rates change, update derived rates and auto-populate
        marketEurToUsd.addEventListener('input', function() {
            updateMarketRates();
            autoPopulateCustomerRates();
            autoPopulateAdminRates();
        });
        
        marketGbpToUsd.addEventListener('input', function() {
            updateMarketRates();
            autoPopulateCustomerRates();
            autoPopulateAdminRates();
        });
        
        marketGbpToEur.addEventListener('input', function() {
            updateMarketRates();
            autoPopulateCustomerRates();
            autoPopulateAdminRates();
        });
        
        // Flag to prevent circular updates
        let isAutoCalculating = false;
        
        // When EUR changes, auto-calculate USD and GBP
        customerEUR.addEventListener('input', function() {
            if (isAutoCalculating) return;
            
            const eurRate = parseFloat(this.value);
            if (eurRate > 0) {
                isAutoCalculating = true;
                customerUSD.value = (eurRate / marketRates.EUR_to_USD).toFixed(2);
                customerGBP.value = (eurRate / marketRates.EUR_to_GBP).toFixed(2);
                isAutoCalculating = false;
            }
        });
        
        // When USD changes, auto-calculate EUR and GBP
        customerUSD.addEventListener('input', function() {
            if (isAutoCalculating) return;
            
            const usdRate = parseFloat(this.value);
            if (usdRate > 0) {
                isAutoCalculating = true;
                customerEUR.value = (usdRate * marketRates.EUR_to_USD).toFixed(2);
                customerGBP.value = (usdRate * marketRates.GBP_to_USD).toFixed(2);
                isAutoCalculating = false;
            }
        });
        
        // When GBP changes, auto-calculate EUR and USD
        customerGBP.addEventListener('input', function() {
            if (isAutoCalculating) return;
            
            const gbpRate = parseFloat(this.value);
            if (gbpRate > 0) {
                isAutoCalculating = true;
                customerEUR.value = (gbpRate * marketRates.GBP_to_EUR).toFixed(2);
                customerUSD.value = (gbpRate * marketRates.GBP_to_USD).toFixed(2);
                isAutoCalculating = false;
            }
        });
        
        // Auto-populate admin selling rates when USD rate changes
        adminUSD.addEventListener('input', function() {
            autoPopulateAdminRates();
        });
        
        // Profit preview - clear other fields when one is entered
        const profitGBP = document.getElementById('profit_gbp_amount');
        const profitUSD = document.getElementById('profit_usd_amount');
        const profitEUR = document.getElementById('profit_eur_amount');
        
        profitGBP.addEventListener('input', function() {
            if (this.value) {
                profitUSD.value = 0;
                profitEUR.value = 0;
            }
        });
        
        profitUSD.addEventListener('input', function() {
            if (this.value) {
                profitGBP.value = 0;
                profitEUR.value = 0;
            }
        });
        
        profitEUR.addEventListener('input', function() {
            if (this.value) {
                profitGBP.value = 0;
                profitUSD.value = 0;
            }
        });
    });
    
    // Profit Preview Calculator
    function updateProfitPreview() {
        const gbpAmount = parseFloat(document.getElementById('profit_gbp_amount').value) || 0;
        const usdAmount = parseFloat(document.getElementById('profit_usd_amount').value) || 0;
        const eurAmount = parseFloat(document.getElementById('profit_eur_amount').value) || 0;
        
        const resultsDiv = document.getElementById('profit-preview-results');
        
        // Check if at least one amount is entered
        if (gbpAmount <= 0 && usdAmount <= 0 && eurAmount <= 0) {
            resultsDiv.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Please enter an amount in at least one currency field.</p>';
            return;
        }
        
        // Determine which currency was entered (use the first non-zero one)
        let inputCurrency, inputAmount;
        if (gbpAmount > 0) {
            inputCurrency = 'GBP';
            inputAmount = gbpAmount;
        } else if (usdAmount > 0) {
            inputCurrency = 'USD';
            inputAmount = usdAmount;
        } else {
            inputCurrency = 'EUR';
            inputAmount = eurAmount;
        }
        
        const customerRates = {
            'GBP': <?= $data['customer_rates']['GBP'] ?>,
            'USD': <?= $data['customer_rates']['USD'] ?>,
            'EUR': <?= $data['customer_rates']['EUR'] ?>
        };
        
        const adminRates = {
            'GBP': <?= $data['admin_selling_rates']['GBP'] ?>,
            'USD': <?= $data['admin_selling_rates']['USD'] ?>,
            'EUR': <?= $data['admin_selling_rates']['EUR'] ?>
        };
        
        const marketRates = {
            EUR_to_USD: <?= $data['market_rates']['EUR_to_USD'] ?>,
            EUR_to_GBP: <?= $data['market_rates']['EUR_to_GBP'] ?>,
            GBP_to_USD: <?= $data['market_rates']['GBP_to_USD'] ?>,
            GBP_to_EUR: <?= $data['market_rates']['GBP_to_EUR'] ?>,
            USD_to_EUR: <?= $data['market_rates']['USD_to_EUR'] ?>,
            USD_to_GBP: <?= $data['market_rates']['USD_to_GBP'] ?>
        };
        
        // Calculate conversions to other currencies
        const conversions = {};
        conversions[inputCurrency] = inputAmount;
        
        if (inputCurrency === 'GBP') {
            conversions['USD'] = inputAmount * marketRates.GBP_to_USD;
            conversions['EUR'] = inputAmount * marketRates.GBP_to_EUR;
        } else if (inputCurrency === 'USD') {
            conversions['GBP'] = inputAmount * marketRates.USD_to_GBP;
            conversions['EUR'] = inputAmount * marketRates.USD_to_EUR;
        } else if (inputCurrency === 'EUR') {
            conversions['GBP'] = inputAmount * marketRates.EUR_to_GBP;
            conversions['USD'] = inputAmount * marketRates.EUR_to_USD;
        }
        
        // Calculate MWK value (what customer gets)
        const mwkGiven = inputAmount * customerRates[inputCurrency];
        
        // Calculate profits for each currency representation
        const profits = {};
        ['GBP', 'USD', 'EUR'].forEach(curr => {
            const currAmount = conversions[curr];
            const sellForMWK = currAmount * adminRates[curr];
            profits[curr] = sellForMWK - mwkGiven;
        });
        profits['MWK'] = mwkGiven; // Customer gets this in MWK
        
        // Build the result HTML
        let html = `
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff;">
                <h4 style="margin-top: 0; color: #007bff;">💰 Profit Analysis for ${inputAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${inputCurrency}</h4>
                
                <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <h5 style="margin-top: 0; color: #495057;">📊 Currency Conversions</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <div style="padding: 10px; background: #e7f3ff; border-radius: 4px;">
                            <strong style="color: #0056b3;">GBP:</strong> ${conversions['GBP'].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </div>
                        <div style="padding: 10px; background: #e7f3ff; border-radius: 4px;">
                            <strong style="color: #0056b3;">USD:</strong> ${conversions['USD'].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </div>
                        <div style="padding: 10px; background: #e7f3ff; border-radius: 4px;">
                            <strong style="color: #0056b3;">EUR:</strong> ${conversions['EUR'].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </div>
                        <div style="padding: 10px; background: #fff3cd; border-radius: 4px;">
                            <strong style="color: #856404;">MWK (Customer Gets):</strong> ${mwkGiven.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </div>
                    </div>
                </div>
                
                <div style="background: white; padding: 15px; border-radius: 6px;">
                    <h5 style="margin-top: 0; color: #495057;">💵 Profit Breakdown (if you sell as different currencies)</h5>
                    <div style="display: grid; gap: 10px;">
        `;
        
        // Add profit for each currency
        ['GBP', 'USD', 'EUR', 'MWK'].forEach(curr => {
            if (curr === 'MWK') {
                // MWK is what customer gets, so "profit" is 0 but show what they received
                html += `
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                        <span><strong>As MWK (Direct):</strong> Give customer MWK directly</span>
                        <span style="color: #666; font-size: 0.9em;">Customer gets: ${mwkGiven.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} MWK</span>
                    </div>
                `;
            } else {
                const profitColor = profits[curr] >= 0 ? '#28a745' : '#dc3545';
                const profitSign = profits[curr] >= 0 ? '+' : '';
                html += `
                    <div style="padding: 12px; background: ${profits[curr] >= 0 ? '#d4edda' : '#f8d7da'}; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid ${profitColor};">
                        <span><strong>Sell as ${curr}:</strong> ${conversions[curr].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${curr} × ${adminRates[curr].toLocaleString('en-US')} MWK</span>
                        <span style="color: ${profitColor}; font-weight: bold; font-size: 1.1em;">${profitSign}${profits[curr].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} MWK</span>
                    </div>
                `;
            }
        });
        
        // Find best profit option
        const bestCurr = Object.keys(profits).filter(c => c !== 'MWK').reduce((a, b) => profits[a] > profits[b] ? a : b);
        
        html += `
                    </div>
                    <div style="margin-top: 15px; padding: 12px; background: #d1ecf1; border-radius: 4px; border-left: 4px solid #17a2b8;">
                        <strong style="color: #0c5460;">💡 Best Strategy:</strong> Sell as ${bestCurr} for maximum profit of <strong>${profits[bestCurr].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} MWK</strong>
                    </div>
                </div>
            </div>
        `;
        
        resultsDiv.innerHTML = html;
    }
    <?php endif; ?>
    </script>
</body>
</html>