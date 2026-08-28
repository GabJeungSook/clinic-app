<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seed demo data on a fresh install
    |--------------------------------------------------------------------------
    |
    | When true, a brand-new install (empty database) is seeded with rich demo
    | data — patients, appointments, sales, treatments — for client
    | presentations. Leave it false for a real go-live: a fresh install then
    | gets only the clean baseline (service + inventory catalogue and the owner
    | login), with no demo records.
    |
    | Toggle it via CLINIC_SEED_DEMO in .env, then rebuild the desktop app.
    |
    */
    'seed_demo' => (bool) env('CLINIC_SEED_DEMO', false),
];
