<?php
declare(strict_types=1);

namespace Avic\Domain;

/** The fixed vocabularies of the system, with display labels. */
final class Catalog
{
    public const LOSS_TYPES = [
        'collision' => 'Collision', 'theft' => 'Theft', 'fire' => 'Fire',
        'vandalism' => 'Vandalism', 'natural_disaster' => 'Natural disaster', 'other' => 'Other',
    ];
    public const REGIONS = [
        'central' => 'Central', 'eastern' => 'Eastern', 'northern' => 'Northern', 'western' => 'Western',
    ];
    public const EXPOSURE_TYPES = [
        'own_vehicle' => 'Own vehicle damage',
        'third_party_vehicle' => 'Third-party vehicle',
        'third_party_property' => 'Third-party property',
        'third_party_injury' => 'Third-party injury',
    ];
    public const DOC_TYPES = [
        'accident_photo' => 'Accident photo', 'police_report' => 'Police report',
        'repair_estimate' => 'Repair estimate', 'medical_report' => 'Medical report',
        'id_document' => 'ID document', 'other' => 'Other',
    ];
    public const PAYMENT_METHODS = [
        'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'mobile_money' => 'Mobile money',
    ];
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    public const STATUSES = [
        'open' => 'Open', 'investigating' => 'Investigating', 'settled' => 'Settled', 'closed' => 'Closed',
    ];
    public const OUTCOMES = ['paid' => 'Paid', 'denied' => 'Denied', 'withdrawn' => 'Withdrawn'];
    public const COVERAGE = ['third_party' => 'Third party', 'comprehensive' => 'Comprehensive'];

    /** @return list<array<string,string>> */
    public static function all(): array
    {
        return [
            self::STATUSES, self::LOSS_TYPES, self::REGIONS, self::EXPOSURE_TYPES, self::DOC_TYPES,
            self::PAYMENT_METHODS, self::PRIORITIES, self::OUTCOMES, self::COVERAGE,
        ];
    }
}
