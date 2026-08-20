<?php

namespace App\Directory;

use App\Exceptions\LibraryAuthenticationException;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class ActiveDirectoryProvisioner
{
    public function __construct(private AuditLogger $audit) {}

    public function provision(ActiveDirectoryUser $identity, Request $request, bool $authenticated = true): User
    {
        $guid = mb_strtolower($identity->objectGuid);
        $login = mb_strtolower($identity->samAccountName);
        $email = mb_strtolower(trim((string) ($identity->mail ?: $identity->userPrincipalName)));

        return DB::transaction(function () use ($identity, $request, $guid, $login, $email, $authenticated): User {
            $candidates = collect([
                User::query()->where('ad_object_guid', $guid)->lockForUpdate()->first(),
                $email !== '' ? User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first() : null,
                User::query()->whereRaw('LOWER(COALESCE(ad_samaccountname, ad_login, \'\')) = ?', [$login])->lockForUpdate()->first(),
            ])->filter()->unique(fn (User $user): int => (int) $user->getKey())->values();

            if ($candidates->count() > 1) {
                throw new LibraryAuthenticationException(__('auth.identity_conflict'), 409);
            }

            $user = $candidates->first() ?? new User;
            $created = ! $user->exists;
            $before = $created ? null : $this->snapshot($user->load('roles'));
            $values = [
                'name' => $identity->displayName ?: trim($identity->givenName.' '.$identity->surname) ?: $login,
                'ad_login' => $login,
                'ad_object_guid' => $guid,
                'ad_samaccountname' => $login,
                'ad_user_principal_name' => $identity->userPrincipalName,
                'ad_distinguished_name' => $identity->distinguishedName,
                'ad_last_synced_at' => now('UTC'),
                'auth_source' => 'active_directory',
                'auth_provider' => 'ldap',
                'external_id' => $guid,
                'given_name' => $identity->givenName,
                'surname' => $identity->surname,
                'telephone_number' => $identity->telephoneNumber,
                'department' => $identity->department,
                'job_title' => $identity->title,
                'employee_id' => $identity->employeeId,
            ];
            if ($authenticated) {
                $values['ad_last_login_at'] = now('UTC');
            }
            if ($email !== '') {
                $values['email'] = $email;
            } elseif ($created) {
                $values['email'] = $login.'@directory.invalid';
            }
            $user->fill($values);
            if ($created) {
                $user->forceFill([
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'reader',
                    'role_source' => 'manual',
                    'is_active' => true,
                    'email_verified_at' => now('UTC'),
                    'locale' => 'ru',
                ]);
            }
            $user->save();
            if ($created || $user->roles()->doesntExist()) {
                $user->syncRoles(['member']);
            }
            $user->refresh()->load('roles');
            $after = $this->snapshot($user);
            if ($created || $before !== $after) {
                $this->audit->logRequired(
                    $created ? 'create' : 'update',
                    'user',
                    $user->getKey(),
                    $before,
                    $after,
                    scope: 'security',
                    metadata: ['source' => 'active_directory'],
                    actor: ['name' => 'Active Directory identity bridge', 'role' => 'system'],
                    request: $request,
                );
            }

            return $user;
        });
    }

    /** @return array<string,mixed> */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'ad_object_guid' => $user->ad_object_guid,
            'ad_samaccountname' => $user->ad_samaccountname,
            'department' => $user->department,
            'roles' => $user->getRoleNames()->values()->all(),
            'reader_profile_id' => $user->readerProfile?->getKey(),
        ];
    }
}
