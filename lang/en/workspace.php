<?php

return [
    'eyebrow'=>'Daily operations centre','navigation'=>'Librarian workspace sections',
    'sections'=>[
        'search'=>['title'=>'Global operational search','short'=>'Search','description'=>'Find catalogue, copy, reader and operation records within your permissions.'],
        'tasks'=>['title'=>'Staff tasks','short'=>'Tasks','description'=>'Work queue ordered by priority, deadline and assignee.'],
        'calendar'=>['title'=>'Library calendar','short'=>'Calendar','description'=>'Events, personal tasks and licence deadlines.'],
        'movements'=>['title'=>'Fund movement','short'=>'Fund movement','description'=>'Evidence-backed operations from copy history.'],
        'orders'=>['title'=>'Acquisition orders','short'=>'Orders','description'=>'Orders from request through receipt.'],
        'edd'=>['title'=>'Electronic document delivery','short'=>'EDD','description'=>'Document search and delivery queue with explicit rights restrictions.'],
        'periodicals'=>['title'=>'Periodicals','short'=>'Periodicals','description'=>'Expected, received and missing issue ledger.'],
    ],
    'fields'=>['title'=>'Title','type'=>'Type','priority'=>'Priority','assigned_to'=>'Assignee','responsible'=>'Responsible','due_at'=>'Due','comment'=>'Comment','status'=>'Status','order_number'=>'Order no.','supplier'=>'Supplier','expected_at'=>'Expected','document'=>'Document','quantity'=>'Quantity','received_quantity'=>'Received / ordered','received_now'=>'Receive now','catalog_record_id'=>'Bibliographic record ID','unit_price'=>'Unit price','total'=>'Total','request_number'=>'Request no.','source'=>'Source','rights'=>'Rights restrictions','year'=>'Year','expected_issues'=>'Expected issues','received_issues'=>'Received issues','branch'=>'Branch','fund'=>'Fund','date'=>'Date','operation'=>'Operation','copy'=>'Copy','month'=>'Month'],
    'actions'=>['create_task'=>'Create task','create_order'=>'Create order','receive_order'=>'Register receipt','create_edd'=>'Create EDD request','create_periodical'=>'Add subscription','receive_issue'=>'Receive issue','search'=>'Search'],
    'task_types'=>['general'=>'General','catalogue'=>'Catalogue','circulation'=>'Circulation','incident'=>'Incident','message'=>'Inquiry','electronic'=>'Electronic resource','event'=>'Event','licence'=>'Licence'],
    'priorities'=>['low'=>'Low','normal'=>'Normal','high'=>'High','critical'=>'Critical'],
    'statuses'=>['open'=>'Open','in_progress'=>'In progress','blocked'=>'Blocked','completed'=>'Completed','cancelled'=>'Cancelled','requested'=>'Requested','approved'=>'Approved','ordered'=>'Ordered','partially_received'=>'Partially received','received'=>'Received','searching'=>'Searching','rejected'=>'Rejected','active'=>'Active','expected'=>'Expected','missing'=>'Missing','claimed'=>'Claim sent','published'=>'Published','pending_review'=>'Pending review'],
    'calendar_types'=>['news'=>'News/event','task'=>'Task','licence'=>'Licence'], 'operation_types'=>['loan'=>'Loan','reservation'=>'Reservation','incident'=>'Incident'],
    'search_placeholder'=>'Book, ISBN, inventory no., barcode, reader, ticket…','search_groups'=>['records'=>'Bibliographic records','copies'=>'Copies','readers'=>'Readers','operations'=>'Operations'],
    'empty'=>['tasks'=>'No active tasks. No action is required.','orders'=>'No acquisition orders.','edd'=>'No EDD requests.','periodicals'=>'No periodical subscriptions.','movements'=>'No fund movement records.','calendar'=>'No events this month.','search'=>'No matches found.'],
    'messages'=>['task_created'=>'Task created.','task_updated'=>'Task updated.','order_created'=>'Order created.','order_received'=>'Receipt registered.','record_not_linked'=>'No bibliographic record is linked yet.','receipt_complete'=>'Receipt is complete.','cancelled_order_cannot_receive'=>'Items cannot be received against a cancelled order.','receipt_exceeds_order'=>'The received quantity cannot exceed the ordered quantity.','receipt_no_change'=>'Enter a received quantity or link a bibliographic record.','receipt_record_required'=>'Link a bibliographic record before registering a positive receipt.','receipt_record_locked'=>'The bibliographic record cannot be changed after a receipt has been registered.','edd_created'=>'EDD request created.','periodical_created'=>'Subscription added.','issue_saved'=>'Issue saved.'],
];
