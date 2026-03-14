<?php

return [

    'id' => [
        'label' => 'No.',
        'modules' => ['proposal', 'research', 'publication', 'ipagrants', 'otherawards', 'studentawards'],
    ],

    'title' => [
        'label' => 'Title',
        'modules' => ['proposal', 'otherawards', 'studentawards'],
    ],

    'proj_title' => [
        'label' => 'Project Title',
        'modules' => ['research', 'extension'],
    ],

    'prog_title' => [
        'label' => 'Title of Research Program/Project/Work',
        'modules' => ['publication', 'presentation', 'patent', 'others'],
    ],

    'program' => [
        'label' => 'Program Title',
        'modules' => ['research', 'extension'],
    ],

    'paper_title' => [
        'label' => 'Title of Paper Presented',
        'modules' => ['presentation'],
    ],

    'patent_title' => [
        'label' => 'Title of Patent',
        'modules' => ['patent'],
    ],

    'output_title' => [
        'label' => 'Title of Output',
        'modules' => ['others'],
    ],

    'article_title' => [
        'label' => 'Article Title',
        'modules' => ['ipagrants'],
    ],

    'pub_title' => [
        'label' => 'Title of Publication',
        'modules' => ['publication'],
    ],

    'proj_lead' => [
        'label' => 'Project Leader (SN, FN MI.)',
        'modules' => ['proposal'],
    ],

    'proj_author' => [
        'label' => 'Project Leader (SN, FN, MI)',
        'modules' => ['extension', 'ipagrants'],
    ],

    'proj_staff' => [
        'label' => 'Project Staff (SN, FN MI.)',
        'modules' => ['proposal', 'research', 'extension', 'ipagrants'],
    ],

    'publication_author' => [
        'label' => 'Name of Author and Co-Authors',
        'modules' => ['publication'],
    ],

    'collaborators' => [
        'label' => 'Collaborators',
        'modules' => ['proposal', 'research', 'extension'],
    ],

    'delivery_unit' => [
        'label' => 'Delivery Unit',
        'modules' => ['research', 'publication', 'extension', 'presentation', 'patent', 'others', 'trainings', 'partnership'],
    ],

    'contributing_unit' => [
        'label' => 'Contributing Unit',
        'modules' => ['research', 'publication', 'extension', 'presentation', 'patent', 'others', 'trainings', 'partnership'],
    ],

    'cost' => [
        'label' => 'Project Cost',
        'modules' => ['proposal', 'research', 'extension'],
    ],

    'status' => [
        'label' => 'Status',
        'modules' => ['proposal', 'research', 'extension', 'ipagrants'],
    ],

    'support_link' => [
        'label' => 'Supporting Evidence/s',
        'modules' => ['research', 'extension', 'presentation', 'patent', 'others', 'trainings', 'partnership'],
    ],

    'support_utilization' => [
        'label' => 'Supporting Evidence(s)',
        'modules' => ['patent', 'others', 'trainings'],
    ],

    'support_link_pub' => [
        'label' => 'Supporting Evidence(s) - Proof of Publication',
        'modules' => ['publication'],
    ],

    'proof_of_Utilization' => [
        'label' => 'Supporting Evidence(s) - Proof of Utilization',
        'modules' => ['publication'],
    ],

    'fund_agency' => [
        'label' => 'Funding Agency',
        'modules' => ['proposal', 'research', 'extension'],
    ],

    'fund_type' => [
        'label' => 'Funding Type',
        'modules' => ['proposal', 'research', 'extension'],
    ],

    'fund_source' => [
        'label' => 'Fund Source',
        'modules' => ['research', 'extension', 'trainings'],
    ],

    'start_date' => [
        'label' => 'Start Date (DD/MM/YYYY)',
        'modules' => ['research', 'extension', 'trainings'],
    ],

    'end_date' => [
        'label' => 'End Date (DD/MM/YYYY)',
        'modules' => ['research', 'extension', 'trainings'],
    ],

    // More fields here...
        'research_category' => [
        'label' => 'Research Category',
        'modules' => ['proposal'],
    ],

    'implementing_unit' => [
        'label' => 'Implementing Unit',
        'modules' => ['proposal', 'extension'],
    ],

    'durations' => [
        'label' => 'Project Duration',
        'modules' => ['proposal', 'research'],
    ],

    'date_submitted' => [
        'label' => 'Date Submitted (DD/MM/YYYY)',
        'modules' => ['proposal'],
    ],

    'banner' => [
        'label' => 'Banner Program',
        'modules' => ['research', 'extension'],
    ],

    'agora' => [
        'label' => 'AGORA',
        'modules' => ['research', 'extension'],
    ],

    'name_journal' => [
        'label' => 'Name of Journal/Book/Conference Publication/Other Publication',
        'modules' => ['publication'],
    ],

    'name_publisher' => [
        'label' => 'Name of Publisher',
        'modules' => ['publication'],
    ],

    'type_of_publisher' => [
        'label' => 'Type of Publisher',
        'modules' => ['publication'],
    ],

    'location_publisher' => [
        'label' => 'Location of Publisher',
        'modules' => ['publication'],
    ],

    'name_editor' => [
        'label' => 'Name of Editor/s',
        'modules' => ['publication'],
    ],

    'volume_issue_no' => [
        'label' => 'Volume No. and Issue No.',
        'modules' => ['publication'],
    ],

    'doi_url' => [
        'label' => 'DOI or URL',
        'modules' => ['publication'],
    ],

    'isbn_issn' => [
        'label' => 'ISBN or ISSN',
        'modules' => ['publication'],
    ],

    'web_science' => [
        'label' => 'Web of Science (ISI)',
        'modules' => ['publication'],
    ],

    'elsevier_scopus' => [
        'label' => "Elsevier's Scopus",
        'modules' => ['publication'],
    ],

    'elsevier_science_direct' => [
        'label' => "Elsevier's ScienceDirect",
        'modules' => ['publication'],
    ],

    'pub_med' => [
        'label' => 'PubMed/MEDLINE',
        'modules' => ['publication'],
    ],

    'ched_recognized_journal' => [
        'label' => 'CHED Recognized Journal',
        'modules' => ['publication'],
    ],

    'reputable_db' => [
        'label' => 'Other Reputable Database',
        'modules' => ['publication'],
    ],

    'no_of__citation' => [
        'label' => 'Number of Citations',
        'modules' => ['publication'],
    ],

    'training_courses' => [
        'label' => 'Training Courses (non-degree and non-credit)',
        'modules' => ['extension', 'partnership'],
    ],

    'technical_advisor' => [
        'label' => 'Technical/Advisory Service for external clients',
        'modules' => ['extension'],
    ],

    'info_dissemenation' => [
        'label' => 'Information Dissemination/Communication through mass media',
        'modules' => ['extension'],
    ],

    'consultancy' => [
        'label' => 'Consultancy for external clients',
        'modules' => ['extension'],
    ],

    'community_outreach' => [
        'label' => 'Community Outreach',
        'modules' => ['extension', 'partnership'],
    ],

    'technology_transfer' => [
        'label' => 'Technology or Knowledge Transfer to target users',
        'modules' => ['extension'],
    ],

    'organizing' => [
        'label' => 'Organizing such as symposium, forum, exhibit, performance, conference',
        'modules' => ['extension'],
    ],

    'academic' => [
        'label' => 'Academic Degree Programs of the Unit  beneficiary(s)',
        'modules' => ['extension'],
    ],

    'scopework' => [
        'label' => 'Scope of Work',
        'modules' => ['extension'],
    ],

    'duration_month' => [
        'label' => 'Project Duration (months)',
        'modules' => ['extension'],
    ],

    'duration_days' => [
        'label' => 'Project Duration (in days)',
        'modules' => ['extension'],
    ],

    'beneficiary' => [
        'label' => 'Target Beneficiary Group',
        'modules' => ['extension'],
    ],

    'no_beneficiary' => [
        'label' => 'Number of Target Beneficiary Groups/Persons Served',
        'modules' => ['extension'],
    ],

    'remarks' => [
        'label' => 'Remarks',
        'modules' => ['extension'],
    ],

    'publication_year' => [
        'label' => 'Publication Year',
        'modules' => ['ipagrants'],
    ],

    'awarded_by' => [
        'label' => 'Awarded by',
        'modules' => ['ipagrants'],
    ],

    'journal_title' => [
        'label' => 'Journal/Book Title',
        'modules' => ['ipagrants'],
    ],

    'amount_for_institute' => [
        'label' => 'Amount for the Institute',
        'modules' => ['ipagrants'],
    ],
    'year_awarded' => [
        'label' => 'Year',
        'modules' => ['otherawards', 'studentawards'],
    ],

    'name_of_holder' => [
        'label' => 'Name of Holder',
        'modules' => ['otherawards', 'studentawards'],
    ],

    'department' => [
        'label' => 'Dept/ Ins.',
        'modules' => ['otherawards'],
    ],

    'date_awarded' => [
        'label' => 'Date',
        'modules' => ['otherawards', 'studentawards'],
    ],

    'type_category' => [
        'label' => 'Type',
        'modules' => ['proposal', 'research', 'extension', 'publication', 'ipagrants', 'trainings', 'otherawards'],
    ],

    'paper_title' => [
        'label' => 'Title of Paper Presented',
        'modules' => ['presentation'],
    ],

    'presentation_type' => [
        'label' => 'Type of Presentation',
        'modules' => ['presentation'],
    ],

    'title_of_conference' => [
        'label' => 'Title of Conference',
        'modules' => ['presentation'],
    ],

    'name_organizer' => [
        'label' => 'Name of Organizer',
        'modules' => ['presentation', 'others'],
    ],

    'location_organizer' => [
        'label' => 'Location of Organizer',
        'modules' => ['presentation'],
    ],

    'full_address' => [
        'label' => 'Venue, City and Country',
        'modules' => ['presentation', 'others'],
    ],

    'conf_start_date' => [
        'label' => 'Conference Start Date (DD/MM/YYYY)',
        'modules' => ['presentation'],
    ],

    'conf_end_date' => [
        'label' => 'Conference End Date (DD/MM/YYYY)',
        'modules' => ['presentation'],
    ],

    'date_presentation' => [
        'label' => 'Date of Presentation (DD/MM/YYYY)',
        'modules' => ['presentation'],
    ],

    'patent_type' => [
        'label' => 'Type of Patent',
        'modules' => ['patent'],
    ],

    'application_no' => [
        'label' => 'Application Number',
        'modules' => ['patent'],
    ],

    'name_inventor' => [
        'label' => 'Name of Inventor(s)',
        'modules' => ['patent'],
    ],

    'name_applicant' => [
        'label' => 'Name of Applicant/Owner(s)',
        'modules' => ['patent'],
    ],

    'date_unexamined' => [
        'label' => 'Date of Publication of Unexamined Application (DD/MM/YYYY)',
        'modules' => ['patent'],
    ],

    'date_grantpatent' => [
        'label' => 'Date of Grant of Patent (DD/MM/YYYY)',
        'modules' => ['patent'],
    ],
    'registration_no' => [
        'label' => 'Registration Number',
        'modules' => ['patent'],
    ],

    'name_commercial_product' => [
        'label' => 'Name of Commercial Product',
        'modules' => ['patent'],
    ],

    'utilization_output' => [
        'label' => 'Utilization of Research Output',
        'modules' => ['patent', 'others'],
    ],

    'output_title' => [
        'label' => 'Title of Output',
        'modules' => ['others'],
    ],

    'type_output' => [
        'label' => 'Type of Output',
        'modules' => ['others'],
    ],

    'type_public_event' => [
        'label' => 'Type of Public Event',
        'modules' => ['others'],
    ],

    'title_event' => [
        'label' => 'Title of Event',
        'modules' => ['others'],
    ],

    'name_organizer' => [
        'label' => 'Name of Organizer/Curator/Producer/Publisher',
        'modules' => ['others'],
    ],

    'location_event' => [
        'label' => 'Location of Event',
        'modules' => ['others'],
    ],

    'location_event_venue' => [
        'label' => 'Event Venue, City and Country',
        'modules' => ['others'],
    ],

    'event_start_date' => [
        'label' => 'Event Start Date (DD/MM/YYYY)',
        'modules' => ['others'],
    ],

    'event_end_date' => [
        'label' => 'Event End Date (DD/MM/YYYY)',
        'modules' => ['others'],
    ],

    'date_output' => [
        'label' => 'Date the Output was first shown or released to the public (DD/MM/YYYY)',
        'modules' => ['others'],
    ],

    'location_venue' => [
        'label' => 'Venue, City/Municipality and Province',
        'modules' => ['trainings'],
    ],

    'notes' => [
        'label' => 'Special Notes about the Schedule',
        'modules' => ['trainings'],
    ],

    'no_hours' => [
        'label' => 'K8. Number of Hours Required to Complete the ing Course',
        'modules' => ['trainings'],
    ],

    'total_trainee' => [
        'label' => 'Total Number of ees/Persons Served',
        'modules' => ['trainings'],
    ],

    'sample_size' => [
        'label' => 'Sample Size',
        'modules' => ['trainings'],
    ],

    'survey' => [
        'label' => 'Satisfaction Survey (Number of Responses)',
        'modules' => ['trainings'],
    ],

    'partnership_type' => [
        'label' => 'Type of Extension Activities under Partnership',
        'modules' => ['partnership'],
    ],

    'tech_advisor_external' => [
        'label' => 'Technical/Advisory Service for external clients',
        'modules' => ['partnership'],
    ],

    'info_dissemination' => [
        'label' => 'Information Dissemination/Communication through mass media',
        'modules' => ['partnership'],
    ],

    'consultancy_external' => [
        'label' => 'Consultancy for external clients',
        'modules' => ['partnership'],
    ],

    'tech_transfer' => [
        'label' => 'Technology or Knowledge Transfer to target users',
        'modules' => ['partnership'],
    ],

    'organizing_sympetc' => [
        'label' => 'Organizing such as symposium, forum, exhibit, performance, conference',
        'modules' => ['partnership'],
    ],

    'scope_of_work' => [
        'label' => 'Scope of Work on the part of UP based on the Partnership Agreement',
        'modules' => ['partnership'],
    ],

    'partner_stakeholder' => [
        'label' => 'Name of Partner Stakeholder',
        'modules' => ['partnership'],
    ],

    'stake_holder_category' => [
        'label' => 'Stakeholder Category',
        'modules' => ['partnership'],
    ],

    'type_agreement' => [
        'label' => 'Type of Partnership Agreement',
        'modules' => ['partnership'],
    ],

    'Agreement_start_date' => [
        'label' => 'Partnership Agreement Effectivity Start Date (DD/MM/YYYY)',
        'modules' => ['partnership'],
    ],

    'Agreement_end_date' => [
        'label' => 'Partnership Agreement Effectivity End Date (DD/MM/YYYY)',
        'modules' => ['partnership'],
    ],



];
