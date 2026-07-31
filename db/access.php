<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View the slideshow (students and above).
    'mod/slideshow:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'guest'           => CAP_ALLOW,
            'student'         => CAP_ALLOW,
            'teacher'         => CAP_ALLOW,
            'editingteacher'  => CAP_ALLOW,
            'manager'         => CAP_ALLOW,
        ],
    ],

    // Create a new slideshow activity.
    'mod/slideshow:addinstance' => [
        'riskbitmask'   => RISK_XSS,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_COURSE,
        'archetypes'    => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    // NEW: Manage slideshow (required for AJAX reorder).
    'mod/slideshow:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
