<?php

return [
    'eyebrow' => 'Scientific Library',
    'home' => 'Home',
    'back' => 'Back',
    'request_id' => 'Request ID: :id',
    'pages' => [
        '401' => ['title' => 'Sign-in required', 'body' => 'Sign in to the library system to open this page.'],
        '403' => ['title' => 'Access denied', 'body' => 'Your role is not permitted to perform this action.'],
        '404' => ['title' => 'Page not found', 'body' => 'The address may have changed or the material may have moved.'],
        '419' => ['title' => 'Session expired', 'body' => 'Refresh the page and try again to continue securely.'],
        '422' => ['title' => 'Check the submitted data', 'body' => 'Some fields are invalid. Correct the errors and submit the form again.'],
        '429' => ['title' => 'Too many requests', 'body' => 'Wait a moment and try again later.'],
        '500' => ['title' => 'Service temporarily unavailable', 'body' => 'The error has been recorded. Try refreshing the page a little later.'],
        '503' => ['title' => 'Maintenance in progress', 'body' => 'The service is temporarily paused. Please return later.'],
    ],
];
