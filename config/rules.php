<?php
declare(strict_types=1);

/**
 * Business rules as data. Change the system's behaviour here, not in the services.
 *
 * ASSIGNMENT — tried top to bottom; the first rule that finds an eligible adjuster wins,
 * and among eligible adjusters the one with the fewest open claims is chosen.
 *   'specialty' => 'loss_type'   adjuster.specialty must equal the claim's loss type
 *   'region'    => 'loss_region' adjuster.region must equal the claim's loss region
 * If no rule matches anyone the claim waits in the unassigned queue.
 *
 * ACTIVITIES — tasks the system creates when something happens.
 *   event     claim.opened | claim.reopened | claim.investigating | exposure.added
 *   when      optional filters: loss_type / exposure_type (list of values), flagged (bool)
 *   due_days  calendar days from now
 *   on_complete  transition => move an 'open' claim to that status
 *                clear_flags => clear the claim's flagged items
 */
return [
    'assignment' => [
        ['name' => 'Specialist in the loss region', 'match' => ['specialty' => 'loss_type', 'region' => 'loss_region']],
        ['name' => 'Any adjuster in the loss region', 'match' => ['region' => 'loss_region']],
        ['name' => 'Any adjuster', 'match' => []],
    ],

    'activities' => [
        [
            'key' => 'contact_claimant', 'event' => 'claim.opened',
            'subject' => 'Contact the claimant', 'description' => 'Acknowledge the claim and confirm the loss details.',
            'priority' => 'high', 'due_days' => 1, 'mandatory' => false,
        ],
        [
            'key' => 'review_flags', 'event' => 'claim.opened', 'when' => ['flagged' => true],
            'subject' => 'Review flagged items', 'description' => 'Coverage or duplicate questions were raised at intake. No payment can be issued until this is done.',
            'priority' => 'urgent', 'due_days' => 1, 'mandatory' => true,
            'on_complete' => ['clear_flags' => true],
        ],
        [
            'key' => 'verify_coverage', 'event' => 'claim.opened',
            'subject' => 'Verify policy coverage', 'description' => 'Confirm the policy was in force and covers this loss. Completing this starts the investigation.',
            'priority' => 'high', 'due_days' => 2, 'mandatory' => true,
            'on_complete' => ['transition' => 'investigating'],
        ],
        [
            'key' => 'obtain_police_report', 'event' => 'claim.opened', 'when' => ['loss_type' => ['theft', 'vandalism']],
            'subject' => 'Obtain the police report', 'description' => 'Theft and vandalism claims need a police reference and report.',
            'priority' => 'high', 'due_days' => 3, 'mandatory' => true,
        ],
        [
            'key' => 'inspect_vehicle', 'event' => 'claim.opened', 'when' => ['loss_type' => ['collision', 'fire', 'natural_disaster']],
            'subject' => 'Inspect the vehicle damage', 'description' => 'Arrange an inspection and photograph the damage.',
            'priority' => 'normal', 'due_days' => 3, 'mandatory' => false,
        ],
        [
            'key' => 'review_reopened', 'event' => 'claim.reopened',
            'subject' => 'Review the reopened claim', 'description' => 'Find out why the claim was reopened and decide what is outstanding.',
            'priority' => 'high', 'due_days' => 1, 'mandatory' => true,
            'on_complete' => ['transition' => 'investigating'],
        ],
        [
            'key' => 'investigation_report', 'event' => 'claim.investigating',
            'subject' => 'Write the investigation findings', 'description' => 'Record liability, evidence reviewed and the recommended settlement.',
            'priority' => 'normal', 'due_days' => 7, 'mandatory' => false,
        ],
        [
            'key' => 'repair_estimate', 'event' => 'exposure.added', 'when' => ['exposure_type' => ['own_vehicle', 'third_party_vehicle']],
            'subject' => 'Obtain a repair estimate', 'description' => 'Get a garage estimate and set the reserve for this exposure.',
            'priority' => 'normal', 'due_days' => 5, 'mandatory' => false,
        ],
        [
            'key' => 'medical_report', 'event' => 'exposure.added', 'when' => ['exposure_type' => ['third_party_injury']],
            'subject' => 'Obtain the medical report', 'description' => 'Request medical evidence for the injury exposure.',
            'priority' => 'high', 'due_days' => 5, 'mandatory' => true,
        ],
    ],
];
