<?php

if (! function_exists('cas')) {
    function cas(): \App\Services\CasManager
    {
        return app('cas');
    }
}
