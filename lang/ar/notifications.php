<?php

return [
    'manager_assigned' => [
        'title' => 'مركز جديد تحت إدارتك',
        'message' => 'تمت إضافتك مديراً لـ :center.',
    ],
    'practitioner_joined' => [
        'title' => 'مركز جديد',
        'message' => 'لقد انضممت إلى :center.',
        'mail_subject' => 'لقد انضممت إلى مركز جديد',
        'mail_greeting' => 'مرحباً،',
        'mail_line' => 'تمت إضافتك ممارساً في :center.',
        'mail_hint' => 'يمكنك التبديل إلى هذا المركز من القائمة أعلى التطبيق.',
        'mail_action' => 'الدخول إلى التطبيق',
    ],
    'appointment_assigned' => [
        'title' => 'موعد جديد',
    ],
    'appointment_reminder' => [
        'title' => 'تذكير بالموعد',
    ],
    'appointment' => [
        'message' => 'موعد يوم :date مع :patient.',
    ],
    'welcome' => [
        'subject' => 'مرحباً — حدّد كلمة المرور',
        'greeting' => 'مرحباً :name،',
        'intro' => 'تم إنشاء حساب لك.',
        'line' => 'يرجى تحديد كلمة المرور للدخول.',
        'action' => 'تحديد كلمة المرور',
        'expires' => 'ستنتهي صلاحية هذا الرابط خلال :count دقيقة.',
    ],
    'patient_reminder' => [
        'subject' => 'تذكير — موعد يوم :date',
        'greeting' => 'مرحباً :name',
        'line' => 'نذكّركم بموعدكم يوم :date على الساعة :time.',
        'center' => 'المركز: :center',
        'practitioner' => 'الممارس: :practitioner',
        'join' => 'الانضمام إلى الاستشارة',
    ],
];
