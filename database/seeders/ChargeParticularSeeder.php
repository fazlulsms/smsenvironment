<?php

namespace Database\Seeders;

use App\Models\ChargeParticular;
use Illuminate\Database\Seeder;

/**
 * Seeds the global Charge Particular library. Idempotent: keys on the canonical
 * `name`, so re-running never duplicates and never overwrites edited rows.
 * Near-duplicates are collapsed to one canonical name with the alternates kept as
 * search keywords (aliases). No prices — wording only.
 */
class ChargeParticularSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach ($this->catalog() as $category => $entries) {
            foreach ($entries as $entry) {
                [$name, $keywords] = is_array($entry) ? [$entry[0], $entry[1]] : [$entry, null];
                $order++;

                ChargeParticular::query()->firstOrCreate(
                    ['name' => $name],
                    [
                        'category' => $category,
                        'search_keywords' => $keywords,
                        'is_active' => true,
                        'sort_order' => $order,
                    ]
                );
            }
        }
    }

    private function catalog(): array
    {
        return [
            'General Audit / Assessment' => [
                'Full Initial Audit Fee',
                'Periodic Audit Fee',
                'Initial Audit Fee',
                'Initial Certification Audit Fee',
                'Certification Audit Fee',
                'Re-certification Audit Fee',
                'Surveillance Year-01 Audit Fee',
                'Surveillance Year-02 Audit Fee',
                'Follow-up Audit Fee',
                'Reassessment Fee',
                'Verification Fee',
                'Assessment Fee',
                'Gap Assessment Fee',
                'Gap Analysis Site Visit Fee',
                'Gap Analysis Documentation Review Fee',
                'Social Compliance & Sustainability Gap Assessment Fee',
            ],
            'Better Cotton' => [
                ['Better Cotton 3PV CoC Certification Fee', 'BCP better cotton platform chain of custody'],
                ['Better Cotton 3PV CoC Certification Fee — Including Onsite Audit, Administration and Travel Cost', 'BCP better cotton onsite audit administration travel'],
                ['Better Cotton Platform (BCP) Registration Support Fee', 'BCP better cotton platform registration'],
                ['Better Cotton Platform (BCP) Renewal Support Fee', 'BCP better cotton platform renewal'],
                ['Better Cotton CoC & BCP Platform Training Fee', 'BCP better cotton platform training'],
            ],
            'SLCP' => [
                ['SLCP Verification Fee (Step-01)', 'SLCP'],
                ['SLCP Verification Fee (Step-02)', 'SLCP'],
                ['SLCP Verification Fee (Step-03)', 'SLCP'],
                ['SLCP Joint Assessment Support Fee', 'SLCP joint assessment'],
                ['Support to SLCP Joint Assessment', 'SLCP joint assessment'],
            ],
            'Higg FEM' => [
                ['Higg FEM Verification Fee', 'Higg FEM'],
                ['Higg FEM Module Purchase Fee', 'Higg FEM module'],
            ],
            'Registration / License' => [
                'Registration Fee',
                'License Fee',
                ['Registration & License Fee', 'registration/license registration license'],
                'Enrollment Fee',
                'Certification License Fee',
                'Annual License Fee',
            ],
            'Administration / Operation' => [
                ['Administration Fee', 'admin administrative'],
                'Audit Initiation & Upload Fee',
                ['Verification Initiation & Upload Fee', 'SLCP Higg upload initiation'],
                ['Travel & Operational Cost', 'travel operational transport transportation'],
                ['Travel Cost', 'travel'],
                'Transportation Cost',
                'Operational Cost',
                'Onsite Visit Fee',
            ],
            'Documentation / Consultancy / Support' => [
                'Documentation Support Fee',
                'Consultancy & Support Fee',
                'Technical Support Fee',
                'Implementation Support Fee',
                'Certification Support Fee',
                'Compliance Support Fee',
                'Documentation Review Fee',
                'Document Development Support Fee',
                'ISO 14001:2015 Documentation Development Support Fee',
                'Development Support to Prepare ISO 14001:2015 Required Documents',
            ],
            'Training' => [
                'Training Delivery Fee',
                'Awareness Training Fee',
                'Staff Awareness Training Fee',
                ['ISO 9001:2015 Introduction Training Fee (01 Day)', 'training on introduction to iso 9001:2015 iso 9001 introduction'],
                'Chemical Management Training Fee',
            ],
            'Self-Assessment / Platform Support' => [
                'Self-Assessment Support Fee',
                'Platform Registration Support Fee',
                'Platform Renewal Support Fee',
                'Module Purchase Fee',
                'Application Support Fee',
                'Portal Upload Fee',
            ],
            'Sub-contract Unit' => [
                'Sub-contract Unit License & Visit Fee (01 Unit)',
                'Sub-contract Unit License Fee (01 Unit)',
                'Sub-contract Unit License Fee (02 Units)',
                'Sub-contract Unit License Fee (03 Units)',
                'Sub-contract Unit Visit Fee',
            ],
            'Workplace / Social Programmes' => [
                'Employee Satisfaction Survey Follow-up Fee',
                'Follow-up of Employee Satisfaction Survey on Harassment & Abuse at Workplace',
                'Workplace Harassment & Abuse Assessment Fee',
                'Workplace Harassment & Abuse Assessment Follow-up Fee',
                'Worker Interview & Assessment Fee',
                'Workplace Investigation Fee',
                'Grievance Management System Assessment Fee',
                'Remediation Programme Development Fee',
                'Remediation Monitoring Fee',
                'Follow-up Assessment Fee',
            ],
            'Environmental' => [
                'Environmental Assessment Fee',
                'Environmental Impact Assessment Fee',
                'Environmental Parameter Testing Fee',
                'Environmental Testing Fee',
                'Environmental Monitoring Fee',
                'Environmental Audit Fee',
                'Environmental Compliance Audit Fee',
                'Energy Audit Fee',
                'Carbon Footprint Assessment Fee',
                'GHG Inventory Assessment Fee',
                'GHG Verification Fee',
                'Wastewater & ETP Assessment Fee',
                'Chemical Management Assessment Fee',
            ],
        ];
    }
}
