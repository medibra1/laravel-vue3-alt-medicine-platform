<?php

return [
    'manager_assigned' => [
        'title' => 'Nouveau centre géré',
        'message' => 'Vous avez été ajouté comme manager sur :center.',
    ],
    'practitioner_joined' => [
        'title' => 'Nouveau centre',
        'message' => 'Vous avez rejoint :center.',
        'mail_subject' => 'Vous avez rejoint un nouveau centre',
        'mail_greeting' => 'Bonjour,',
        'mail_line' => 'Vous avez été ajouté comme praticien sur :center.',
        'mail_hint' => 'Vous pouvez basculer vers ce centre depuis le sélecteur en haut de l\'application.',
        'mail_action' => 'Accéder à l\'application',
    ],
    'appointment_assigned' => [
        'title' => 'Nouveau rendez-vous',
    ],
    'appointment_reminder' => [
        'title' => 'Rappel de rendez-vous',
    ],
    'appointment' => [
        'message' => 'Rendez-vous le :date avec :patient.',
    ],
    'welcome' => [
        'subject' => 'Bienvenue — définissez votre mot de passe',
        'greeting' => 'Bonjour :name,',
        'intro' => 'Un compte vient d\'être créé pour vous.',
        'line' => 'Veuillez définir votre mot de passe pour y accéder.',
        'action' => 'Définir mon mot de passe',
        'expires' => 'Ce lien expirera dans :count minutes.',
    ],
    'patient_reminder' => [
        'subject' => 'Rappel — rendez-vous du :date',
        'greeting' => 'Bonjour :name',
        'line' => 'Nous vous rappelons votre rendez-vous le :date à :time.',
        'center' => 'Centre : :center',
        'practitioner' => 'Praticien : :practitioner',
        'join' => 'Rejoindre la consultation',
    ],
];
