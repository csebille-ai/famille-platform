<?php

return [
    'activity' => [
        // 1.0 = log every page view; 0.25 = log 1 in 4 approx.
        'page_view_sample' => (float) env('ACTIVITY_PAGE_VIEW_SAMPLE', 1),
    ],

    'errors' => [
        // Enable DB error logging channel.
        'log_to_db' => (bool) env('LOG_ERRORS_TO_DB', true),
    ],
];
