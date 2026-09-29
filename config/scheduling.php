<?php

return [
    /**
     * Delay before an appointment at which its reminder is sent.
     * A single global value in this V1 — not configurable per center
     * (keeps the sending window logic simple; a per-center override can
     * be added later as a nullable column on Center falling back to this
     * default, without changing the command).
     */
    'appointment_reminder_hours_before' => (int) env('APPOINTMENT_REMINDER_HOURS_BEFORE', 24),
];
