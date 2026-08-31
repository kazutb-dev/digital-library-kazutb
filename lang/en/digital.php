<?php

return [
    'ui' => ['eyebrow' => 'Digital library', 'subtitle' => 'Workflow, rights and protected access policies', 'search' => 'Search materials', 'all_statuses' => 'All statuses', 'workflow' => 'Workflow', 'versions' => 'Versions', 'reason' => 'Reason for action', 'no_versions' => 'No file versions yet'],
    'fields' => ['type' => 'Type', 'language' => 'Language', 'title' => 'Title', 'description' => 'Description', 'source' => 'Source', 'rights_holder' => 'Rights holder', 'copyright' => 'Copyright status', 'licence' => 'Licence', 'access' => 'Access', 'preview_policy' => 'Preview', 'download_policy' => 'Download', 'print_policy' => 'Print', 'copy_policy' => 'Copy', 'campus_only' => 'Campus only', 'embargo_until' => 'Embargo until'],
    'statuses' => ['uploaded' => 'Uploaded', 'quarantined' => 'Quarantined', 'metadata_review' => 'Metadata review', 'rights_review' => 'Rights review', 'processing' => 'Processing', 'ready_for_review' => 'Ready for review', 'approved' => 'Approved', 'published' => 'Published', 'restricted' => 'Restricted', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn', 'archived' => 'Archived', 'processing_failed' => 'Processing failed'],
    'types' => ['book_pdf' => 'Book PDF', 'image_collection' => 'Image collection', 'presentation' => 'Presentation', 'scientific_work' => 'Scientific work', 'methodological_material' => 'Methodological material', 'supplementary_file' => 'Supplementary file'],
    'file_types' => ['pdf' => 'PDF', 'image' => 'Image', 'presentation' => 'Presentation', 'document' => 'Document'],
    'copyright' => ['public_domain' => 'Public domain', 'permission_granted' => 'Permission granted', 'university_owned' => 'University owned', 'licensed' => 'Licensed', 'restricted' => 'Restricted', 'unknown' => 'Unknown'],
    'access' => ['public' => 'Public', 'authenticated' => 'Signed-in users', 'student' => 'Students', 'faculty' => 'Faculty', 'staff' => 'Staff', 'librarian' => 'Librarians', 'campus_only' => 'Campus only', 'restricted_roles' => 'Selected roles', 'embargoed' => 'Embargoed', 'metadata_only' => 'Metadata only', 'restricted' => 'Restricted'],
    'options' => ['none' => 'None', 'metadata' => 'Metadata only', 'sample' => 'Sample', 'full' => 'Full', 'disabled' => 'Disabled', 'allowed' => 'Allowed'],
    'version' => ['initial_upload' => 'Initial upload', 'replacement' => 'New file version'],
    'validation' => ['duplicate_checksum' => 'This file is already registered.', 'storage_failed' => 'The file could not be saved to protected storage.', 'invalid_transition' => 'This status transition is not available.', 'rights_required' => 'Rights holder, source, and licence must be confirmed before publication.'],
    'external' => [
        'fields' => ['access_method' => 'Access method', 'publication_status' => 'Publication status', 'guest_access' => 'Guest access', 'campus_only' => 'Campus network only', 'login_required' => 'Sign-in required', 'contract_number' => 'Contract number', 'contract_starts_at' => 'Contract start', 'contract_ends_at' => 'Contract end', 'renewal_at' => 'Renewal date', 'licence_file' => 'Licence document (protected)', 'contract_change_reason' => 'Contract change reason', 'internal_notes' => 'Internal notes'],
        'access_methods' => ['public_url' => 'Public URL', 'institutional_sso' => 'Institutional SSO', 'ip_based' => 'IP based', 'campus_only' => 'Campus only', 'personal_account' => 'Personal account', 'librarian_mediated' => 'Librarian mediated', 'manual_instructions' => 'Manual instructions'],
        'publication_statuses' => ['draft' => 'Draft', 'review' => 'In review', 'published' => 'Published', 'archived' => 'Archived'],
        'licence_notice_title' => 'External resource licence', 'licence_notice_body' => 'The licence for “:title” has :days days remaining.',
        'health_outage_title' => 'External resource unavailable', 'health_outage_body' => 'The anonymous automated check could not reach “:title”. Verify its URL and availability.',
    ],
];
