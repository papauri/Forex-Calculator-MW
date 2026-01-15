# Forex Calculator MW

A comprehensive foreign exchange calculator for converting currencies to Malawi Kwacha (MWK) with profit tracking and transaction management.

## Features

### Customer-Facing Calculator (index.php)
- **Dual Calculation Modes**:
  - Foreign Currency → MWK
  - MWK → Foreign Currency
- **Separate Action Buttons**:
  - **CALCULATE**: Preview conversion results without saving
  - **SAVE / RECORD**: Calculate and save transaction to history
- **Supported Currencies**:
  - British Pound (GBP)
  - US Dollar (USD)
  - Euro (EUR)
- **Rate Comparison**: Compare exchange rates with bank and TalkRemit rates
- **Transaction Naming**: Optional transaction name/description for record keeping

### Admin Dashboard (admin.php)
- **Transaction History** (displayed at the top):
  - View all recent transactions with customer names
  - See profit breakdown for each transaction
  - View all profit calculation options (direct sale vs currency conversion)
  - Delete individual transactions
  - Clear all transaction history
  
- **Profit Analytics**:
  - **Total Profit (All Time)**: Complete profit from all transactions
  - **Today's Profit**: Profit from today's transactions only
  - Transaction count for both metrics

- **Exchange Rate Management**:
  - Customer rates (what customers receive)
  - Market conversion rates (for cross-currency calculations)
  - Admin selling rates (auto-calculated based on USD rate)
  - Bank rates (optional, for customer comparison)
  - TalkRemit rates (optional, for customer comparison)

- **Profit Preview**: See projected profit per 1000 units for each currency

## Installation

1. Clone this repository:
   ```bash
   git clone https://github.com/papauri/Forex-Calculator-MW.git
   cd Forex-Calculator-MW
   ```

2. Ensure PHP is installed (PHP 7.0 or higher recommended)

3. Start a local PHP server:
   ```bash
   php -S localhost:8000
   ```

4. Access the application:
   - Customer Calculator: http://localhost:8000/index.php
   - Admin Dashboard: http://localhost:8000/admin.php

## Admin Access

- **Default Password**: `admin123`
- Password can be changed in `admin.php` (line 7)

## How It Works

### Customer Flow
1. Enter transaction details (optional name, amount, currency)
2. Choose calculation direction
3. Click **CALCULATE** to preview results OR **SAVE / RECORD** to save transaction
4. View exchange rate and comparison with other services

### Admin Flow
1. Log in to admin dashboard
2. View transaction history at the top with profit details
3. Monitor total profit and today's profit
4. Update exchange rates as needed
5. Use profit preview to plan pricing strategy

### Profit Calculation
The system automatically calculates the best profit strategy for each transaction by:
1. **Direct Sale**: Selling the foreign currency directly for MWK
2. **Currency Conversion**: Converting to another currency first, then selling
3. Comparing all options and selecting the most profitable strategy

## Data Storage

All data is stored in `data.json` including:
- Exchange rates (customer, market, admin selling, bank, TalkRemit)
- Transaction history
- Total profit tracking

## Technologies Used

- **PHP**: Server-side logic and data handling
- **HTML/CSS**: User interface
- **JSON**: Data persistence

## Security Notes

- Change the default admin password before deploying to production
- Consider implementing proper authentication and session management for production use
- The current implementation is suitable for local/development use

## License

This project is open source and available for use and modification.