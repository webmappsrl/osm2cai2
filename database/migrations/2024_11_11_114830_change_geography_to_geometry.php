<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all tables
        $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', ['public']);

        foreach ($tables as $table) {
            // Check if table has geometry column
            $hasGeometryColumn = DB::select(
                "
                SELECT column_name, udt_name 
                FROM information_schema.columns 
                WHERE table_name = ? 
                AND udt_name = 'geography'",
                [$table->table_name]
            );

            if (! empty($hasGeometryColumn)) {
                foreach ($hasGeometryColumn as $column) {
                    // Get the geometry type
                    $geometryType = DB::select(
                        '
                        SELECT type 
                        FROM geography_columns 
                        WHERE f_table_name = ? 
                        AND f_geography_column = ?',
                        [$table->table_name, $column->column_name]
                    )[0]->type;

                    // Alter column type from geography to geometry
                    DB::statement("
                        ALTER TABLE {$table->table_name} 
                        ALTER COLUMN {$column->column_name} 
                        TYPE geometry({$geometryType}, 4326) 
                        USING {$column->column_name}::geometry
                    ");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get all tables
        $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', ['public']);

        foreach ($tables as $table) {
            // Check if table has geometry column
            $hasGeometryColumn = DB::select(
                "
                SELECT column_name, udt_name 
                FROM information_schema.columns 
                WHERE table_name = ? 
                AND udt_name = 'geometry'",
                [$table->table_name]
            );

            if (! empty($hasGeometryColumn)) {
                foreach ($hasGeometryColumn as $column) {
                    // Get the geometry type
                    $geometryType = DB::select(
                        '
                        SELECT type 
                        FROM geometry_columns 
                        WHERE f_table_name = ? 
                        AND f_geometry_column = ?',
                        [$table->table_name, $column->column_name]
                    )[0]->type;

                    // Alter column type from geometry back to geography
                    DB::statement("
                        ALTER TABLE {$table->table_name} 
                        ALTER COLUMN {$column->column_name} 
                        TYPE geography({$geometryType}, 4326) 
                        USING {$column->column_name}::geography
                    ");
                }
            }
        }
    }
};
