<?php

return [
    'minutes_per_cut' => (int) env('SHOP_MINUTES_PER_CUT', 20),
    'call_window_seconds' => (int) env('SHOP_CALL_WINDOW_SECONDS', 30),
    'reminder_lead_seconds' => (int) env('SHOP_REMINDER_LEAD_SECONDS', 60),
    'default_country_code' => env('SHOP_COUNTRY_CODE', '60'),
    'verify_phone' => (bool) env('SHOP_VERIFY_PHONE', true),
    'booking' => [
        'open_time' => env('SHOP_OPEN_TIME', '10:00'),
        'close_time' => env('SHOP_CLOSE_TIME', '21:00'),
        'slot_step_minutes' => 30,
        'weekly_closed_days' => array_map('intval', array_filter(explode(',', env('SHOP_WEEKLY_CLOSED_DAYS', '')), fn (string $day) => $day !== '')),
        'grace_minutes' => (int) env('SHOP_BOOKING_GRACE_MINUTES', 30),
        'max_advance_days' => 60,
        'max_active_per_customer' => 3,
    ],
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'fallback_template' => env('WHATSAPP_FALLBACK_TEMPLATE'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
    ],
];
