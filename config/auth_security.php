<?php

return [
    // ADR-068: lockout after configurable threshold, default 5. Lockout
    // duration isn't specified in the frozen ADR — 15 minutes chosen as a
    // reasonable default pending explicit founder/security sign-off.
    'lockout_threshold' => (int) env('AUTH_LOCKOUT_THRESHOLD', 5),
    'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
];
