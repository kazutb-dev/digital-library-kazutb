<?php

return [
    'create' => ['title' => 'Move copies', 'description' => 'Scan inventory numbers or barcodes. The whole batch is moved atomically.'],
    'fields' => ['copy_codes' => 'Copies', 'branch' => 'Branch', 'fund' => 'Fund', 'sigla' => 'Storage sigla', 'service_point' => 'Service point', 'shelf_index' => 'Shelf index', 'shelf' => 'Shelf / placement', 'reason' => 'Movement reason', 'search' => 'Search', 'date_from' => 'Date from', 'date_to' => 'Date to', 'route' => 'From — to', 'from' => 'From', 'to' => 'To'],
    'placeholders' => ['copy_codes' => "4404\nLIB-2026-0000123", 'search' => 'Inventory number, barcode or title'],
    'hints' => ['copy_codes' => 'One code per line; scanner-friendly and keyboard-only.'],
    'actions' => ['move' => 'Move batch'],
    'events' => ['fund_movement' => 'Fund movement', 'location_changed' => 'Placement changed', 'transfer_received' => 'Transfer received'],
    'moved' => ':count copies moved. Batch: :batch.',
    'validation' => [
        'codes_required' => 'Enter at least one inventory number or barcode.', 'destination_required' => 'Enter at least one new placement field.',
        'codes_unknown' => 'Copies were not found: :codes.', 'codes_ambiguous' => 'One of the codes is ambiguous. Check the input.',
        'fund_branch_mismatch' => 'The selected fund does not belong to the selected branch.', 'active_loan' => 'Copy :copy is currently on loan.',
        'active_reservation' => 'Copy :copy has an active reservation.', 'status_blocked' => 'Copy :copy cannot be moved: :status.',
        'unchanged' => 'Placement did not change for copy :copy.',
    ],
];
