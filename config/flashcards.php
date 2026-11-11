<?php

// Application settings. See SPECIFICATION.md §9 and §10.5.

return [

    // Anyone can create a learner account while this is true.
    'registration_open' => (bool) env('FLASHCARDS_REGISTRATION_OPEN', true),

    // Required by the one-time /install page that creates the first admin.
    'install_token' => env('FLASHCARDS_INSTALL_TOKEN'),

    // Learner settings: defaults for new learners and the allowed ranges.
    'session_size' => [
        'default' => (int) env('FLASHCARDS_DEFAULT_SESSION_SIZE', 20),
        'min' => 5,
        'max' => 100,
    ],

    'postpone_sessions' => [
        'default' => (int) env('FLASHCARDS_DEFAULT_POSTPONE_SESSIONS', 10),
        'min' => 1,
        'max' => 100,
    ],

    'complexity' => [
        'default' => 5,
        'min' => 1,
        'max' => 10,
    ],

];
