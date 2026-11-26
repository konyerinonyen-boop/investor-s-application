# Dividend system — Phase 1 integration

Prepared for `konyerinonyen-boop/investor-s-application`, branch `investor-wallet-automation`.

This package adds the core dividend declaration, record-date allocation, approval, NGN wallet-credit, and Filament admin workflow.

## Files included

Copy the `app/` and `database/` directories into the matching folders of your Laravel project, preserving the paths. The updated `app/Services/InvestmentPaymentService.php` replaces the existing file and now updates both the live equity holding and ownership ledger when a wallet-funded equity investment completes.

## Important ownership history note

The inspected branch's wallet equity investment completion method reduced `equity_rounds.available_units` and completed the investment, but did not create the matching `equity_holdings` and `equity_ownership_transactions` entries. This package fixes that for new wallet-funded investments.

Existing investments need a one-time ownership-ledger backfill. The included command creates missing initial-purchase ledger entries from completed investments and successful equity-payment records. It does **not** change live holdings.

Do this on local/staging first, after taking a database backup:

```cmd
php artisan migrate
php artisan equity:backfill-ownership-ledger
```

You can restrict the backfill to one equity round:

```cmd
php artisan equity:backfill-ownership-ledger --round=1
```

The dividend allocation service deliberately refuses to calculate if the current holdings do not reconcile with the all-time ownership ledger. Do not bypass that check; reconcile the data first.

## Filament permissions

The resource uses Shield's generated permissions for `dividend declarations`. The four workflow actions also require these custom permissions:

```php
$dividendWorkflowPermissions = [
    'declare dividends',
    'calculate dividend allocations',
    'approve dividends',
    'pay dividends',
];

foreach ($dividendWorkflowPermissions as $permission) {
    \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
}
```

Add `dividend declarations` to the resource lists in `RolesAndPermissionsSeeder` for the roles that should view/manage dividends. Give `declare dividends` and `calculate dividend allocations` to the operations role if appropriate. Give `approve dividends` and `pay dividends` only to a separate authorized admin/finance role. Ensure super_admin/admin roles receive the four custom permissions. Then run:

```cmd
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan optimize:clear
```

## Workflow in admin

1. Create a draft under **Admin → Investment Operations → Dividends**.
2. Enter the equity round, distribution name, total pool (NGN), and record date.
3. Click **Declare**.
4. After the record date has fully passed, click **Calculate Allocations**.
5. Review the investor allocation list on the declaration view.
6. A different authorized user clicks **Approve**.
7. Click **Pay Wallets** to credit investor NGN wallets. Failed allocations remain retryable; paid allocations cannot be paid twice.

## Calculation and payment safeguards

- Eligibility is calculated from ownership ledger transactions through the end of the record date.
- The record date must be in the past; calculate the following day to ensure the date has fully elapsed.
- Allocation is in integer kobo using the largest-remainder method. The allocation total reconciles exactly to the declared pool.
- The declarer cannot approve their own declaration.
- Payment uses `WalletService::credit()` with a stable unique reference per allocation.
- Each allocation is locked and processed independently; retries do not double-credit.
- Only NGN wallet credits are supported in this first version.
- The system does not yet include dividend investor API endpoints or investor-facing UI; those are the next integration step.

## Validation

All included PHP files passed `php -l` in the package build environment. Run the Laravel migrations and test the full flow on staging before production. Also check the ownership ledger for every equity round before declaring a real dividend.
