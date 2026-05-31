<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['api', 'auth:api']]);
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->UserID === (int) $id;
});
