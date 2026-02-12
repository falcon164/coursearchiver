<?php
// This file is part of Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class tool_coursearchiver_step1_form extends moodleform {

    /**
     * Define the search form.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        // ---------------------------
        // Search header
        // ---------------------------
        $mform->addElement('header', 'searchhdr', get_string('search'));

        $mform->addElement('html', '<div style="float:right;">');
        $mform->addElement(
            'html',
            '<a href="../../settings.php?section=tool_coursearchiver" target="_blank">' .
            '<i class="fa fa-gear"></i> ' .
            get_string('coursearchiver_settings', 'tool_coursearchiver') .
            '</a>'
        );
        $mform->addElement('html', '</div><div style="clear:both;"></div>');

        // ---------------------------
        // Course search fields
        // ---------------------------
        $mform->addElement('text', 'searches[id]', get_string('courseid', 'tool_coursearchiver'));
        $mform->setType('searches[id]', PARAM_TEXT);
        $mform->addRule('searches[id]', null, 'numeric', null, 'client');
        $mform->setDefault('searches[id]', '');

        $mform->addElement('text', 'searches[short]', get_string('courseshortname', 'tool_coursearchiver'));
        $mform->setType('searches[short]', PARAM_TEXT);
        $mform->setDefault('searches[short]', '');

        $mform->addElement('text', 'searches[full]', get_string('coursefullname', 'tool_coursearchiver'));
        $mform->setType('searches[full]', PARAM_TEXT);
        $mform->setDefault('searches[full]', '');

        $mform->addElement('text', 'searches[idnumber]', get_string('courseidnumber', 'tool_coursearchiver'));
        $mform->setType('searches[idnumber]', PARAM_TEXT);
        $mform->setDefault('searches[idnumber]', '');

        // ---------------------------
        // ✅ Course custom field: TERM
        // ---------------------------
        $options = ['' => get_string('choose')];

        $sql = "SELECT DISTINCT COALESCE(d.value, d.charvalue, d.shortcharvalue) AS val
                  FROM {customfield_field} f
                  JOIN {customfield_category} cc ON cc.id = f.categoryid
                  JOIN {customfield_data} d ON d.fieldid = f.id
                 WHERE cc.component = :component
                   AND cc.area      = :area
                   AND f.shortname  = :shortname
              ORDER BY val";

        $records = $DB->get_records_sql($sql, [
            'component' => 'core_course',
            'area'      => 'course',
            'shortname' => 'term'   // 🔴 course custom field shortname
        ]);

        foreach ($records as $r) {
            if (!empty($r->val)) {
                $options[$r->val] = $r->val;
            }
        }

        $mform->addElement(
            'select',
            'searches[term]',
            get_string('termfield', 'tool_coursearchiver'),
            $options
        );
        $mform->setType('searches[term]', PARAM_RAW);

        // ---------------------------
        // Ignore flags
        // ---------------------------
        $mform->addElement('checkbox', 'ignadmins', get_string('ignoreadmins', 'tool_coursearchiver'));
        $mform->addElement('checkbox', 'ignsiteroles', get_string('ignoresiteroles', 'tool_coursearchiver'));

        // ---------------------------
        // Date filters
        // ---------------------------
        $mform->addElement('header', 'timestarted', get_string('startend', 'tool_coursearchiver'));

        $startbeforegroup = [];
        $startbeforegroup[] =& $mform->createElement('date_selector', 'startbefore');
        $startbeforegroup[] =& $mform->createElement('checkbox', 'startbeforeenabled', '', get_string('enable'));
        $mform->addGroup($startbeforegroup, 'startbeforegroup', get_string('startbefore', 'tool_coursearchiver'), ' ', false);
        $mform->disabledIf('startbeforegroup', 'startbeforeenabled');

        $startaftergroup = [];
        $startaftergroup[] =& $mform->createElement('date_selector', 'startafter');
        $startaftergroup[] =& $mform->createElement('checkbox', 'startafterenabled', '', get_string('enable'));
        $mform->addGroup($startaftergroup, 'startaftergroup', get_string('startafter', 'tool_coursearchiver'), ' ', false);
        $mform->disabledIf('startaftergroup', 'startafterenabled');

        $endbeforegroup = [];
        $endbeforegroup[] =& $mform->createElement('date_selector', 'endbefore');
        $endbeforegroup[] =& $mform->createElement('checkbox', 'endbeforeenabled', '', get_string('enable'));
        $mform->addGroup($endbeforegroup, 'endbeforegroup', get_string('endbefore', 'tool_coursearchiver'), ' ', false);
        $mform->disabledIf('endbeforegroup', 'endbeforeenabled');

        $endaftergroup = [];
        $endaftergroup[] =& $mform->createElement('date_selector', 'endafter');
        $endaftergroup[] =& $mform->createElement('checkbox', 'endafterenabled', '', get_string('enable'));
        $mform->addGroup($endaftergroup, 'endaftergroup', get_string('endafter', 'tool_coursearchiver'), ' ', false);
        $mform->disabledIf('endaftergroup', 'endafterenabled');

        // ---------------------------
        // Action buttons
        // ---------------------------
        $this->add_action_buttons(false, get_string('search', 'tool_coursearchiver'));
        $this->add_action_buttons(false, get_string('optoutlist', 'tool_coursearchiver'));
        $this->add_action_buttons(false, get_string('savestatelist', 'tool_coursearchiver'));
        $this->add_action_buttons(false, get_string('archivelist', 'tool_coursearchiver'));
    }

    /**
     * Validation
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['searches'])) {
            $errors['searchhdr'] = get_string('erroremptysearch', 'tool_coursearchiver');
        }

        return $errors;
    }
}
