<?php

return [
    'fields' => ['source' => 'Source', 'rights_holder' => 'Rights holder', 'copyright_status' => 'Copyright status', 'licence_type' => 'Licence', 'licence_text' => 'Licence / permission terms', 'permission_date' => 'Permission date', 'access_policy' => 'Access policy', 'embargo_until' => 'Embargo until', 'post_embargo_access_policy' => 'Access after embargo', 'primary_author_orcid' => 'Primary author ORCID (optional)', 'version_reason' => 'Version change reason', 'scheduled_for' => 'Publish at'],
    'post_embargo_help' => 'Explicitly select the approved policy that takes effect after the embargo expires.',
    'copyright' => ['unknown' => 'Unknown', 'public_domain' => 'Public domain', 'permission_granted' => 'Permission granted', 'university_owned' => 'University owned', 'licensed' => 'Licensed', 'restricted' => 'Restricted'],
    'access' => ['metadata_only' => 'Metadata only', 'full_public' => 'Public full text', 'metadata_public_file_authenticated' => 'Metadata public, file after sign-in', 'campus_only' => 'Campus only', 'restricted' => 'Restricted', 'embargoed' => 'Embargoed'],
    'validation' => ['invalid_transition' => 'This status transition is not available.', 'rights_required' => 'Rights must be confirmed before publication.', 'reason_required' => 'Provide a reason for this decision.', 'schedule_required' => 'Choose a future publication time.', 'approval_required' => 'Publication requires approval by the library director.', 'pdf_required' => 'Upload the work as a PDF before publication.', 'full_public_required' => 'Select public full-text access before publication.'],
];
