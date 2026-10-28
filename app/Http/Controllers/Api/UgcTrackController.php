<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FindsExistingUgcByUuid;
use Wm\WmPackage\Http\Controllers\Api\UgcTrackController as BaseUgcTrackController;

/**
 * STRATO 1 — DA CANCELLARE (oc:8867): copia locale destinata a essere cancellata.
 *
 * Sostituisce il controller omonimo di wm-package tramite bind nel container
 * in AppServiceProvider::register(). Va eliminata (insieme al bind) quando
 * wm-package verrà aggiornato con la correzione.
 */
class UgcTrackController extends BaseUgcTrackController
{
    use FindsExistingUgcByUuid;
}
