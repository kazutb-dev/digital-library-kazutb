<?php

$fields = [
    'max_active_reservations' => 'Active reservation limit',
    'reservation_hold_days' => 'Pickup hold, days',
    'max_active_loans' => 'Active loan limit',
    'standard_loan_period_days' => 'Standard loan period, days',
    'renewal_allowed' => 'Allow eligible renewals',
    'renewal_period_days' => 'Renewal period, days',
    'max_renewals' => 'Renewal limit',
    'fine_per_overdue_day' => 'Overdue fine per day, KZT (0 disables)',
    'inventory_batch_scan_limit' => 'Scans per inventory session',
    'inventory_numbering_enabled' => 'Automatic inventory numbering',
    'inventory_number_prefix' => 'Inventory number prefix',
    'barcode_generation_enabled' => 'Automatic barcode generation',
    'barcode_prefix' => 'Barcode prefix',
    'ksu_numbering_enabled' => 'New KSU number allocation',
    'ksu_yearly_reset' => 'Separate KSU sequence for every year',
    'default_service_point' => 'Default service point',
    'default_sigla' => 'Default storage sigla',
];

return [
    'kicker' => 'Controlled business policy',
    'title' => 'Library operation settings',
    'description' => 'Loan, reservation, numbering and placement defaults. Every change is validated and written to the audit log.',
    'circulation' => 'Circulation and reservations',
    'numbering' => 'New-arrival numbering',
    'numbering_help' => 'These switches affect only new confirmed acquisitions. Missing legacy barcodes and KSU numbers are never generated.',
    'inventory' => 'Inventory and placement',
    'save' => 'Save settings',
    'saved' => 'Library operation settings saved.',
    'fields' => $fields,
    'descriptions' => $fields,
];
