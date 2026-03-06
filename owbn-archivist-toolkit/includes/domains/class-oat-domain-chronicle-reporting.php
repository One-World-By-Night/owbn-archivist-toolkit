<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Chronicle_Reporting implements OAT_Domain_Interface {

    /**
     * @return string
     */
    public function get_slug() {
        return 'chronicle_reporting';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Chronicle Reporting';
    }

    /**
     * Workflow: Staff → Archivist (auto). No coordinator step, no timer.
     *
     * @return array
     */
    public function get_workflow_template() {
        return array(
            array(
                'id'                 => 'submit',
                'label'              => 'Submission',
                'assignee_role'      => '',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => 'archivist',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'archivist',
                'label'              => 'Archivist Review',
                'assignee_role'      => 'Exec/Archivist/Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_ARCHIVIST,
                'on_approve'         => null,
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
        );
    }

    /**
     * @return array
     */
    public function get_meta_keys() {
        return array(
            'chronicle_slug' => array(
                'label'    => 'Chronicle Name',
                'type'     => 'text',
                'required' => true,
            ),
            'submitter_name' => array(
                'label'    => 'Submitted By',
                'type'     => 'text',
                'required' => true,
            ),
            'submitter_email' => array(
                'label'    => 'Submitted By Email',
                'type'     => 'text',
                'required' => true,
            ),
            'game_dates' => array(
                'label'    => 'Dates of games played',
                'type'     => 'textarea',
                'required' => true,
            ),
            'approx_attendance' => array(
                'label'    => 'Average Attendance Per Game',
                'type'     => 'number',
                'required' => false,
            ),
            'attendance_notes' => array(
                'label'    => 'Attendance Notes',
                'type'     => 'textarea',
                'required' => false,
            ),
            'major_plots' => array(
                'label'    => 'Major plots',
                'type'     => 'textarea',
                'required' => true,
            ),
            'ic_events_other_chronicles' => array(
                'label'    => 'IC events affecting other chronicles',
                'type'     => 'textarea',
                'required' => true,
            ),
            'ic_events_attention' => array(
                'label'    => 'IC events gaining attention',
                'type'     => 'textarea',
                'required' => false,
            ),
            'political_changes' => array(
                'label'    => 'Major Political Changes',
                'type'     => 'textarea',
                'required' => true,
            ),
            'masquerade_breaches' => array(
                'label'    => 'Masquerade/Veil breaches',
                'type'     => 'textarea',
                'required' => true,
            ),
            'fame_actions' => array(
                'label'    => 'Fame x5 level actions',
                'type'     => 'textarea',
                'required' => true,
            ),
            'coord_attention' => array(
                'label'    => 'Plots requiring Coordinator attention',
                'type'     => 'textarea',
                'required' => true,
            ),
            'prominent_deaths' => array(
                'label'    => 'Prominent character deaths/removals',
                'type'     => 'textarea',
                'required' => true,
            ),
            'other_matters' => array(
                'label'    => 'Other matters',
                'type'     => 'textarea',
                'required' => false,
            ),
            'staff_list' => array(
                'label'    => 'Staff Members',
                'type'     => 'textarea',
                'required' => false,
            ),
            'unusual_events' => array(
                'label'    => 'Strange and Unusual Events',
                'type'     => 'textarea',
                'required' => false,
            ),
            'inter_chronicle_events' => array(
                'label'    => 'Inter-Chronicle Interactions',
                'type'     => 'textarea',
                'required' => false,
            ),
            'disciplinary_actions' => array(
                'label'    => 'Disciplinary Actions Handed Out',
                'type'     => 'textarea',
                'required' => false,
            ),
            'characters_removed' => array(
                'label'    => 'Removal of Disciplinary Actions',
                'type'     => 'textarea',
                'required' => false,
            ),
            'ru_changes' => array(
                'label'    => 'Change of R&U Character(s) Status',
                'type'     => 'textarea',
                'required' => false,
            ),
        );
    }

    /**
     * Seed form fields into oat_form_fields table.
     *
     * @return int Number of fields inserted.
     */
    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        return OAT_Form_Field::seed( 'chronicle_reporting', array(
            // ── Submit context ───────────────────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'chronicle_slug',
                'field_type'      => 'chronicle_picker',
                'label'           => 'Chronicle Name',
                'required'        => 1,
                'sort_order'      => 10,
                'attributes_json' => wp_json_encode( array(
                    'roles'      => array( 'HST', 'Staff', 'CM' ),
                    'auto_props' => array(
                        'submitter_name'  => 'user_name',
                        'submitter_email' => 'user_email',
                    ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitted By',
                'required'        => 1,
                'sort_order'      => 20,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitted By Email',
                'required'        => 1,
                'sort_order'      => 30,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'game_dates',
                'field_type'      => 'textarea',
                'label'           => 'Dates of games played',
                'required'        => 1,
                'sort_order'      => 40,
                'help_text'       => 'Please list the dates that your chronicle had a game session ("Month Day, Year" format). List one game date per line.',
                'placeholder'     => 'January 1, 2026',
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'approx_attendance',
                'field_type'      => 'number',
                'label'           => 'Average Attendance Per Game',
                'sort_order'      => 45,
                'attributes_json' => wp_json_encode( array( 'min' => 0 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'attendance_notes',
                'field_type'      => 'textarea',
                'label'           => 'Attendance Notes',
                'sort_order'      => 47,
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'major_plots',
                'field_type'      => 'htmlarea',
                'label'           => 'Major plots that happened in the chronicle',
                'required'        => 1,
                'sort_order'      => 50,
                'help_text'       => 'Major plots are defined as plot that affected several players over more than two game sessions.',
                'attributes_json' => wp_json_encode( array( 'rows' => 8 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'ic_events_other_chronicles',
                'field_type'      => 'htmlarea',
                'label'           => 'In-character (IC) events that may affect other chronicles or territory',
                'required'        => 1,
                'sort_order'      => 60,
                'help_text'       => 'These are events that could impact in another member chronicle or coord/neutral territory in a meaningful way.',
                'attributes_json' => wp_json_encode( array( 'rows' => 8 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'ic_events_attention',
                'field_type'      => 'htmlarea',
                'label'           => 'In-character events that may gain Regional or National attention',
                'sort_order'      => 70,
                'help_text'       => 'Regional attention means events that may or have caused PCs, NPCs or mortal authorities in other regional games or nearby areas to take notice. National attention means events that may or have caused PCs, NPCs or mortal authorities outside of your region to take notice.',
                'attributes_json' => wp_json_encode( array( 'rows' => 8 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'political_changes',
                'field_type'      => 'htmlarea',
                'label'           => 'Major Political Changes within the Chronicle',
                'required'        => 1,
                'sort_order'      => 80,
                'help_text'       => 'Major political changes are defined as changes in important positions, ranks, leadership or other noticeable meaningful power shift (i.e. Princes, Harpies, Bishops, high ranked Garou, Chantry leaders, etc), preferably indicating the names being replaced.',
                'attributes_json' => wp_json_encode( array( 'rows' => 8 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'masquerade_breaches',
                'field_type'      => 'htmlarea',
                'label'           => 'Noticeable Masquerade/Veil breaches',
                'required'        => 1,
                'sort_order'      => 90,
                'help_text'       => 'Defined as any breach of the masquerade that reached news, especially online news.',
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'fame_actions',
                'field_type'      => 'htmlarea',
                'label'           => 'Fame x5 level actions',
                'required'        => 1,
                'sort_order'      => 100,
                'help_text'       => 'Defined as actions that would be noticed by the population.',
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'coord_attention',
                'field_type'      => 'htmlarea',
                'label'           => 'Plots that may require help or attention from Coordinators',
                'required'        => 1,
                'sort_order'      => 110,
                'help_text'       => 'Any plot the chronicle feel would benefit from such attention, serving as indication of willingness to work with said coordinator.',
                'attributes_json' => wp_json_encode( array( 'rows' => 8 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'prominent_deaths',
                'field_type'      => 'htmlarea',
                'label'           => 'Death or Administrative Removal of Prominent Characters within the Chronicle',
                'required'        => 1,
                'sort_order'      => 120,
                'help_text'       => 'Character deaths, NPC (Significant sect members, i.e Prince, Primogen, Archbishop, High Ranked Garou, etc or Kine, Mayor, Media personality or other significant person) or PC.',
                'attributes_json' => wp_json_encode( array( 'rows' => 8 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'other_matters',
                'field_type'      => 'htmlarea',
                'label'           => 'Other Matters the Reporter Feels are Relevant',
                'sort_order'      => 130,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'staff_list',
                'field_type'      => 'htmlarea',
                'label'           => 'Staff Members',
                'sort_order'      => 140,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'unusual_events',
                'field_type'      => 'htmlarea',
                'label'           => 'Strange and Unusual Events',
                'sort_order'      => 150,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'inter_chronicle_events',
                'field_type'      => 'htmlarea',
                'label'           => 'Inter-Chronicle Interactions',
                'sort_order'      => 160,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'disciplinary_actions',
                'field_type'      => 'htmlarea',
                'label'           => 'Disciplinary Actions Handed Out',
                'sort_order'      => 170,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'characters_removed',
                'field_type'      => 'htmlarea',
                'label'           => 'Removal of Disciplinary Actions',
                'sort_order'      => 180,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'ru_changes',
                'field_type'      => 'htmlarea',
                'label'           => 'Change of R&U Character(s) Status',
                'sort_order'      => 190,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),

            // ── Review context ───────────────────────────────────────────────
            array(
                'context'         => 'review',
                'field_key'       => 'section_review',
                'field_type'      => 'heading',
                'label'           => 'Review',
                'sort_order'      => 10,
            ),
            array(
                'context'         => 'review',
                'field_key'       => 'review_note',
                'field_type'      => 'textarea',
                'label'           => 'Review Comments',
                'sort_order'      => 20,
                'placeholder'     => 'Review comments...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Escalate context ─────────────────────────────────────────────
            array(
                'context'         => 'escalate',
                'field_key'       => 'section_escalate',
                'field_type'      => 'heading',
                'label'           => 'Escalation',
                'sort_order'      => 10,
            ),
            array(
                'context'         => 'escalate',
                'field_key'       => 'escalation_reason',
                'field_type'      => 'textarea',
                'label'           => 'Escalation Reason',
                'required'        => 1,
                'sort_order'      => 20,
                'placeholder'     => 'Reason for escalation...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Resolve context ──────────────────────────────────────────────
            array(
                'context'         => 'resolve',
                'field_key'       => 'section_resolve',
                'field_type'      => 'heading',
                'label'           => 'Resolution',
                'sort_order'      => 10,
            ),
            array(
                'context'         => 'resolve',
                'field_key'       => 'resolution_note',
                'field_type'      => 'textarea',
                'label'           => 'Resolution Notes',
                'sort_order'      => 20,
                'placeholder'     => 'Resolution notes...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),
            array(
                'context'         => 'resolve',
                'field_key'       => 'resolution_type',
                'field_type'      => 'select',
                'label'           => 'Resolution Type',
                'required'        => 1,
                'sort_order'      => 30,
                'options_json'    => wp_json_encode( array(
                    'approved' => 'Approved',
                    'denied'   => 'Denied',
                    'deferred' => 'Deferred',
                    'returned' => 'Returned',
                ) ),
            ),
        ) );
    }
    public function validate( $entry, $meta ) {
        if ( empty( $meta['chronicle_slug'] ) ) {
            return new WP_Error( 'missing_chronicle', 'Chronicle selection is required.' );
        }

        return true;
    }

    /**
     * @return string
     */
    public function get_archivist_mode() {
        return 'auto';
    }
}
