<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaiHut extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'second_name', 'description', 'elevation', 'owner', 'geometry', 'type', 'type_custodial', 'company_management_property', 'addr_street',
        'addr_housenumber', 'addr_postcode', 'addr_city', 'ref_vatin', 'phone', 'fax', 'email', 'email_pec', 'website', 'facebook_contact', 'municipality_geo', 'province_geo',
        'site_geo', 'opening', 'acqua_in_rifugio_serviced', 'acqua_calda_service', 'acqua_esterno_service', 'posti_letto_invernali_service', 'posti_totali_service', 'ristorante_service',
        'activities', 'necessary_equipment', 'rates', 'payment_credit_cards', 'accessibilitá_ai_disabili_service', 'gallery', 'rule', 'map'
    ];
}
