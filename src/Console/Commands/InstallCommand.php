<?php

namespace NepalPay\Gateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class InstallCommand extends Command
{
    protected $signature = 'nepal-payment:install';
    protected $description = 'Install the Nepal Payment Gateway package (publish config, run migrations, set up admin account)';

    public function handle(): int
    {
        $this->info('Installing Nepal Payment Gateway...');
        $this->newLine();

        $this->call('vendor:publish', ['--tag' => 'nepal-payment-config']);
        $this->call('vendor:publish', ['--tag' => 'nepal-payment-migrations']);
        $this->call('migrate');

        $this->newLine();
        $this->setUpAdminAccount();

        $this->newLine();
        $this->info('✅ Installation complete!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Add "Pay" buttons anywhere: <x-nepal-payment::buttons :amount="100" />');
        $this->line('  2. Manage gateways at: ' . url(config('nepal-payment.admin.route_prefix', 'nepal-payment/admin')));
        return self::SUCCESS;
    }

    protected function setUpAdminAccount(): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->warn('No .env file found — skipping admin account setup. Set NEPAL_PAYMENT_ADMIN_USER and NEPAL_PAYMENT_ADMIN_PASSWORD_HASH manually.');
            return;
        }

        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, 'NEPAL_PAYMENT_ADMIN_PASSWORD_HASH=') && !str_contains($envContent, 'NEPAL_PAYMENT_ADMIN_PASSWORD_HASH=' . PHP_EOL) && !preg_match('/NEPAL_PAYMENT_ADMIN_PASSWORD_HASH=\s*$/m', $envContent)) {
            $this->info('Admin account already configured — skipping.');
            return;
        }

        $this->info('Let\'s set up your admin dashboard login.');

        $username = $this->ask('Choose an admin username', 'admin');

        $this->comment('(Your password will not be shown as you type - this is normal for security.)');
        $password = $this->secret('Choose an admin password');

        while (strlen($password ?? '') < 6) {
            $this->warn('Password should be at least 6 characters. Please try again.');
            $password = $this->secret('Choose an admin password');
        }

        $this->comment('(Please re-type it to confirm.)');
        $confirmPassword = $this->secret('Confirm admin password');

        while ($confirmPassword !== $password) {
            $this->error('Passwords did not match. Please try again.');
            $password = $this->secret('Choose an admin password');
            $confirmPassword = $this->secret('Confirm admin password');
        }

        $hash = Hash::make($password);

        $this->setEnvValue($envPath, 'NEPAL_PAYMENT_ADMIN_USER', $username);
        $this->setEnvValue($envPath, 'NEPAL_PAYMENT_ADMIN_PASSWORD_HASH', $hash);

        $this->info("Admin account saved. Username: {$username}");
    }

    protected function setEnvValue(string $path, string $key, string $value): void
    {
        $content = file_get_contents($path);
        $escapedValue = str_contains($value, ' ') || str_contains($value, '#') ? '"' . $value . '"' : $value;

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$escapedValue}", $content);
        } else {
            $content .= PHP_EOL . "{$key}={$escapedValue}";
        }

        file_put_contents($path, $content);
    }
}