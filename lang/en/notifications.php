<?php

return [
    'manager_assigned' => [
        'title' => 'New managed center',
        'message' => 'You have been added as manager of :center.',
    ],
    'practitioner_joined' => [
        'title' => 'New center',
        'message' => 'You have joined :center.',
        'mail_subject' => 'You have joined a new center',
        'mail_greeting' => 'Hello,',
        'mail_line' => 'You have been added as practitioner at :center.',
        'mail_hint' => 'You can switch to this center from the selector at the top of the application.',
        'mail_action' => 'Open the application',
    ],
    'appointment_assigned' => [
        'title' => 'New appointment',
    ],
    'appointment_reminder' => [
        'title' => 'Appointment reminder',
    ],
    'appointment' => [
        'message' => 'Appointment on :date with :patient.',
    ],
    'welcome' => [
        'subject' => 'Welcome — set your password',
        'greeting' => 'Hello :name,',
        'intro' => 'An account has just been created for you.',
        'line' => 'Please set your password to access it.',
        'action' => 'Set my password',
        'expires' => 'This link will expire in :count minutes.',
    ],
    'patient_reminder' => [
        'subject' => 'Reminder — appointment on :date',
        'greeting' => 'Hello :name',
        'line' => 'This is a reminder of your appointment on :date at :time.',
        'center' => 'Center: :center',
        'practitioner' => 'Practitioner: :practitioner',
        'join' => 'Join the consultation',
    ],
];
