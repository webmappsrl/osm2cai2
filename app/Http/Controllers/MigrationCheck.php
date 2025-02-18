<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MigrationCheck extends Controller
{
    const preparedQueries = [
        // USERS
        'count users' => [
            'legacy' => "select count(*) as num from users ;",
            'current' => "select count(*) as num from users ;;",
        ],
        'count users admin' => [
            'legacy' => "select count(*) as num from users where is_administrator = true;",
            'current' => "select count(DISTINCT model_id) as num from model_has_roles where role_id=1 AND model_type='App\Models\User';",
        ],
        'count users itinerary manager' => [
            'legacy' => "select count(*) as num from users where is_itinerary_manager = true;",
            'current' => "select count(DISTINCT model_id) as num from model_has_roles where role_id=2 AND model_type='App\Models\User';",
        ],
        'count users natioanal referent' => [
            'legacy' => "select count(*) as num from users where is_national_referent = true;",
            'current' => "select count(DISTINCT model_id) as num from model_has_roles where role_id=3 AND model_type='App\Models\User';",
        ],
        'count users regional referent' => [
            'legacy' => "select count(*) as num from users where region_id IS NOT NULL;",
            'current' => "select count(DISTINCT model_id) as num from model_has_roles where role_id=4 AND model_type='App\Models\User';",
        ],
        'count users local referent' => [
            'legacy' => "select (select count(DISTINCT user_id) from area_user) + (select count(DISTINCT user_id) from sector_user) as num;",
            'current' => "select count(DISTINCT model_id) as num from model_has_roles where role_id=5 AND model_type='App\Models\User';",
        ],
        'count membri del gruppo sentieri / 1' => [
            'legacy' => "select count(*) as num from users where section_id IS NOT NULL;",
            'current' => "select count(*) from users where managed_club_id is not null;",
        ],
        'count membri del gruppo sentieri / 2' => [
            'legacy' => "select count(*) as num from users where section_id IS NOT NULL;",
            'current' => "select count(DISTINCT model_id) as num from model_has_roles where role_id=6 AND model_type='App\Models\User';",
        ],
        'count responsabili gruppo sentieri / 1' => [
            'legacy' => "select count(*) from users where manager_section_id IS NOT NULL;",
            'current' => "select count(*) from users where managed_club_id is not null;",
        ],
        'count responsabili gruppo sentieri / 2' => [
            'legacy' => "select count(*) from users where manager_section_id IS NOT NULL;",
            'current' => "select count(DISTINCT model_id) from model_has_roles where role_id=10 AND model_type='App\Models\User';",
        ],

        // TODO: roles ID 8 and 9 missing
        // 8 | Guest              | web        | 2024-10-02 07:03:42 | 2024-10-02 07:03:42
        // 9 | No login user      | web        | 2024-10-02 07:03:42 | 2024-10-02 07:03:42

        // VALIDATOR (permissions)
        // id |             name              
        // ----+-------------------------------
        //   1 | validate source surveys <- resources_validator->>'is_source_validator'
        //   2 | validate archaeological sites <- resources_validator->>'is_archaeological_site_validator'
        //   3 | validate geological sites <- resources_validator->>'is_geological_site_validator'
        //   4 | validate archaeological areas <- resources_validator->>'is_archaeological_area_validator'
        //   5 | validate signs <- resources_validator->>'is_signs_validator'
        //   TODO: 6 | manage roles and permissions
        //   TODO: 7 | validate pois
        //   TODO: 8 | validate tracks

        'count source survey validators' => [
            'legacy' => "SELECT COUNT(*) as num FROM users WHERE (resources_validator->>'is_source_validator')::boolean = true;",
            'current' => "SELECT COUNT(DISTINCT model_id) as num FROM model_has_permissions WHERE permission_id=1 AND model_type='App\Models\User';",
        ],
        'count archaeological site validators' => [
            'legacy' => "SELECT COUNT(*) as num FROM users WHERE (resources_validator->>'is_archaeological_site_validator')::boolean = true;",
            'current' => "SELECT COUNT(DISTINCT model_id) as num FROM model_has_permissions WHERE permission_id=2 AND model_type='App\Models\User';",
        ],
        'count geological site validators' => [
            'legacy' => "SELECT COUNT(*) as num FROM users WHERE (resources_validator->>'is_geological_site_validator')::boolean = true;",
            'current' => "SELECT COUNT(DISTINCT model_id) as num FROM model_has_permissions WHERE permission_id=3 AND model_type='App\Models\User';",
        ],
        'count archaeological area validators' => [
            'legacy' => "SELECT COUNT(*) as num FROM users WHERE (resources_validator->>'is_archaeological_area_validator')::boolean = true;",
            'current' => "SELECT COUNT(DISTINCT model_id) as num FROM model_has_permissions WHERE permission_id=4 AND model_type='App\Models\User';",
        ],
        'count signs validators' => [
            'legacy' => "SELECT COUNT(*) as num FROM users WHERE (resources_validator->>'is_signs_validator')::boolean = true;",
            'current' => "SELECT COUNT(DISTINCT model_id) as num FROM model_has_permissions WHERE permission_id=5 AND model_type='App\Models\User';",
        ],


        // HIKING ROUTES
        'count hiking routes' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes;",
            'current' => "SELECT COUNT(*) as num from hiking_routes;",
        ],
        'count hiking routes with osm2cai_status=1' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=1;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=1;",
        ],
        'count hiking routes with osm2cai_status=2' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=2;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=2;",
        ],
        'count hiking routes with osm2cai_status=3' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=3;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=3;",
        ],
        'count hiking routes with osm2cai_status=4 / 1' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=4;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE osm2cai_status=4;",
        ],
        'count hiking routes with osm2cai_status=4 / 2' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE validation_date IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE validation_date IS NOT NULL;",
        ],
        'count hiking routes with osm2cai_status=4 / 3' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE user_id IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE validator_id IS NOT NULL;",
        ],

        // HIKING ROUTES ISSUE STATUS
        'count hiking routes with issues_status=precorribile' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='percorribile';",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='percorribile';",
        ],
        'count hiking routes with issues_status=non percorribile' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='non percorribile';",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='non percorribile';",
        ],
        'count hiking routes with issues_status=percorribile parzialmente' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='percorribile parzialmente';",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='percorribile parzialmente';",
        ],
        'count hiking routes with issues_status=sconosciuto' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='sconosciuto';",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_status='sconosciuto';",
        ],
        'count hiking routes with issue_description' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_description IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_description IS NOT NULL;",
        ],
        'count hiking routes with issues_last_update' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_last_update IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_last_update IS NOT NULL;",
        ],
        'count hiking routes with issues_user_id' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_user_id IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_user_id IS NOT NULL;",
        ],
        'count hiking routes with issues_chronology' => [
            'legacy' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_chronology IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num from hiking_routes WHERE issues_chronology IS NOT NULL;",
        ],

        // HIKING ROUTES FAVORITE: 
        'count hiking routes with region favorite=true' => [
            'legacy' => "SELECT COUNT(*) as num FROM hiking_routes WHERE region_favorite = true;",
            'current' => "SELECT COUNT(*) as num FROM hiking_routes WHERE region_favorite = true;",
        ],
        'count hiking routes with description_cai_it not null' => [
            'legacy' => "SELECT COUNT(*) as num FROM hiking_routes WHERE description_cai_it IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num FROM hiking_routes WHERE description_cai_it IS NOT NULL;",
        ],
        'count hiking routes with feature_image not null' => [
            'legacy' => "SELECT COUNT(*) as num FROM hiking_routes WHERE feature_image IS NOT NULL;",
            'current' => "SELECT COUNT(*) as num FROM hiking_routes WHERE feature_image IS NOT NULL;",
        ],

        // TODO: HR natural_springs, cai_huts, cached_mnitur_data, tdh




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
            $queryLegacy = 'All prepared queries';
            $queryCurrent = 'All prepared queries';
            $dataLegacy = [];
            $dataCurrent = [];
            foreach ($this::preparedQueries as $name => $preparedQuery) {
                $legacy = $preparedQuery['legacy'];
                $dataLegacy[$name] = $this->runOverLegacy($legacy);

                $current = $preparedQuery['current'];
                $dataCurrent[$name] = $this->runOverCurrent($current);

                // if ($dataCurrent[$name] !== $dataLegacy[$name]) {
                //     $message = 'Different result ' . strtoupper($name);
                // }
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
            if (isset($r[0]->num))
                $r = $r[0]->num;
        } catch (Exception $e) {
            $r = $e->getMessage();
        }
        DB::rollBack();

        return $r;
    }
}
