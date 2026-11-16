<?php

/*
|--------------------------------------------------------------------------
| Meeting room schedule
|--------------------------------------------------------------------------
|
| Room hours are wall-clock times in the application timezone
| (config/app.php → 'timezone', env APP_TIMEZONE). The seeder turns them
| into hourly slots: the first slot starts at opens_at_hour, the last one
| ends at closes_at_hour.
|
*/

return [

    'opens_at_hour' => 9,

    'closes_at_hour' => 18,

    'slot_minutes' => 60,

    // How many calendar days the seeder covers, counting today.
    'seed_days' => 7,

];
