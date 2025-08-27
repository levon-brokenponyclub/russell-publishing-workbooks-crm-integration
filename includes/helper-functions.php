<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('workbooks_crm_get_personal_titles')) {
    function workbooks_crm_get_personal_titles() {
        // Workbooks CRM title values (from person_personal_title field)
        return [
            'Dr.' => 'Dr.',
            'Master' => 'Master', 
            'Miss' => 'Miss',
            'Mr.' => 'Mr',
            'Mrs.' => 'Mrs.',
            'Ms.' => 'Ms',
            'Prof.' => 'Prof.'
        ];
    }
}

if (!function_exists('workbooks_crm_get_dtr_areas_of_interest')) {
    function workbooks_crm_get_dtr_areas_of_interest() {
        // Replace with actual Workbooks API call to fetch picklist (e.g., picklist_id=161)
        return ['Technology', 'Finance', 'Healthcare', 'Education'];
    }
}

// Get all TOI options for admin display
if (!function_exists('dtr_get_all_toi_options')) {
    function dtr_get_all_toi_options() {
        return [
            'cf_person_business' => 'Business',
            'cf_person_diseases' => 'Diseases',
            'cf_person_drugs_therapies' => 'Drugs & Therapies',
            'cf_person_genomics_3774' => 'Genomics',
            'cf_person_research_development' => 'Research & Development',
            'cf_person_technology' => 'Technology',
            'cf_person_tools_techniques' => 'Tools & Techniques',
        ];
    }
}

// Get all AOI field names for admin display
if (!function_exists('dtr_get_aoi_field_names')) {
    function dtr_get_aoi_field_names() {
        return [
            'cf_person_analysis' => 'Analysis',
            'cf_person_assays' => 'Assays',
            'cf_person_biomarkers' => 'Biomarkers',
            'cf_person_clinical_trials' => 'Clinical trials',
            'cf_person_preclinical_research' => 'Preclinical Research',
            'cf_person_drug_discovery_processes' => 'Drug Discovery Processes',
            'cf_person_genomics' => 'Genomics',
            'cf_person_hit_to_lead' => 'Hit to Lead',
            'cf_person_imaging' => 'Imaging',
            'cf_person_toxicology' => 'Toxicology',
            'cf_person_artificial_intelligencemachine_learning' => 'Artificial Intelligence/Machine Learning',
            'cf_person_cell_gene_therapy' => 'Cell & Gene Therapy',
            'cf_person_informatics' => 'Informatics',
            'cf_person_lab_automation' => 'Lab Automation',
            'cf_person_molecular_diagnostics' => 'Molecular Diagnostics',
            'cf_person_personalised_medicine' => 'Personalised medicine',
            'cf_person_screening_sequencing' => 'Screening sequencing',
            'cf_person_stem_cells' => 'Stem cells',
            'cf_person_targets' => 'Targets',
            'cf_person_translational_science' => 'Translational science',
            'cf_person_protein_production' => 'Protein Production',
        ];
    }
}

// Main TOI to AOI mapping function
if (!function_exists('dtr_map_toi_to_aoi')) {
    function dtr_map_toi_to_aoi($selected_toi_fields) {
        if (empty($selected_toi_fields) || !is_array($selected_toi_fields)) {
            return [];
        }
        
        // Initialize all AOI fields to 0
        $aoi_fields = array_keys(dtr_get_aoi_field_names());
        $aoi_mapping = array_fill_keys($aoi_fields, 0);
        
        // Define the mapping matrix
        $toi_to_aoi_matrix = [
            'cf_person_business' => [
                'cf_person_analysis' => 1,
                'cf_person_biomarkers' => 1,
                'cf_person_clinical_trials' => 1,
                'cf_person_genomics' => 1,
                'cf_person_toxicology' => 1,
                'cf_person_artificial_intelligencemachine_learning' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_informatics' => 1,
                'cf_person_lab_automation' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_translational_science' => 1,
            ],
            'cf_person_diseases' => [
                'cf_person_biomarkers' => 1,
                'cf_person_genomics' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_stem_cells' => 1,
            ],
            'cf_person_drugs_therapies' => [
                'cf_person_biomarkers' => 1,
                'cf_person_clinical_trials' => 1,
                'cf_person_preclinical_research' => 1,
                'cf_person_drug_discovery_processes' => 1,
                'cf_person_hit_to_lead' => 1,
                'cf_person_toxicology' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_screening_sequencing' => 1,
                'cf_person_stem_cells' => 1,
                'cf_person_targets' => 1,
                'cf_person_translational_science' => 1,
            ],
            'cf_person_genomics_3774' => [
                'cf_person_biomarkers' => 1,
                'cf_person_genomics' => 1,
            ],
            'cf_person_research_development' => [
                'cf_person_analysis' => 1,
                'cf_person_assays' => 1,
                'cf_person_biomarkers' => 1,
                'cf_person_clinical_trials' => 1,
                'cf_person_preclinical_research' => 1,
                'cf_person_drug_discovery_processes' => 1,
                'cf_person_genomics' => 1,
                'cf_person_hit_to_lead' => 1,
                'cf_person_imaging' => 1,
                'cf_person_informatics' => 1,
                'cf_person_toxicology' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_screening_sequencing' => 1,
                'cf_person_stem_cells' => 1,
                'cf_person_targets' => 1,
                'cf_person_translational_science' => 1,
            ],
            'cf_person_technology' => [
                'cf_person_analysis' => 1,
                'cf_person_assays' => 1,
                'cf_person_imaging' => 1,
                'cf_person_artificial_intelligencemachine_learning' => 1,
                'cf_person_informatics' => 1,
                'cf_person_lab_automation' => 1,
                'cf_person_molecular_diagnostics' => 1,
            ],
            'cf_person_tools_techniques' => [
                'cf_person_analysis' => 1,
                'cf_person_assays' => 1,
                'cf_person_imaging' => 1,
                'cf_person_artificial_intelligencemachine_learning' => 1,
                'cf_person_informatics' => 1,
                'cf_person_lab_automation' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_screening_sequencing' => 1,
            ],
        ];
        
        // Apply mappings for selected TOI fields
        foreach ($selected_toi_fields as $toi_field) {
            if (isset($toi_to_aoi_matrix[$toi_field])) {
                foreach ($toi_to_aoi_matrix[$toi_field] as $aoi_field => $value) {
                    $aoi_mapping[$aoi_field] = $value;
                }
            }
        }
        
        return $aoi_mapping;
    }
}

// Convert Ninja Forms country codes to full names before submission
add_filter( 'ninja_forms_submit_data', function( $form_data ) {

    $target_form_id = 15; // Replace with your form ID
    $target_field_key = 'nf-field-148'; // Replace with your country field key

    if ( intval( $form_data['id'] ) !== $target_form_id ) {
        return $form_data;
    }

    foreach ( $form_data['fields'] as &$field ) {
        if ( isset( $field['key'] ) && $field['key'] === $target_field_key ) {
            // Convert ISO code to full country name
            $field['value'] = dtr_convert_country_code_to_name( $field['value'] );
        }
    }

    return $form_data;
});

?>