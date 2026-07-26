<?php

/*
|--------------------------------------------------------------------------
| ATTP Bi-Annual Monitoring Questionnaire
|--------------------------------------------------------------------------
|
| This bundled definition is the deployment-safe default derived from the
| 2026-07-26 ATTP Monitoring Questionnaire workbook. It intentionally has no
| model or seeder dependency, so a future seeder can require this file and
| persist the normalized template through the application's own data layer.
|
*/

$sections = [
    [
        'part_number' => 1,
        'title' => 'Institutional Assessment',
        'topics' => [
            [
                'title' => 'Consortium Governance & Structure',
                'description' => 'Alignment of consortium setup with PPA, clarity of roles, governance framework, and decision-making structures',
                'guidance' => null,
                'questions' => [
                    'Is the consortium structure consistent with the PPA?',
                    'Are roles and responsibilities clearly defined?',
                    'Are governance arrangements documented and functional?',
                    'Is there a designated official (Executive Director) formally accountable to AUC?',
                    'Are subcontracting arrangements within the ≤20% threshold?',
                    'If subcontracting exceeds 15%, is there documented AUC approval and a capacity transfer plan?',
                ],
            ],
            [
                'title' => 'Coordination Mechanisms',
                'description' => 'Effectiveness of coordination across consortium members, including meetings, communication flows, and reporting lines',
                'guidance' => 'NB: TTPSC engagement to be assessed during subsequent monitoring visits following Year 1 review.',
                'questions' => [
                    'Are coordination mechanisms functioning effectively?',
                    'Are regular meetings held and documented?',
                    'Are reporting lines clear and followed?',
                    'Is coordination aligned with reporting obligations to AUC and TTPSC?',
                    'Are decisions documented and traceable to governance structures?',
                ],
            ],
            [
                'title' => 'Key Personnel Availability',
                'description' => 'Presence and engagement of key personnel as defined in the PPA',
                'guidance' => null,
                'questions' => [
                    'Are key personnel in place as per the PPA?',
                    'Are they actively engaged in implementation?',
                    'Are there any critical vacancies?',
                    'Do key personnel match those listed in the PPA (e.g., named experts)?',
                    'Is there continuity in staffing (low turnover in key roles)?',
                ],
            ],
            [
                'title' => 'Staffing Capacity',
                'description' => 'Adequacy of staffing across technical, operational, fiduciary, and support functions',
                'guidance' => null,
                'questions' => [
                    'Is staffing adequate across all functions?',
                    'Are there capacity gaps affecting delivery?',
                    'Are staff roles clearly defined?',
                    'Is there evidence of the Core Researcher Roster / Thematic Bench being operational?',
                    'Is there adequate capacity in specialized areas (e.g., CGE modeling, econometrics)?',
                ],
            ],
            [
                'title' => 'Project Management Systems',
                'description' => 'Use of planning tools, work plans, tracking systems, and implementation monitoring mechanisms',
                'guidance' => null,
                'questions' => [
                    'Are planning and tracking systems in place and used?',
                    'Are work plans regularly updated?',
                    'Is implementation progress tracked effectively?',
                    'Are implementation timelines aligned with the approved Gantt/phases?',
                    'Is the Think Tank using the approved Annual Work Plan and Budget (AWPB) submitted and approved by AUC and the World Bank?',
                    'Are activities strictly implemented based on the approved AWPB?',
                    'Has the Think Tank commenced working on the AWPB for FY2027?',
                ],
            ],
            [
                'title' => 'Partner Engagement',
                'description' => 'Level and quality of participation of consortium partners in implementation',
                'guidance' => null,
                'questions' => [
                    'Are partners actively contributing to implementation?',
                    'Are responsibilities clearly allocated?',
                    'Is there evidence of partner deliverables?',
                    'Is each Think Tank effectively accountable for all partners’ outputs and compliance?',
                    'Are subcontractors monitored and evaluated?',
                ],
            ],
        ],
    ],
    [
        'part_number' => 2,
        'title' => 'Procurement and Financial Management',
        'topics' => [
            [
                'title' => 'Procurement Systems',
                'description' => 'Compliance with World Bank procurement procedures, documentation, and transparency',
                'guidance' => null,
                'questions' => [
                    'Are procurement processes compliant with World Bank regulations?',
                    'Are procurement records complete and accurate?',
                    'Is there transparency in processes?',
                    'Are World Bank standard procurement documents/templates used?',
                    'Are vendors screened against World Bank/AU debarment lists?',
                    'Are procurement records retained (7-year requirement)?',
                ],
            ],
            [
                'title' => 'Procurement Planning & Execution',
                'description' => 'Alignment of procurement activities with AWPB and efficiency of execution',
                'guidance' => null,
                'questions' => [
                    'Is there an approved procurement plan aligned with the AWPB?',
                    'Are procurement activities executed on time?',
                    'Are procurement methods correctly applied (ICB, NCB, QCBS, RFQ, Direct Selection)?',
                    'Is prior approval obtained where required (e.g., Direct Selection)?',
                    'Are vendors and partners screened annually against AU and World Bank sanctions lists?',
                ],
            ],
            [
                'title' => 'Financial Management Systems',
                'description' => 'Quality of financial controls, reporting (IFRs), accounting systems, and compliance',
                'guidance' => null,
                'questions' => [
                    'Are financial systems in place and functioning?',
                    'Are IFRs prepared accurately and submitted quarterly on time?',
                    'Are financial records accurate and complete?',
                    'Is there a dedicated project bank account?',
                    'Are ineligible costs (e.g., VAT, debt service) excluded?',
                    'Are anti-fraud controls integrated into financial management systems?',
                    'Are staff aware of procedures for reporting Prohibited Practices?',
                ],
            ],
            [
                'title' => 'Budget Execution',
                'description' => 'Alignment of expenditures with approved budget and justification of variances',
                'guidance' => null,
                'questions' => [
                    'Does expenditure align with the approved AWPB?',
                    'Are budget variances explained and justified?',
                    'Are expenditures aligned with eligible cost categories?',
                    'Is the subcontracting ceiling (≤20%) respected financially?',
                    'Are project implementation timelines consistent with the overall project end date (August 2028)?',
                    'How is the Think Tank adjusting the FY2026 AWPB to account for delays in disbursement and procurement plan approvals (approximately 3 months)?',
                    'Has the Think Tank formally revised or reprogrammed the AWPB to reflect implementation delays?',
                    'Are delayed activities rescheduled with clear timelines and prioritization?',
                    'Has the revised AWPB been submitted to and cleared by AUC/World Bank where required?',
                ],
            ],
            [
                'title' => 'Payment & Fund Flow Management',
                'description' => 'Timeliness and accuracy of fund requests and disbursements',
                'guidance' => null,
                'questions' => [
                    'Are withdrawal/payment requests submitted correctly and on time?',
                    'Are funds used within approved timelines?',
                    'Are there any cases of ineligible expenditures identified and recovered?',
                    'Are debit notes settled within 45 days where applicable?',
                ],
            ],
            [
                'title' => 'Travel & Mission Compliance',
                'description' => 'Compliance with ICSC travel rules and documentation',
                'guidance' => null,
                'questions' => [
                    'Are DSA rates aligned with ICSC standards?',
                    'Are travel claims submitted within 10 working days?',
                    'Are travel classes compliant with policy?',
                ],
            ],
            [
                'title' => 'Audit & Compliance Readiness',
                'description' => 'Preparedness for AUC/World Bank audits and reviews',
                'guidance' => null,
                'questions' => [
                    'Is the project audit-ready at all times?',
                    'Are previous audit findings addressed?',
                ],
            ],
            [
                'title' => 'Performance-Based Financing & Disbursement',
                'description' => 'Alignment with funding milestones and performance-linked disbursement conditions',
                'guidance' => null,
                'questions' => [
                    'Are milestones linked to disbursements achieved?',
                    'Is funding utilization above 50% threshold?',
                    'Is performance above 75% target threshold?',
                    'Are disbursement milestones (e.g., Q1, Q4, Q6, Q10 deliverables) achieved as per schedule?',
                    'Is there documentation linking outputs to payment triggers?',
                ],
            ],
        ],
    ],
    [
        'part_number' => 3,
        'title' => 'Research Ethics',
        'topics' => [
            [
                'title' => 'Research Implementation',
                'description' => 'Progress, quality, and alignment of research activities with PPA thematic priorities',
                'guidance' => null,
                'questions' => [
                    'Are research activities aligned with PPA thematic priorities and approved research agenda?',
                    'Is implementation progressing as planned?',
                    'Are outputs of acceptable quality?',
                    'Are deliverables (papers, policy briefs, reports) produced as scheduled?',
                ],
            ],
            [
                'title' => 'Research Ethics Compliance',
                'description' => 'Existence of ethics approvals, adherence to protocols, and consent procedures',
                'guidance' => null,
                'questions' => [
                    'Are ethical approvals obtained where required?',
                    'Are research protocols followed?',
                    'Are consent procedures properly applied?',
                    'Are data governance and data protection practices in place?',
                ],
            ],
            [
                'title' => 'Intellectual Property & Open Access Compliance',
                'description' => 'Compliance with IP and knowledge-sharing provisions',
                'guidance' => null,
                'questions' => [
                    'Are outputs jointly attributed to AUC/World Bank/Think Tank?',
                    'Are outputs publicly accessible within required timelines?',
                    'Is there any unauthorized commercial use?',
                ],
            ],
        ],
    ],
    [
        'part_number' => 4,
        'title' => 'Environment and Social Safeguards',
        'topics' => [
            [
                'title' => 'Environmental & Social Safeguards (ESS)',
                'description' => 'Existence and implementation of environmental and social safeguards and risk mitigation measures related to research',
                'guidance' => null,
                'questions' => [
                    'Are ESS measures established and implemented?',
                    'Are risks identified and mitigated?',
                    'Are safeguards integrated into research activities?',
                    'Are SEA/SH prevention and response measures in place?',
                    'Are incidents reported within required timelines (24–48 hours)?',
                ],
            ],
            [
                'title' => 'Grievance Redress Mechanism (GRM)',
                'description' => 'Availability, accessibility, and functionality of grievance handling systems',
                'guidance' => null,
                'questions' => [
                    'Is a GRM in place?',
                    'Is it accessible to stakeholders?',
                    'Are grievances properly recorded and resolved?',
                    'Does the GRM include SEA/SH-sensitive procedures?',
                    'Are grievance records linked to resolution timelines?',
                ],
            ],
            [
                'title' => 'Gender and Inclusion Compliance',
                'description' => 'Compliance with gender participation and inclusion targets',
                'guidance' => null,
                'questions' => [
                    'Do research teams meet the minimum 50% female participation requirement?',
                    'Are there targeted activities promoting gender inclusion?',
                    'Is gender integrated into research design and outputs?',
                ],
            ],
        ],
    ],
    [
        'part_number' => 5,
        'title' => 'Monitoring, Evaluation, and Learning (MEAL)',
        'topics' => [
            [
                'title' => 'Monitoring & Evaluation Systems',
                'description' => 'Existence of a results framework, indicator tracking, reporting systems, and use of data for decision-making',
                'guidance' => null,
                'questions' => [
                    'Is there a functional results framework?',
                    'Are indicators regularly tracked?',
                    'Are M&E reports produced and used for decision-making?',
                    'Are KPIs tracked against PPA performance thresholds (e.g., 80%)?',
                    'Are Adaptive Action Plans prepared when performance drops below thresholds?',
                ],
            ],
            [
                'title' => 'Stakeholder Engagement',
                'description' => 'Engagement with policymakers, partners, and relevant stakeholders throughout implementation',
                'guidance' => null,
                'questions' => [
                    'Are stakeholders actively engaged?',
                    'Are consultations documented?',
                    'Are partnerships functioning effectively?',
                ],
            ],
            [
                'title' => 'Policy Uptake & Influence',
                'description' => 'Evidence that research outputs inform or influence policy decisions and dialogue',
                'guidance' => null,
                'questions' => [
                    'Is there evidence of policy uptake?',
                    'Are policymakers using research outputs?',
                    'Are policy dialogues conducted?',
                    'Is there evidence of contribution to continental or cross-border policy priorities?',
                ],
            ],
            [
                'title' => 'Communications & Visibility',
                'description' => 'Effectiveness of dissemination strategies and compliance with donor visibility requirements',
                'guidance' => null,
                'questions' => [
                    'Is there a communication and dissemination strategy?',
                    'Are outputs effectively shared?',
                    'Is donor visibility ensured?',
                    'Are outputs published under CC BY 4.0 open access license within 6 months?',
                    'Is attribution to AUC/World Bank consistently applied?',
                ],
            ],
        ],
    ],
    [
        'part_number' => 6,
        'title' => 'Sustainability',
        'topics' => [
            [
                'title' => 'Institutional Sustainability',
                'description' => 'Plans and mechanisms for sustaining outcomes, systems, and partnerships beyond the project',
                'guidance' => null,
                'questions' => [
                    'Are sustainability plans in place?',
                    'Are systems embedded within the institution?',
                    'Is there a resource mobilization strategy?',
                    'Is there a resource mobilization pipeline beyond ATTP funding?',
                    'Are systems (e.g., data governance unit) institutionalized?',
                ],
            ],
            [
                'title' => 'Facility & IT Systems',
                'description' => 'Adequacy of physical infrastructure and IT systems supporting implementation',
                'guidance' => null,
                'questions' => [
                    'Are facilities adequate for project implementation?',
                    'Are IT systems functional, secure, and backed up?',
                    'Are data systems supporting research and storage (data governance unit) functional?',
                ],
            ],
            [
                'title' => 'Documentation & Record Keeping',
                'description' => 'Completeness, organization, and accessibility of required project documentation',
                'guidance' => null,
                'questions' => [
                    'Are documents complete, well-organized, and accessible?',
                    'Are required records available for review?',
                    'Are records retained in line with 7-year requirement?',
                    'Are contracts, financial, and procurement documents audit-ready?',
                ],
            ],
            [
                'title' => 'Institutional Capacity Building',
                'description' => 'Implementation of capacity strengthening activities under ATTP',
                'guidance' => null,
                'questions' => [
                    'Are capacity-building activities implemented as planned?',
                    'Is there evidence of training, mentorship, or fellowships?',
                    'Are identified institutional capacity gaps being addressed?',
                    'Is ACBF support being utilized where applicable?',
                ],
            ],
            [
                'title' => 'Risk Management',
                'description' => 'Identification, monitoring, and mitigation of operational, fiduciary, and contextual risks',
                'guidance' => null,
                'questions' => [
                    'Are risks identified and documented?',
                    'Are mitigation measures in place?',
                    'Are risks regularly monitored and updated?',
                    'Are anti-fraud measures implemented?',
                    'Are suspected prohibited practices reported within 5 days?',
                ],
            ],
        ],
    ],
    [
        'part_number' => 7,
        'title' => 'Overall Assessment',
        'topics' => [
            [
                'title' => 'Overall Assessment',
                'description' => 'Consolidated assessment of overall think tank performance across institutional, fiduciary, technical, and policy dimensions',
                'guidance' => null,
                'questions' => [
                    'What is the overall performance of the Think Tank across all assessed areas?',
                    'Are there any critical risks or compliance issues that could affect implementation?',
                    'What are the top 3 strengths and top 3 weaknesses or gaps requiring immediate attention?',
                    'Is the Think Tank on track to meet PPA objectives and performance targets?',
                    'Are there any issues that may require corrective actions, sanctions, or escalation to AUC/World Bank?',
                    'What are the priority recommendations for the next monitoring period?',
                ],
            ],
        ],
    ],
];

$topicGlobalOrder = 0;
$questionGlobalOrder = 0;

foreach ($sections as $sectionIndex => &$section) {
    $section['key'] = sprintf('section-%02d', $sectionIndex + 1);
    $section['section_key'] = $section['key'];
    $section['order'] = $sectionIndex + 1;
    $section['sort_order'] = $sectionIndex + 1;

    foreach ($section['topics'] as $topicIndex => &$topic) {
        $topicGlobalOrder++;
        $topic['key'] = sprintf('topic-%02d', $topicGlobalOrder);
        $topic['topic_key'] = $topic['key'];
        $topic['order'] = $topicIndex + 1;
        $topic['sort_order'] = $topicIndex + 1;
        $topic['global_order'] = $topicGlobalOrder;

        foreach ($topic['questions'] as $questionIndex => $prompt) {
            $questionGlobalOrder++;
            $topic['questions'][$questionIndex] = [
                'key' => sprintf('question-%03d', $questionGlobalOrder),
                'question_key' => sprintf('question-%03d', $questionGlobalOrder),
                'order' => $questionIndex + 1,
                'sort_order' => $questionIndex + 1,
                'global_order' => $questionGlobalOrder,
                'prompt' => $prompt,
                'response_type' => 'scored_finding',
                'question_type' => 'scored_finding',
                'required' => false,
            ];
        }
    }
    unset($topic);
}
unset($section);

return [
    'key' => 'monitoring-evaluation-tool-per-think-tank',
    'code' => 'monitoring-evaluation-tool-per-think-tank',
    'title' => 'Monitoring Evaluation Tool (Per Think Tank)',
    'rating_scale' => [
        'key' => 'rating-scale-0-3',
        'minimum' => 0,
        'maximum' => 3,
        'options' => [
            [
                'value' => 0,
                'label' => 'Not Applicable',
                'description' => null,
                'order' => 1,
            ],
            [
                'value' => 1,
                'label' => 'Weak',
                'description' => 'major gaps / non-compliance',
                'order' => 2,
            ],
            [
                'value' => 2,
                'label' => 'Average',
                'description' => 'partial compliance, improvement needed',
                'order' => 3,
            ],
            [
                'value' => 3,
                'label' => 'Strong',
                'description' => 'fully compliant and effective',
                'order' => 4,
            ],
        ],
    ],
    'response_schema' => [
        'type' => 'scored_finding',
        'fields' => [
            'strength' => [
                'type' => 'long_text',
                'required' => false,
            ],
            'weakness' => [
                'type' => 'long_text',
                'required' => false,
            ],
            'rating_code' => [
                'type' => 'single_choice',
                'required' => false,
                'rating_scale' => 'rating-scale-0-3',
            ],
            'ranking_label' => [
                'type' => 'derived',
                'source' => 'rating_code',
            ],
        ],
    ],
    'sections' => $sections,
    'counts' => [
        'sections' => count($sections),
        'topics' => $topicGlobalOrder,
        'questions' => $questionGlobalOrder,
    ],
    'source' => [
        'type' => 'bundled_fixture',
        'workbook' => '2026-07-26_ATTP Monitoring Questionnaire.xlsx',
    ],
];
