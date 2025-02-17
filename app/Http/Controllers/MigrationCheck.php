<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MigrationCheck extends Controller
{
    const preparedQueries = [
        'count hiking routes' => [
            'legacy' => "select count(*) from hiking_routes;",
            'current' => "select count(*) from hiking_routes;"
        ]
    ];

    public function show(Request $request)
    {

        $validated = $request->validate(
            [
                'query' => 'string|nullable',
                'preparedQuery' => 'string|nullable'
            ]
        );

        $dataLegacy = 'No query was run';
        $dataCurrent = 'No query was run';

        if (
            isset($validated['preparedQuery'])
            && isset($this::preparedQueries[$validated['preparedQuery']])
        ) {
            $queries = $this::preparedQueries[$validated['preparedQuery']];
            $dataLegacy = $this->runOverLegacy($queries['legacy']);
            $dataCurrent = $this->runOverCurrent($queries['current']);
        } elseif (isset($validated['query']) && ($query = $validated['query'])) {
            $request->flashOnly(['query']);
            $dataLegacy = $this->runOverLegacy($query);
            $dataCurrent = $this->runOverCurrent($query);
        }



        return view('migrationCheck', [
            'preparedQueries' => array_keys($this::preparedQueries),
            'dataLegacy' => $dataLegacy,
            'dataCurrent' => $dataCurrent
        ]);
    }



    ###########################

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
