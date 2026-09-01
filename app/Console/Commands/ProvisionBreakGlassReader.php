<?php

namespace App\Console\Commands;

use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Provisions (or resets) a single break-glass reader account that can log in
 * with a local password while Active Directory remains the primary provider.
 *
 * The account only works when BREAK_GLASS_LOGIN_ENABLED=true; with the flag off
 * it is inert. Intended for controlled testing or emergency access, never as a
 * standing production login.
 */
class ProvisionBreakGlassReader extends Command
{
    protected $signature = 'library:break-glass-reader
        {email : Account email / login}
        {--name= : Display name}
        {--password= : Password (generated if omitted)}
        {--category=student : Reader category}';

    protected $description = 'Create or reset a break-glass reader account for local password login';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email is required.');

            return self::FAILURE;
        }

        $category = (string) $this->option('category');
        if (! in_array($category, ReaderProfile::CATEGORIES, true)) {
            $this->error('Category must be one of: '.implode(', ', ReaderProfile::CATEGORIES));

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: 'Тестовый читатель'));
        $password = (string) ($this->option('password') ?: Str::password(16));

        try {
            $user = DB::transaction(function () use ($email, $name, $password, $category): User {
                Role::findOrCreate('member', 'web');

                $user = User::query()->firstOrNew(['email' => $email]);
                $user->fill([
                    'name' => $name,
                    'password' => Hash::make($password),
                    // auth_provider is constrained to demo|ldap; the break-glass
                    // discriminator lives in auth_source.
                    'auth_provider' => 'demo',
                    'auth_source' => 'local_break_glass',
                    'role' => 'reader',
                    'role_source' => 'manual',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                if (! $user->exists) {
                    $user->locale = 'ru';
                }
                $user->save();
                $user->syncRoles(['member']);

                ReaderProfile::query()->updateOrCreate(
                    ['user_id' => $user->getKey()],
                    [
                        'ticket_number' => ReaderProfile::query()->where('user_id', $user->getKey())->value('ticket_number')
                            ?? ReaderProfile::nextTicketNumber(),
                        'barcode' => ReaderProfile::query()->where('user_id', $user->getKey())->value('barcode')
                            ?? ReaderProfile::nextBarcode(),
                        'category' => $category,
                        'status' => 'active',
                    ],
                );

                return $user;
            });
        } catch (Throwable $exception) {
            $this->error('Failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Break-glass reader ready.');
        $this->table(['Field', 'Value'], [
            ['Email / login', $user->email],
            ['Password', $password],
            ['Category', $category],
            ['Role', 'member'],
            ['auth_source', 'local_break_glass'],
        ]);
        $this->warn('Login works only while BREAK_GLASS_LOGIN_ENABLED=true. Disable it after testing.');

        return self::SUCCESS;
    }
}
