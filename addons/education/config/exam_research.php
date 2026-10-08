<?php

return [
    /*
     * Research-first catalogue.
     *
     * This file records verified examination structures before registration
     * fields are generated. Requirements are intentionally data-driven so
     * future exam bodies/programmes can add their own rules without changing
     * the database schema.
     */
    'research_version' => '2026-10-08',
    'sources' => [
        'ican' => [
            'official' => 'https://icanig.org/ican/about.php',
            'atswa' => 'https://icanig.org/ican/students/atswa/index.php',
            'professional' => 'https://icanig.org/ican/students/professional/',
        ],
        'jamb' => [
            '2026_utme_de_bulletin' => 'https://www.jamb.gov.ng/Bulletin/2026/JAMBulletin_23-02-2026.pdf',
        ],
        'waec' => [
            'services' => 'https://waeconline.org.ng/?op=registration',
            'school_registration' => 'https://registration.waeconline.org.ng/',
        ],
        'neco' => [
            'examinations' => 'https://neco.gov.ng/',
            '2026_ssce_internal_guidelines' => 'https://neco.gov.ng/2026%20GUIDELINES.pdf',
            'ncee' => 'https://neco.gov.ng/exams/ncee',
        ],
        'nabteb' => [
            'registration' => 'https://novdec.nabteb.gov.ng/?TabId=36',
            'faq' => 'https://nabteb.gov.ng/about-nabteb/faqs/',
            'entry_guide' => 'https://nabteb.gov.ng/all-industries/registration-entry-guide/',
        ],
    ],

    /*
     * Structures discovered from official sources. These are research
     * records, not yet hard-coded registration forms.
     */
    'exam_structures' => [
        'ican' => [
            'programmes' => [
                'professional' => [
                    'name' => 'ICAN Professional Examinations',
                    'entry_qualification' => [
                        'degree_or_equivalent',
                        'higher_national_diploma',
                        'ican_aat_or_approved_equivalent',
                    ],
                    'qualification_rule' => 'University degree or equivalent / approved HND or other Council-approved qualification.',
                ],
                'atswa' => [
                    'name' => 'Accounting Technicians Scheme West Africa',
                    'parts' => 3,
                    'subjects_per_part' => 4,
                    'entry_qualification' => [
                        'five_o_level_credits_including_english_and_mathematics',
                        'ond_from_recognised_polytechnic',
                        'nce_from_college_of_education',
                    ],
                    'maximum_o_level_sittings' => 2,
                    'diets_per_year' => 2,
                    'known_diets' => ['March', 'September'],
                    'completion_duration_months' => ['minimum_standard' => 15, 'higher_certificate' => '6-12'],
                ],
            ],
            'field_families' => [
                'programme',
                'part_or_level',
                'subject',
                'entry_qualification',
                'o_level_sittings',
                'exemption_request',
                'diet',
                'exam_centre',
                'candidate_identity',
                'passport_photo',
            ],
        ],

        'jamb' => [
            'programmes' => [
                'utme' => [
                    'name' => 'Unified Tertiary Matriculation Examination',
                    'general_minimum_age_rule' => 'Not less than 16 by 30 September of the examination year, subject to published waiver rules.',
                    'pathway' => 'UTME',
                ],
                'direct_entry' => [
                    'name' => 'Direct Entry',
                    'accepted_qualification_families' => [
                        'a_level',
                        'national_diploma',
                        'higher_national_diploma',
                        'nce',
                        'ijmb',
                        'jupeb',
                        'cambridge_a_level',
                        'other_recognised_equivalent',
                    ],
                    'registration_data' => [
                        'previous_school_registration_or_matriculation_number',
                        'qualification_subjects',
                        'awarding_institution',
                        'affiliated_institution_when_applicable',
                        'year_of_graduation',
                    ],
                ],
            ],
            'field_families' => [
                'candidate_identity',
                'age_or_waiver',
                'programme_pathway',
                'institution_choices',
                'course_choice',
                'utme_subject_combination',
                'o_level_results',
                'direct_entry_qualification',
                'previous_institution',
                'qualification_details',
            ],
        ],

        'waec' => [
            'programmes' => [
                'wassce_school' => [
                    'name' => 'WASSCE for School Candidates',
                    'candidate_mode' => 'school_candidate',
                    'registration_model' => 'school_managed',
                    'biometrics' => true,
                    'passport_photo' => true,
                    'subject_selection' => true,
                ],
                'wassce_private' => [
                    'name' => 'WASSCE for Private Candidates',
                    'candidate_mode' => 'private_candidate',
                    'registration_model' => 'individual_online',
                    'subject_selection' => true,
                ],
            ],
            'field_families' => [
                'candidate_identity',
                'candidate_mode',
                'school',
                'subjects',
                'passport_photo',
                'biometrics',
                'contact_details',
                'examination_centre',
                'diet',
            ],
        ],

        'neco' => [
            'programmes' => [
                'ssce_internal' => [
                    'name' => 'Senior School Certificate Examination Internal',
                    'candidate_mode' => 'school_based',
                    'eligibility' => 'Final-year SS3 school candidates; not for private candidates.',
                    'nin_required' => true,
                    'nlin_assigned' => true,
                    'modes' => ['hybrid_cbe', 'pen_and_paper'],
                ],
                'ssce_external' => [
                    'name' => 'Senior School Certificate Examination External',
                    'candidate_mode' => 'external',
                    'registration_mode' => 'individual',
                ],
                'ncee' => [
                    'name' => 'National Common Entrance Examination',
                    'purpose' => 'Admission into JSS1 of Federal Government Unity Colleges.',
                    'candidate_stage' => 'final_year_primary',
                    'minimum_age_rule' => 'Not less than 10 by September of examination year.',
                    'guardian_registration' => true,
                    'centre_capacity_rule' => 250,
                ],
                'bece' => [
                    'name' => 'Basic Education Certificate Examination',
                    'candidate_stage' => 'completed_junior_secondary_education',
                ],
            ],
            'field_families' => [
                'candidate_identity',
                'candidate_stage',
                'school_or_guardian',
                'nin',
                'nlin',
                'subjects',
                'exam_mode',
                'centre',
                'programme',
            ],
        ],

        'nabteb' => [
            'programmes' => [
                'nbc' => ['name' => 'National Business Certificate'],
                'ntc' => ['name' => 'National Technical Certificate'],
                'anbc' => ['name' => 'Advanced National Business Certificate'],
                'antc' => ['name' => 'Advanced National Technical Certificate'],
                'mtce' => [
                    'name' => 'Modular Trades Certificate Examinations',
                    'purpose' => 'Certification for components of a trade.',
                ],
                'ncee' => [
                    'name' => 'National Common Entrance Examination',
                    'purpose' => 'Selection/admission into technical colleges.',
                ],
            ],
            'field_families' => [
                'programme',
                'trade',
                'trade_component',
                'general_education',
                'candidate_identity',
                'passport_photo',
                'biometrics',
                'school_or_registration_centre',
                'session',
                'candidate_type',
                'pin_or_registration_reference',
            ],
        ],
    ],

    /*
     * Generic field primitives to be used only where an official exam
     * structure requires them. Programme-specific rules live in data,
     * not in controller conditionals.
     */
    'field_primitives' => [
        'text',
        'date',
        'date_of_birth',
        'select',
        'multi_select',
        'number',
        'boolean',
        'file',
        'image',
        'country',
        'state',
        'lga',
        'institution',
        'qualification',
        'subject',
        'exam_centre',
        'biometric',
        'o_level_result',
        'previous_qualification',
        'waiver',
        'exemption',
    ],
];
