<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MigrationCheck extends Controller
{
    const preparedQueries = [
        // USERS
        'count hiking routes' => [
            'legacy' => "select count(*) from hiking_routes;",
            'current' => "select count(*) from hiking_routes;",
        ],
        'count users admin' => [
            'legacy' => "select count(*) from users where is_administrator = true;",
            'current' => "select count(DISTINCT model_id) from model_has_roles where role_id=1 AND model_type='App\Models\User';",
        ],
        'count users itinerary manager' => [
            'legacy' => "select count(*) from users where is_itinerary_manager = true;",
            'current' => "select count(DISTINCT model_id) from model_has_roles where role_id=2 AND model_type='App\Models\User';",
        ],
        'count users natioanal referent' => [
            'legacy' => "select count(*) from users where is_national_referent = true;",
            'current' => "select count(DISTINCT model_id) from model_has_roles where role_id=3 AND model_type='App\Models\User';",
        ],
        'count users regional referent' => [
            'legacy' => "select count(*) from users where region_id IS NOT NULL;",
            'current' => "select count(DISTINCT model_id) from model_has_roles where role_id=4 AND model_type='App\Models\User';",
        ],
        'count users local referent' => [
            'legacy' => "select (select count(DISTINCT user_id) from area_user) + (select count(DISTINCT user_id) from sector_user);",
            'current' => "select count(DISTINCT model_id) from model_has_roles where role_id=5 AND model_type='App\Models\User';",
        ],
        'count users sectional referent' => [
            'legacy' => "select count(*) from users where section_id IS NOT NULL;",
            'current' => "select count(DISTINCT model_id) from model_has_roles where role_id=6 AND model_type='App\Models\User';",
        ]

    ];

    public function show(Request $request)
    {
        $validated = $request->validate(
            [
                'queryLegacy' => 'string|nullable',
                'queryCurrent' => 'string|nullable',
                'preparedQuery' => 'string|nullable',
                'run_all' => 'boolean|nullable',
            ]
        );

        //dd($request->all());
        $queryLegacy = $validated['queryLegacy'] ?? false;
        $queryCurrent = $validated['queryCurrent'] ?? $queryLegacy;
        $dataLegacy = 'No query was run';
        $dataCurrent = 'No query was run';

        if (isset($validated['run_all']) && $validated['run_all']) {
            $dataCurrent = ['report' => []];
            $dataLegacy = ['report' => []];
            $queryLegacy = 'All prepared queries';
            $queryCurrent = 'All prepared queries';
            foreach ($this::preparedQueries as $name => $preparedQuery) {
                $legacy = $preparedQuery['legacy'];
                $dataLegacy[$name] = $this->runOverLegacy($legacy);

                $current = $preparedQuery['current'];
                $dataCurrent[$name] = $this->runOverCurrent($current);

                if ($dataCurrent[$name] !== $dataLegacy[$name]) {
                    $message = 'Different result '.strtoupper($name);
                    $dataCurrent['report'][] = $message;
                    $dataLegacy['report'][] = $message;
                }
            }
        } elseif (
            isset($validated['preparedQuery'])
            && ! empty($validated['preparedQuery'])
            && isset($this::preparedQueries[$validated['preparedQuery']])
        ) {
            $queries = $this::preparedQueries[$validated['preparedQuery']];
            $queryLegacy = $queries['legacy'];
            $dataLegacy = $this->runOverLegacy($queryLegacy);
            $queryCurrent = $queries['current'];
            $dataCurrent = $this->runOverCurrent($queryCurrent);
        } elseif ($queryLegacy && $queryCurrent) {
            $dataLegacy = $this->runOverLegacy($queryLegacy);
            $dataCurrent = $this->runOverCurrent($queryCurrent);
        }

        $request->flash();

        return view('migrationCheck', [
            'preparedQueries' => array_keys($this::preparedQueries),
            'dataLegacy' => $dataLegacy,
            'dataCurrent' => $dataCurrent,
            'queryLegacy' => $queryLegacy,
            'queryCurrent' => $queryCurrent,
        ]);
    }

    //##########################

    protected function runOverLegacy(string $sql)
    {
        return $this->run($sql, 'legacyosm2cai');
    }

    protected function runOverCurrent(string $sql)
    {
        return $this->run($sql);
    }

    protected function run(string $sql, $connection = 'pgsql')
    {
        $connection = DB::connection($connection);
        $connection->beginTransaction();
        try {
            $r = $connection->select($sql);
        } catch (Exception $e) {
            $r = $e->getMessage();
        }
        DB::rollBack();

        return $r;
    }
}
