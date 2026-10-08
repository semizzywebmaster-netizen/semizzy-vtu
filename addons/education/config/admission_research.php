<?php

return [
    /*
     * Institution research register.
     * Admission requirements must be derived from an institution's official
     * current admission material before they are entered into the database.
     * This register deliberately does NOT create generic requirements.
     */
    'research_version' => '2026-10-08',
    'rules' => [
        'source_priority' => [
            'official_admission_portal',
            'official_admission_requirements_document',
            'official_post_utme_or_de_notice',
            'official_faculty_or_department_document',
            'official_jamb_requirements',
            'regulatory_body_requirement',
        ],
        'require_session_versioning' => true,
        'require_source_url' => true,
        'require_source_date_or_publication_year' => true,
        'require_programme_specific_rule_when_published' => true,
        'never_assume_university_rule_from_another_institution' => true,
    ],

    'institutions_researched' => [
        'university_of_ibadan' => [
            'name' => 'University of Ibadan',
            'category' => 'university',
            'ownership' => 'federal',
            'country' => 'Nigeria',
            'official_sources' => [
                'admission_portal' => 'https://admissions.ui.edu.ng/',
                'requirements_document' => 'https://admissions.ui.edu.ng/Regulation.pdf',
            ],
            'observed_admission_dimensions' => [
                'utme',
                'direct_entry',
                'programme',
                'faculty',
                'utme_subject_combination',
                'o_level_subjects',
                'o_level_sittings',
                'special_consideration_waiver',
                'age',
                'post_utme_screening',
                'screening_fee',
                'direct_entry_qualification',
                'accepted_qualifications',
                'clearance_documents',
            ],
            'verified_examples' => [
                'medicine_and_pharmacy' => [
                    'five_o_level_credits_at_one_sitting_in_relevant_subjects',
                ],
                'other_programmes' => [
                    'two_o_level_results_may_require_six_relevant_credits',
                ],
                'direct_entry_accepted_families' => [
                    'waec',
                    'neco',
                    'cambridge_advanced_level',
                    'nce',
                    'ond',
                    'hnd',
                    'degree',
                ],
                'explicitly_noted' => [
                    'ijmb_not_accepted',
                    'jupeb_not_accepted',
                ],
            ],
            'do_not_generalize' => [
                'programme_rules_differ',
                'waiver_remarks_differ',
                'utme_subject_combinations_differ',
                'medicine_and_pharmacy_have_special_o_level_rule',
            ],
        ],

        'university_of_lagos' => [
            'name' => 'University of Lagos',
            'category' => 'university',
            'ownership' => 'federal',
            'country' => 'Nigeria',
            'official_sources' => [
                'admission_office' => 'https://admissions.unilag.edu.ng/',
                'general_notices' => 'https://admissions.unilag.edu.ng/notices.html',
                'programme_requirements' => 'https://admissions.unilag.edu.ng/admission_requirements.html',
                'programme_list' => 'https://admissions.unilag.edu.ng/programmes.html',
            ],
            'observed_admission_dimensions' => [
                'utme',
                'direct_entry',
                'programme',
                'o_level_subjects',
                'utme_score',
                'jamb_forwarded_candidate_data',
                'post_utme',
                'post_utme_score',
                'age',
                'international_certificate_verification',
                'department_specific_requirements',
                'jupeb_a_level_subjects',
                'nce_subjects',
            ],
            'verified_examples' => [
                'general_undergraduate' => [
                    'utme_or_direct_entry_pathway',
                    'five_o_level_credits_as_required_by_department',
                    'minimum_utme_score_200_in_published_general_notice',
                    'post_utme_required',
                    'minimum_age_16_by_30_september',
                ],
                'programme_specific' => [
                    'linguistics',
                    'philosophy',
                    'christian_religious_studies',
                    'islamic_studies',
                    'many_other_programmes',
                ],
            ],
            'do_not_generalize' => [
                'programme_subject_requirements_vary',
                'direct_entry_requirements_vary_by_programme',
                'published_general_rules_do_not_replace_programme_table',
            ],
        ],
    ],

    /*
     * Research dimensions that the future admission model must be capable
     * of representing. These are field families, not admission requirements.
     */
    'future_requirement_dimensions' => [
        'academic_session',
        'institution',
        'faculty',
        'department',
        'programme',
        'admission_route',
        'candidate_type',
        'minimum_age',
        'maximum_age',
        'jamb_utme_minimum_score',
        'o_level_minimum_credit_count',
        'o_level_max_sittings',
        'o_level_subject_rules',
        'utme_subject_combination',
        'direct_entry_qualification_rules',
        'accepted_exam_bodies',
        'accepted_qualifications',
        'post_utme_required',
        'post_utme_format',
        'post_utme_minimum_score',
        'screening_required',
        'screening_fee',
        'special_waiver_rules',
        'international_qualification_verification',
        'required_documents',
        'institution_specific_notes',
        'source_url',
        'source_title',
        'source_published_at',
        'effective_from',
        'effective_until',
        'verification_status',
    ],
];
