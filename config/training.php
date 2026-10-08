<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assistant Manager Position
    |--------------------------------------------------------------------------
    |
    | The "AM – Assistant Manager" position exists but is hidden from the Add
    | Trainee form until this is switched on.
    |
    */

    'assistant_manager_enabled' => (bool) env('TRAINING_ASSISTANT_MANAGER_ENABLED', false),

];
