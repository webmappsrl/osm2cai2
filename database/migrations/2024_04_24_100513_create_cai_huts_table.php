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
        Schema::create('cai_huts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('unico_id')->nullable();
            $table->string('name');
            $table->string('second_name')->nullable();
            $table->text('description')->nullable();
            $table->integer('elevation')->nullable();
            $table->string('owner')->nullable();
            $table->geography('geometry', 'point', 4326);
            $table->string('type')->nullable();
            $table->string('type_custodial')->nullable();
            $table->string('company_management_property')->nullable();
            $table->string('addr_street')->nullable();
            $table->string('addr_housenumber')->nullable();
            $table->string('addr_postcode')->nullable();
            $table->string('addr_city')->nullable();
            $table->string('ref_vatin')->nullable();
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('email_pec')->nullable();
            $table->string('website')->nullable();
            $table->string('facebook_contact')->nullable();
            $table->string('municipality_geo')->nullable();
            $table->string('province_geo')->nullable();
            $table->string('site_geo')->nullable();
            $table->string('opening')->nullable();
            $table->string('acqua_in_rifugio_serviced')->nullable();
            $table->string('acqua_calda_service')->nullable();
            $table->string('acqua_esterno_service')->nullable();
            $table->string('posti_letto_invernali_service')->nullable();
            $table->string('posti_totali_service')->nullable();
            $table->string('ristorante_service')->nullable();
            $table->string('activities')->nullable();
            $table->string('necessary_equipment')->nullable();
            $table->string('rates')->nullable();
            $table->string('payment_credit_cards')->nullable();
            $table->string('accessibilitá_ai_disabili_service')->nullable();
            $table->string('gallery')->nullable();
            $table->string('rule')->nullable();
            $table->string('map')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cai_huts');
    }
};
