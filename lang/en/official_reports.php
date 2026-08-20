<?php

return [
    'title' => 'Official reports', 'subtitle' => 'Immutable snapshots, director approval, and a protected archive.',
    'archive' => 'Archive', 'create' => 'Capture a new snapshot', 'open_live' => 'Open live analytics',
    'fields' => ['type' => 'Report type', 'number' => 'Report number', 'period' => 'Period', 'from' => 'Date from', 'to' => 'Date to', 'note' => 'Revision note', 'status' => 'Status', 'revision' => 'Revision', 'created' => 'Created', 'creator' => 'Prepared by', 'approver' => 'Approved by', 'hash' => 'SHA-256'],
    'statuses' => ['draft' => 'Draft', 'generated' => 'Generated', 'pending_review' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'superseded' => 'Superseded', 'archived' => 'Archived', 'queued' => 'Queued', 'generating' => 'Generating', 'ready' => 'Ready', 'failed' => 'Failed'],
    'actions' => ['create' => 'Capture', 'submit' => 'Send to director', 'approve' => 'Approve', 'reject' => 'Reject', 'revise' => 'Create revision', 'archive' => 'Archive', 'delete' => 'Delete draft', 'source' => 'Source JSON', 'export' => 'Generate file', 'download' => 'Download', 'retry' => 'Retry'],
    'integrity_ok' => 'Source data and archive integrity verified.', 'integrity_failed' => 'Integrity verification failed. Approval and export are blocked.',
    'source_notice' => 'The figures below are read from the captured snapshot and are not recalculated from the live database.', 'revisions' => 'Revision chain', 'exports' => 'Report files', 'progress' => 'Progress: :value%', 'empty' => 'No official snapshots yet.', 'decision_note' => 'Decision note',
    'page' => 'Page',
    'notifications' => ['ready_title' => 'Official report is ready', 'ready_body' => 'The :format file for report :number is ready in the protected archive.', 'failed_title' => 'Report generation failed', 'failed_body' => 'The :format file for report :number could not be generated. Retry it from the archive.'],
    'messages' => ['created' => 'Report snapshot captured.', 'submitted' => 'Snapshot sent to the director.', 'approved' => 'Snapshot approved and locked against changes.', 'rejected' => 'Snapshot rejected.', 'revised' => 'New revision created.', 'archived' => 'Report archived.', 'deleted' => 'Draft deleted.', 'export_queued' => 'File generation queued.'],
];
