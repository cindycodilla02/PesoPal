# Student Expense and Allowance Management System

This PHP/MySQL project follows the features and entities in `Codilla_Prelim.docx`: student accounts, allowance records, categorized expenses, budgets, financial reports, and activity history.

## Run with XAMPP and phpMyAdmin

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Choose **Import**, select `sql/database.sql` from this project, and choose **Go**. The script creates the `student_expense_db` database, its tables, and the starter expense categories. It does not insert a shared sample user/password.
4. Open `http://localhost/Codilla_Final/register.php` and create your own account. The password is stored using PHP's password hashing.
5. Sign in, record an allowance, then add and categorize expenses. Dashboard balances are calculated from that account's own allowance and expense records.

By default, `includes/connection.php` connects to MySQL at `127.0.0.1:3306` with database `student_expense_db`, user `root`, and an empty password (the usual local XAMPP defaults). For another database, configure the `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables before serving the site. Keep real credentials out of source control; use your hosting provider's environment-variable settings for deployment.

## Features

- Session-based private registration, login/logout, profile editing, and password changes
- Add, view, edit, and delete expenses and daily/weekly/monthly allowance records
- Expense categories and transaction search/date/category filters
- Allowance, expense, balance, and category dashboard summaries
- Dated budgets with spending progress
- Weekly and monthly reports, saved report snapshots, CSV export (opens in Excel), and browser print / **Save as PDF**
- Account activity log

The report's **Print / Save PDF** button uses the browser print dialog; choose **Save as PDF** there. CSV is used for the spreadsheet export so no external PHP library is needed.

## Database notes

All financial queries are scoped to the signed-in user. Keep the database and PHP server on a trusted development machine while using default XAMPP credentials. Production deployment requires a dedicated MySQL user/password, HTTPS, and deployment-specific session and database configuration.

PHP sessions keep each student signed in across pages; unauthenticated users are redirected to sign in. Each account can create and manage multiple transactions (at least three or more). No fake financial transactions are inserted automatically, so dashboard balances reflect records the student actually enters.

Account creation and financial transaction saves/deletes use PDO database transactions so their corresponding activity-log entries are committed together or rolled back on failure.

If the database was imported from the earlier project version, register a new account or sign in to the existing account. A legacy plain-text password is upgraded to a password hash after a successful sign-in. Re-importing the SQL file does not delete existing tables or records.
