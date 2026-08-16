<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Models\Standard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the global Service Category + Standard master from the supplied business
 * service list. Idempotent: categories key on `code`, standards key on
 * (category, slug), and existing rows are never overwritten — so re-running never
 * duplicates and never clobbers user-edited descriptions.
 */
class StandardSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $order => $cat) {
            $category = ServiceCategory::query()->firstOrCreate(
                ['code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'short_name' => $cat['short_name'] ?? null,
                    'selection_label' => $cat['label'],
                    'item_noun' => $cat['noun'] ?? null,
                    'active' => true,
                    'display_order' => $order + 1,
                ]
            );

            foreach ($cat['standards'] as $i => $entry) {
                if (is_string($entry)) {
                    [$name, $code, $scope] = [$entry, null, null];
                } elseif (array_is_list($entry)) {
                    [$name, $code, $scope] = [$entry[0], $entry[1] ?? null, null];
                } else {
                    [$name, $code, $scope] = [$entry['name'], $entry['code'] ?? null, $entry['scope'] ?? null];
                }
                $slug = Str::slug($code ?: $name);

                Standard::query()->firstOrCreate(
                    ['service_category_id' => $category->id, 'slug' => $slug],
                    [
                        'name' => $name,
                        'code' => $code,
                        'short_name' => $code,
                        'type' => $cat['type'],
                        'default_scope' => $scope ? implode("\n", $scope) : null,
                        'active' => true,
                        'display_order' => $i + 1,
                    ]
                );
            }
        }
    }

    private function catalog(): array
    {
        return [
            [
                'code' => 'ISO_MGMT', 'name' => 'ISO Management System Certification',
                'short_name' => 'ISO Certification', 'label' => 'Select Standards', 'type' => 'ISO Standard',
                'standards' => [
                    ['ISO 9001 — Quality Management Systems', 'ISO 9001'],
                    ['ISO 14001 — Environmental Management Systems', 'ISO 14001'],
                    ['ISO 45001 — Occupational Health and Safety Management Systems', 'ISO 45001'],
                    ['ISO 22000 — Food Safety Management Systems', 'ISO 22000'],
                    ['ISO 50001 — Energy Management Systems', 'ISO 50001'],
                    ['ISO/IEC 27001 — Information Security Management Systems', 'ISO/IEC 27001'],
                    ['ISO/IEC 27701 — Privacy Information Management Systems', 'ISO/IEC 27701'],
                    ['ISO/IEC 20000-1 — IT Service Management Systems', 'ISO/IEC 20000-1'],
                    ['ISO/IEC 42001 — Artificial Intelligence Management Systems', 'ISO/IEC 42001'],
                    ['ISO 22301 — Business Continuity Management Systems', 'ISO 22301'],
                    ['ISO 37001 — Anti-Bribery Management Systems', 'ISO 37001'],
                    ['ISO 37301 — Compliance Management Systems', 'ISO 37301'],
                    ['ISO 46001 — Water Efficiency Management Systems', 'ISO 46001'],
                    ['ISO 13485 — Medical Devices Quality Management Systems', 'ISO 13485'],
                ],
            ],
            [
                'code' => 'SOCIAL_AUDIT', 'name' => 'Social and Ethical Compliance Audits',
                'label' => 'Select Audit Program', 'type' => 'Social Audit',
                'standards' => [
                    ['Sedex SMETA 2-Pillar Audit', 'SMETA 2-Pillar'],
                    ['Sedex SMETA 4-Pillar Audit', 'SMETA 4-Pillar'],
                    ['amfori BSCI Audit', 'BSCI'],
                    ['SLCP Assessment', 'SLCP'],
                    ['WRAP Certification Audit', 'WRAP'],
                    ['RBA Validated Assessment Program Audit', 'RBA VAP'],
                    ['Workplace Conditions Assessment', 'WCA'],
                    'Customer Code of Conduct Audit',
                    'Brand-Specific Social Compliance Audit',
                    'Social Compliance Gap Assessment',
                    'Labour Law Compliance Audit',
                    'Human Rights Due Diligence Assessment',
                    'Worker Wellbeing Assessment',
                ],
            ],
            [
                'code' => 'WORKPLACE_LABOUR', 'name' => 'Workplace Behaviour and Labour Management Services',
                'label' => 'Select Service', 'noun' => 'Service', 'type' => 'Assessment Program',
                'standards' => [
                    'Workplace Behaviour Assessment',
                    'Leadership and Organizational Culture Assessment',
                    'Workplace Harassment and Abuse Assessment',
                    'Grievance Management System Assessment',
                    'Disciplinary Management System Assessment',
                    'Worker Representation System Assessment',
                    'Participation Committee Effectiveness Assessment',
                    'Workplace Communication Assessment',
                    'Employee Relations Assessment',
                    'Employee Turnover and Separation Analysis',
                    'Absenteeism and Workforce Stability Analysis',
                    'Workplace Investigation',
                    'Employee Complaint Investigation',
                    'Root-Cause Analysis',
                    'Corrective Action Plan Development',
                    'Remediation Programme Development',
                    'Remediation Monitoring and Follow-Up Assessment',
                ],
            ],
            [
                'code' => 'TEXTILE_CERT', 'name' => 'Textile and Product Certification',
                'label' => 'Select Standards / Schemes', 'type' => 'Certification Scheme',
                'standards' => [
                    ['GOTS — Global Organic Textile Standard', 'GOTS'],
                    ['GRS — Global Recycled Standard', 'GRS'],
                    ['OCS — Organic Content Standard', 'OCS'],
                    ['RCS — Recycled Claim Standard', 'RCS'],
                    ['CCS — Content Claim Standard', 'CCS'],
                    ['RDS — Responsible Down Standard', 'RDS'],
                    ['RWS — Responsible Wool Standard', 'RWS'],
                    ['RMS — Responsible Mohair Standard', 'RMS'],
                    ['RAS — Responsible Alpaca Standard', 'RAS'],
                    ['GRTS — Global Recycled Textile Standard', 'GRTS'],
                    ['Better Cotton Chain of Custody Certification', 'Better Cotton'],
                    ['Cotton made in Africa Certification', 'CmiA'],
                    ['Fairtrade Textile Standard Certification', 'Fairtrade Textile'],
                    ['Fairtrade Cotton Certification', 'Fairtrade Cotton'],
                    ['regenagri Chain of Custody Certification', 'regenagri'],
                    ['ISCC PLUS Certification', 'ISCC PLUS'],
                    ['Cradle to Cradle Certified®', 'C2C'],
                    ['bluesign® System Certification', 'bluesign'],
                    ['EU Ecolabel Certification', 'EU Ecolabel'],
                    ['Nordic Swan Ecolabel Certification', 'Nordic Swan'],
                ],
            ],
            [
                'code' => 'OEKOTEX', 'name' => 'OEKO-TEX® Services',
                'label' => 'Select Standards', 'type' => 'Certification Scheme',
                'standards' => [
                    ['OEKO-TEX® STANDARD 100', 'STANDARD 100'],
                    ['OEKO-TEX® STeP', 'STeP'],
                    ['OEKO-TEX® MADE IN GREEN', 'MADE IN GREEN'],
                    ['OEKO-TEX® ORGANIC COTTON', 'ORGANIC COTTON'],
                    ['OEKO-TEX® LEATHER STANDARD', 'LEATHER STANDARD'],
                    ['OEKO-TEX® ECO PASSPORT', 'ECO PASSPORT'],
                    ['OEKO-TEX® RESPONSIBLE BUSINESS', 'RESPONSIBLE BUSINESS'],
                ],
            ],
            [
                'code' => 'FORESTRY_PAPER', 'name' => 'Forestry, Paper and Packaging Certification',
                'label' => 'Select Standards / Schemes', 'type' => 'Certification Scheme',
                'standards' => [
                    ['FSC® Chain of Custody Certification', 'FSC CoC'],
                    ['FSC® Controlled Wood Certification', 'FSC CW'],
                    ['PEFC Chain of Custody Certification', 'PEFC CoC'],
                    'Sustainable Packaging Certification',
                    'Recycled Packaging Content Certification',
                    'Biodegradable Product Certification',
                    ['OK compost INDUSTRIAL Certification', 'OK compost INDUSTRIAL'],
                    ['OK compost HOME Certification', 'OK compost HOME'],
                    'Product Packaging and Labelling Compliance Assessment',
                ],
            ],
            [
                'code' => 'LEATHER_FOOTWEAR', 'name' => 'Leather and Footwear Certification',
                'label' => 'Select Standards / Services', 'type' => 'Certification Scheme',
                'standards' => [
                    ['Leather Working Group Audit', 'LWG'],
                    ['Sustainable Leather Foundation Assessment', 'SLF'],
                    ['ICEC Leather Certification', 'ICEC'],
                    ['OEKO-TEX® LEATHER STANDARD', 'LEATHER STANDARD'],
                    'Footwear Social Compliance Audit',
                    'Footwear Chemical Compliance Assessment',
                    'Leather Traceability Assessment',
                    'Restricted Substances Compliance Assessment',
                ],
            ],
            [
                'code' => 'ENVIRO_SUSTAIN', 'name' => 'Environmental and Sustainability Services',
                'label' => 'Select Services / Packages', 'noun' => 'Package', 'type' => 'Environmental Service',
                'standards' => [
                    'Environmental Compliance Audit',
                    ['Environmental Impact Assessment (Single)', 'EIA'],
                    [
                        'name' => 'Environmental Impact Assessment', 'code' => null,
                        'scope' => [
                            'Ambient Air Quality Assessment',
                            'Stack Emission Test',
                            'Noise Level Assessment',
                            'Light Level Assessment',
                            'Temperature Assessment',
                            'Humidity Assessment',
                            'ODS Assessment / Inventory',
                        ],
                    ],
                    [
                        'name' => 'Environmental Parameter Testing', 'code' => null,
                        'scope' => [
                            'Ambient Air Quality Assessment',
                            'Stack Emission Test',
                            'Noise Level Assessment',
                            'Light Level Assessment',
                            'Temperature Assessment',
                            'Humidity Assessment',
                            'ODS Assessment / Inventory',
                        ],
                    ],
                    ['Initial Environmental Examination', 'IEE'],
                    ['Environmental and Social Impact Assessment', 'ESIA'],
                    ['Environmental Management Plan', 'EMP'],
                    'Environmental Clearance Support',
                    ['Higg FEM Verification', 'Higg FEM'],
                    ['amfori BEPI Assessment', 'BEPI'],
                    ['ZDHC Supplier to Zero Assessment', 'ZDHC Supplier to Zero'],
                    ['ZDHC Chemical to Zero Assessment', 'ZDHC Chemical to Zero'],
                    'Chemical Management System Assessment',
                    'Wastewater and ETP Performance Assessment',
                    'Water Footprint Assessment',
                    'Energy Audit',
                    'Carbon Footprint Assessment',
                    ['Greenhouse Gas Inventory', 'GHG Inventory'],
                    ['Greenhouse Gas Verification', 'GHG Verification'],
                    'Climate Risk Assessment',
                    'Resource Efficiency Assessment',
                    'Cleaner Production Assessment',
                    'Waste Management Assessment',
                    'Circularity Assessment',
                    ['Life-Cycle Assessment', 'LCA'],
                    ['ESG Assessment', 'ESG'],
                    'Sustainability Assessment',
                    'Sustainability Report Development',
                    'Environmental Parameter Testing Coordination',
                ],
            ],
            [
                'code' => 'CHEMICAL_MGMT', 'name' => 'Chemical Management Services',
                'label' => 'Select Service', 'noun' => 'Service', 'type' => 'Assessment Program',
                'standards' => [
                    'Chemical Management System Development',
                    'Chemical Risk Assessment',
                    'Chemical Inventory Assessment',
                    'Chemical Management Training',
                ],
            ],
            [
                'code' => 'OHS', 'name' => 'Occupational Health and Safety Services',
                'label' => 'Select Service', 'noun' => 'Service', 'type' => 'Safety Assessment',
                'standards' => [
                    'Occupational Health and Safety Audit',
                    'Workplace Risk Assessment',
                    'Fire Safety Assessment',
                    'Electrical Safety Assessment',
                    'Structural Safety Assessment',
                    'Machinery Safety Assessment',
                    'Heat-Stress Assessment',
                    'Ergonomic Risk Assessment',
                    'Accident and Incident Investigation',
                    'Occupational Health Programme Assessment',
                    'Safety Management System Development',
                    ['RSC Readiness and Remediation Support', 'RSC'],
                ],
            ],
            [
                'code' => 'SUPPLY_CHAIN_SECURITY', 'name' => 'Supply Chain and Security Audits',
                'label' => 'Select Audit / Assessment', 'type' => 'Inspection',
                'standards' => [
                    ['CTPAT Supply Chain Security Audit', 'CTPAT'],
                    ['SCAN Supply Chain Security Audit', 'SCAN'],
                    ['Global Security Verification', 'GSV'],
                    'Supply Chain Security Risk Assessment',
                    'Facility Security Assessment',
                    'Physical Security Assessment',
                    'Container and Cargo Security Assessment',
                    'Business Partner Security Assessment',
                    'Customs Compliance Assessment',
                    'Export Supply Chain Assessment',
                    'Product Traceability Assessment',
                    'Chain of Custody Assessment',
                    'Supplier Qualification Audit',
                    'Supplier Risk Assessment',
                ],
            ],
            [
                'code' => 'QUALITY_PRODUCTION', 'name' => 'Quality, Production and Technical Audits',
                'label' => 'Select Audit / Inspection', 'type' => 'Inspection',
                'standards' => [
                    'Quality Management System Audit',
                    'Production Process Audit',
                    'Technical Factory Audit',
                    'Supplier Evaluation Audit',
                    'Vendor Performance Assessment',
                    'Manufacturing Process Assessment',
                    'Production Efficiency Assessment',
                    'Lean Manufacturing Assessment',
                    '5S Workplace Assessment',
                    'Quality Improvement Assessment',
                    'Defect and Rejection Analysis',
                    'Root-Cause and Corrective Action Assessment',
                    'Product Traceability Assessment',
                    'Calibration System Assessment',
                    'Laboratory Management Assessment',
                    ['Pre-Production Inspection', 'PPI'],
                    ['During-Production Inspection', 'DUPRO'],
                    ['Final Random Inspection', 'FRI'],
                    ['Container Loading Supervision', 'CLS'],
                    'Product Quality Inspection',
                    'Product Testing Coordination',
                ],
            ],
        ];
    }
}
