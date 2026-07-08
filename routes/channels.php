<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('obiora.server.{serverId}', function ($user, int $serverId) {
    return $user !== null;
});

Broadcast::channel('obiora.monitoring', function ($user) {
    return $user !== null;
});

Broadcast::channel('obiora.crash-analyzer', function ($user) {
    return $user !== null && $user->can('monitoring.view');
});

Broadcast::channel('obiora.progress.{serverId}.{scope}', function ($user, int $serverId, string $scope) {
    return $user !== null;
});
