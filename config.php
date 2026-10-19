<?php
/**
 * College Quiz System — configuration
 *
 * The app picks the "local" settings automatically when it runs on
 * localhost (XAMPP) and the "live" settings everywhere else
 * (InfinityFree or any other host). Edit both blocks below.
 */

return [

    // Name shown across the app
    'app_name' => 'College Quiz System',

    // ---- XAMPP (localhost) -------------------------------------
    'local' => [
        'db_host' => '127.0.0.1',
        'db_name' => 'college_quiz',
        'db_user' => 'root',
        'db_pass' => '',
        'debug'   => true,   // show detailed errors while developing
    ],

    // ---- InfinityFree (live) ------------------------------------
    // Copy these from InfinityFree Control Panel > MySQL Databases.
    'live' => [
        'db_host' => 'sqlXXX.infinityfree.com',   // "MySQL Hostname"
        'db_name' => 'if0_XXXXXXXX_college_quiz', // "MySQL DB Name"
        'db_user' => 'if0_XXXXXXXX',              // "MySQL Username"
        'db_pass' => 'your-vpanel-password',      // your hosting account password
        'debug'   => false,
    ],

    // Largest upload allowed for study materials (InfinityFree caps files at 10 MB)
    'max_upload_mb' => 10,

    // File types admins may upload as study materials
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'zip'],

    // Timezone used for timestamps and quiz timers
    'timezone' => 'Africa/Lagos',
];
