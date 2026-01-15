<?php
// Load data
$dataFile = 'data.json';
if (!file_exists($dataFile)) {
    $defaultData = [
        'customer_rates' => [
            // What customers get when selling foreign currency for MWK
            'GBP' => 5200,  // Customer: 1 GBP = 5200 MWK
            'USD' => 4000,  // Customer: 1 USD = 4000 MWK  
            'EUR' => 4200   // Customer: 1 EUR = 4200 MWK
        ],
        'market_rates' => [
            // Real market rates between foreign currencies
            'EUR_to_GBP' => 1.14,      // 1 EUR = 1.14 GBP
            'EUR_to_USD' => 1.1738,    // 1 EUR = 1.1738 USD
            'GBP_to_USD' => 1.28,      // 1 GBP = 1.28 USD
            'GBP_to_EUR' => 0.877,     // 1 GBP = 0.877 EUR
            'USD_to_EUR' => 0.852,     // 1 USD = 0.852 EUR
            'USD_to_GBP' => 0.781      // 1 USD = 0.781 GBP
        ],
        'admin_selling_rates' => [
            // What admin can actually sell each currency for (in MWK)
            'GBP' => 5500,  // Admin can sell 1 GBP for 5500 MWK
            'USD' => 4300,  // Admin can sell 1 USD for 4300 MWK
            'EUR' => 4600   // Admin can sell 1 EUR for 4600 MWK
        ],
        'bank_rates' => [
            // Optional: Bank rates for comparison (what bank gives for foreign currency)
            'GBP' => 0,
            'USD' => 0,
            'EUR' => 0
        ],
        'talkremit_rates' => [
            // Optional: TalkRemit app rates for comparison
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

// Handle conversion
$result = null;
$profit_breakdown = null;

if (isset($_POST['calculate']) || isset($_POST['save'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = $_POST['currency'];
    $direction = $_POST['direction'] ?? 'foreign_to_mwk'; // Default: foreign currency to MWK
    $transaction_name = trim($_POST['transaction_name'] ?? '');
    
    if ($amount > 0) {
        if ($direction === 'foreign_to_mwk') {
            // Foreign currency to MWK (original calculation)
            $customer_rate = $data['customer_rates'][$currency];
            $mwk_given = $amount * $customer_rate;
            
            // Calculate profit options by converting to other currencies
            $profit_options = [];
            
            // Option 1: Direct sale of the same currency
            $direct_mwk = $amount * $data['admin_selling_rates'][$currency];
            $direct_profit = $direct_mwk - $mwk_given;
            $profit_options['direct_' . $currency] = [
                'description' => "Sell $amount $currency directly",
                'amount_received' => $direct_mwk,
                'profit' => $direct_profit
            ];
            
            // Option 2 & 3: Convert to other currencies first, then sell
            foreach ($data['market_rates'] as $rate_key => $rate_value) {
                $from_curr = substr($rate_key, 0, 3);
                $to_curr = substr($rate_key, -3);
                
                if ($from_curr === $currency) {
                    $converted_amount = $amount * $rate_value;
                    $converted_mwk = $converted_amount * $data['admin_selling_rates'][$to_curr];
                    $converted_profit = $converted_mwk - $mwk_given;
                    
                    $profit_options['convert_to_' . $to_curr] = [
                        'description' => "Convert $amount $currency to $converted_amount $to_curr, then sell",
                        'amount_received' => $converted_mwk,
                        'profit' => $converted_profit,
                        'conversion_rate' => $rate_value,
                        'converted_amount' => $converted_amount,
                        'target_currency' => $to_curr
                    ];
                }
            }
            
            // Find best profit option
            $best_option = max($profit_options);
            $best_profit = $best_option['profit'];
            
            // Only save transaction if 'save' button was clicked
            if (isset($_POST['save'])) {
                $transaction = [
                    'date' => date('Y-m-d H:i:s'),
                    'name' => $transaction_name,
                    'amount' => $amount,
                    'currency' => $currency,
                    'customer_rate' => $customer_rate,
                    'mwk_given_to_customer' => $mwk_given,
                    'best_strategy' => array_search($best_option, $profit_options),
                    'best_profit' => $best_profit,
                    'all_options' => $profit_options
                ];
                
                $data['transactions'][] = $transaction;
                $data['total_profit'] += $best_profit;
                file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
            }
            
            $result = [
                'amount' => $amount,
                'currency' => $currency,
                'mwk_amount' => number_format($mwk_given, 2),
                'rate' => $customer_rate,
                'direction' => 'foreign_to_mwk'
            ];
            
            $profit_breakdown = $profit_options;
        } else {
            // MWK to Foreign currency (reverse calculation)
            $customer_rate = $data['customer_rates'][$currency];
            $foreign_amount = $amount / $customer_rate;
            
            $result = [
                'mwk_amount' => number_format($amount, 2),
                'currency' => $currency,
                'amount' => $foreign_amount,
                'rate' => $customer_rate,
                'direction' => 'mwk_to_foreign'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forex Calculator</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Forex Calculator</h1>
            <p>Convert foreign currency to Malawi Kwacha</p>
        </header>

        <div class="calculator-card">
            <form method="POST" class="calculator-form">
                <div class="form-group">
                    <label for="transaction_name">Transaction Name (Optional)</label>
                    <input type="text" 
                           id="transaction_name" 
                           name="transaction_name" 
                           placeholder="e.g., John Doe, Payment for services" 
                           value="<?= htmlspecialchars($_POST['transaction_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="direction">Calculation Direction</label>
                    <select id="direction" name="direction" onchange="updateLabels()" required>
                        <option value="foreign_to_mwk" <?= (($_POST['direction'] ?? 'foreign_to_mwk') === 'foreign_to_mwk') ? 'selected' : '' ?>>
                            Foreign Currency → MWK
                        </option>
                        <option value="mwk_to_foreign" <?= (($_POST['direction'] ?? 'foreign_to_mwk') === 'mwk_to_foreign') ? 'selected' : '' ?>>
                            MWK → Foreign Currency
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount" id="amount-label">Amount</label>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           step="0.01" 
                           min="0" 
                           placeholder="0.00" 
                           value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select id="currency" name="currency" required>
                        <option value="GBP" <?= (($_POST['currency'] ?? '') === 'GBP') ? 'selected' : '' ?>>
                            British Pound (£)
                        </option>
                        <option value="USD" <?= (($_POST['currency'] ?? '') === 'USD') ? 'selected' : '' ?>>
                            US Dollar ($)
                        </option>
                        <option value="EUR" <?= (($_POST['currency'] ?? '') === 'EUR') ? 'selected' : '' ?>>
                            Euro (€)
                        </option>
                    </select>
                </div>

                <div class="button-group">
                    <button type="submit" name="calculate" class="calculate-btn">CALCULATE</button>
                    <button type="submit" name="save" class="save-btn">SAVE / RECORD</button>
                </div>
            </form>

            <?php if ($result): ?>
            <div class="result-card">
                <div class="result-row">
                    <?php if ($result['direction'] === 'foreign_to_mwk'): ?>
                        <span class="input-amount"><?= $result['currency'] ?> <?= number_format($result['amount'], 2) ?></span>
                        <span class="equals">=</span>
                        <span class="output-amount">MWK <?= $result['mwk_amount'] ?></span>
                    <?php else: ?>
                        <span class="input-amount">MWK <?= $result['mwk_amount'] ?></span>
                        <span class="equals">=</span>
                        <span class="output-amount"><?= $result['currency'] ?> <?= number_format($result['amount'], 2) ?></span>
                    <?php endif; ?>
                </div>
                <div class="rate-info">Rate: 1 <?= $result['currency'] ?> = <?= number_format($result['rate']) ?> MWK</div>
                
                <?php 
                // Show rate comparison if direction is foreign_to_mwk and comparison rates are set
                if ($result['direction'] === 'foreign_to_mwk'):
                    $currency = $result['currency'];
                    $amount = $result['amount'];
                    $bank_rate = max(0, floatval($data['bank_rates'][$currency] ?? 0));
                    $talkremit_rate = max(0, floatval($data['talkremit_rates'][$currency] ?? 0));
                    $show_comparison = ($bank_rate > 0 || $talkremit_rate > 0);
                    
                    if ($show_comparison):
                ?>
                <div class="rate-comparison">
                    <h4>Rate Comparison</h4>
                    <p style="font-size: 0.85rem; margin-bottom: 12px;">See how much you would get with different services:</p>
                    <div class="comparison-list">
                        <div class="comparison-item our-rate">
                            <span class="service-name">Our Rate</span>
                            <span class="comparison-amount">MWK <?= number_format($amount * $result['rate'], 2) ?></span>
                        </div>
                        <?php if ($bank_rate > 0): ?>
                        <div class="comparison-item">
                            <span class="service-name">Bank Rate</span>
                            <span class="comparison-amount">MWK <?= number_format($amount * $bank_rate, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($talkremit_rate > 0): ?>
                        <div class="comparison-item">
                            <span class="service-name">TalkRemit</span>
                            <span class="comparison-amount">MWK <?= number_format($amount * $talkremit_rate, 2) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                    endif;
                endif; 
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        function updateLabels() {
            const direction = document.getElementById('direction').value;
            const amountLabel = document.getElementById('amount-label');
            
            if (direction === 'foreign_to_mwk') {
                amountLabel.textContent = 'Foreign Currency Amount';
            } else {
                amountLabel.textContent = 'MWK Amount';
            }
        }
        
        // Update labels on page load
        document.addEventListener('DOMContentLoaded', updateLabels);
        </script>

        <div class="rates-display">
            <h3>Current Exchange Rates</h3>
            <div class="rates-list">
                <?php foreach ($data['customer_rates'] as $curr => $rate): ?>
                <div class="rate-row">
                    <span class="currency"><?= $curr ?></span>
                    <span class="rate">1 = <?= number_format($rate) ?> MWK</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <footer>
            <a href="admin.php" class="admin-link">Admin</a>
        </footer>
    </div>
</body>
</html>