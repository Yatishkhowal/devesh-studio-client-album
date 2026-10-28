<?php
/**
 * Devesh Studio – Client Album settings
 * -------------------------------------------------------------
 * Change only the values on the right side. Keep the quotes and commas.
 */
return [
    // Studio details shown on the login and album pages
    'studio_name'    => 'Devesh Studio',
    'studio_tagline' => 'A Complete Design Solution',
    'logo'           => 'images/logo.png',     // your existing website logo
    'favicon'        => 'images/favicon.png',
    'website_home'   => 'index.html',
    'timezone'       => 'Asia/Kolkata',

    // WhatsApp number with country code, digits only (91 = India). Please check this is your WhatsApp number.
    'whatsapp_number'  => '919799016153',
    'whatsapp_message' => 'Hello Devesh Studio, I need help with my wedding album login.',

    // Social links (same as the main website)
    'instagram' => 'https://instagram.com/devesh_studio',
    'facebook'  => 'https://www.facebook.com/Deveshstudiopaota/about',
    'youtube'   => 'https://youtube.com/@deveshstudio',

    // Shown in the album banner when a client has no message of their own
    'default_message' => 'Thank you for letting us be a part of your beautiful day. These memories are yours to keep forever.',

    // Security
    'session_timeout_minutes' => 120,  // client is logged out after 2 hours without activity
    'max_login_attempts'      => 5,    // wrong passwords allowed...
    'lockout_minutes'         => 10,   // ...before login is locked for this many minutes
    'real_ip_header'          => '',   // leave empty unless your hosting company tells you otherwise

    // Photo sizes made automatically from your uploads (pixels)
    'thumb_width'  => 640,   // gallery grid
    'large_size'   => 1800,  // full-screen view
    'jpeg_quality' => 82,
];
