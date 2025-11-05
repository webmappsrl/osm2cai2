<?php

namespace App\Enums;

enum UserRole: string
{
    //TODO: translate all the roles
    case Administrator = 'Administrator';
    case NationalReferent = 'National Referent';
    case RegionalReferent = 'Regional Referent';
    case LocalReferent = 'Local Referent';
    case ClubManager = 'Club Manager';
    case ItineraryManager = 'Itinerary Manager';
    case Guest = 'Guest';
    case Contributor = 'Contributor';
    case Editor = 'Editor';
    case Author = 'Author';
    case Validator = 'Validator';

    public static function referentRoles(): array
    {
        return [
            UserRole::NationalReferent,
            UserRole::RegionalReferent,
            UserRole::LocalReferent,
        ];
    }

    public static function adminAndReferentRoles(): array
    {
        return [
            UserRole::Administrator,
            ...UserRole::referentRoles(),
        ];
    }

    public static function higherRoles(): array
    {
        return [
            UserRole::Administrator,
            UserRole::NationalReferent,
            UserRole::RegionalReferent,
        ];
    }

}
