<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->integer('gid')->nullable();
            $table->float('cod_rip')->nullable();
            $table->float('cod_reg')->nullable();
            $table->float('cod_prov')->nullable();
            $table->float('cod_cm')->nullable();
            $table->float('cod_uts')->nullable();
            $table->float('pro_com')->nullable();
            $table->string('pro_com_t', 6)->nullable();
            $table->string('comune_a', 100)->nullable();
            $table->float('cc_uts')->nullable();
            $table->decimal('shape_leng', 20, 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropColumn('gid');
            $table->dropColumn('cod_rip');
            $table->dropColumn('cod_reg');
            $table->dropColumn('cod_prov');
            $table->dropColumn('cod_cm');
            $table->dropColumn('cod_uts');
            $table->dropColumn('pro_com');
            $table->dropColumn('pro_com_t');
            $table->dropColumn('comune_a');
            $table->dropColumn('cc_uts');
            $table->dropColumn('shape_leng');
        });
    }
};
