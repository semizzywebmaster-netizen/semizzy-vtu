<?php

return [
    'categories' => [
        'school_admission' => [
            'label' => 'School Admission',
            'description' => 'Institution admission information, requirements, screening and admission-related services.',
            'institution_scoped' => true,
            'exam_body_scoped' => false,
        ],
        'school_past_questions' => [
            'label' => 'School Past Questions',
            'description' => 'Past questions and study materials belonging to schools and institutions.',
            'institution_scoped' => true,
            'exam_body_scoped' => false,
        ],
        'exam_past_questions' => [
            'label' => 'Exam Past Questions',
            'description' => 'Past questions and study materials belonging to examination bodies.',
            'institution_scoped' => false,
            'exam_body_scoped' => true,
        ],
        'exam_registration' => [
            'label' => 'Exam Registration',
            'description' => 'Registration and related services for examination bodies. Result checking remains in the Exams & Results addon.',
            'institution_scoped' => false,
            'exam_body_scoped' => true,
        ],
    ],
];
