<?php

namespace App\Http\Controllers\Api;

use Wm\WmPackage\Http\Controllers\Api\UgcPoiController as BaseUgcPoiController;

/**
 * STRATO 1 di oc:8867 — copia locale destinata a essere cancellata.
 *
 * Sostituisce il controller omonimo di wm-package tramite bind nel container
 * in AppServiceProvider::register(). Va eliminata (insieme al bind) quando
 * wm-package verrà aggiornato con la correzione.
 */
class UgcPoiController extends BaseUgcPoiController {}
