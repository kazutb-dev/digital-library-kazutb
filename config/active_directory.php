<?php

return [
    'enabled' => (bool) env('AD_ENABLED', false),
    'host' => env('AD_HOST'),
    'port' => (int) env('AD_PORT', env('AD_USE_SSL', true) ? 636 : 389),
    'use_ssl' => (bool) env('AD_USE_SSL', true),
    'timeout' => max(1, min(30, (int) env('AD_TIMEOUT', 5))),
    'bind_dn' => env('AD_BIND_DN'),
    'bind_password' => env('AD_BIND_PASSWORD'),
    'base_dn' => env('AD_BASE_DN'),
    'login_field' => env('AD_LOGIN_FIELD', 'samaccountname'),
    'user_filter' => env('AD_USER_FILTER', '(&(objectCategory=person)(objectClass=user))'),
    'require_cert' => (bool) env('AD_REQUIRE_CERT', true),
    'ca_cert_path' => env('AD_CA_CERT_PATH'),
    'privileged_group_mapping' => [],
];
