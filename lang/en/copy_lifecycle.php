<?php

return [
    'inventory'=>'Collection lifecycle','circulation'=>'Circulation availability',
    'inventory_statuses'=>['active'=>'In collection','damaged'=>'Damaged','repair'=>'In repair','lost'=>'Lost','written_off'=>'Withdrawn'],
    'circulation_statuses'=>['available'=>'Available','reserved'=>'Reserved','on_hold'=>'On hold shelf','on_loan'=>'On loan','in_transfer'=>'In transfer','unavailable'=>'Unavailable'],
    'fields'=>['writeoff_date'=>'Withdrawal date','writeoff_act'=>'Withdrawal act','writeoff_reason'=>'Withdrawal reason'],
    'writeoff_warning'=>'The copy remains in the database but becomes unavailable. Assigned reservations are cancelled, and the action is recorded in KSU-2 and the audit log.',
    'reservation_cancel_reason'=>'Copy :copy was withdrawn from circulation.',
    'validation'=>['written_off_immutable'=>'A withdrawn copy cannot be restored through the ordinary status action.','no_state_change'=>'The copy is already in the requested state.'],
    'legacy'=>[
        'title'=>'MARC-SQL technical provenance','description'=>'Immutable source values for reconciliation; canonical edits do not rewrite them.',
        'fields'=>['legacy_inv_id'=>'Source INV ID','legacy_doc_id'=>'Source DOC ID','legacy_inventory_number'=>'Source inventory no.','sigla_code'=>'Sigla code','legacy_sigla_id'=>'Source SIGLA_ID','local_library_code'=>'Local library code','fund_raw'=>'T090w / fund_raw','price_raw'=>'Source price','accounting_mode_raw'=>'Source accounting mode','legacy_state_raw'=>'INV.STATE','legacy_state_label'=>'Source status','legacy_notes'=>'Source notes'],
    ],
];
