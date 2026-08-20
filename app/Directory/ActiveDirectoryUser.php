<?php

namespace App\Directory;

final readonly class ActiveDirectoryUser
{
    /** @param list<string> $groups */
    public function __construct(
        public string $objectGuid,
        public string $samAccountName,
        public string $distinguishedName,
        public bool $enabled,
        public ?string $userPrincipalName = null,
        public ?string $displayName = null,
        public ?string $givenName = null,
        public ?string $surname = null,
        public ?string $mail = null,
        public ?string $telephoneNumber = null,
        public ?string $department = null,
        public ?string $title = null,
        public ?string $employeeId = null,
        public array $groups = [],
    ) {}

    /** @return array<string,mixed> */
    public function safeArray(): array
    {
        return [
            'object_guid' => $this->objectGuid,
            'samaccountname' => $this->samAccountName,
            'user_principal_name' => $this->userPrincipalName,
            'display_name' => $this->displayName,
            'mail' => $this->mail,
            'department' => $this->department,
            'title' => $this->title,
            'enabled' => $this->enabled,
        ];
    }
}
