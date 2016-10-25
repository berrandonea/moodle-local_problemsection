<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Adds to the course a section where the teacher can submit a problem to groups of students
 * and give them various collaboration tools to work together on a solution.
 *
 * @package   local_problemsection
 * @copyright 2016 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : problemsection_form.php
 * Problem section edition form
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}
require_once($CFG->libdir.'/formslib.php');

class problemsection_form extends moodleform {
    public function definition() {
        global $OUTPUT;

        $mform =& $this->_form;

        $mform->addElement('header', 'generalhdr', get_string('general'));

        $mform->addElement('text', 'name', get_string('name'));
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('editor', 'summary', get_string('summary'));
        $mform->addHelpButton('summary', 'summary', 'local_problemsection');
        $mform->setType('summary', PARAM_RAW);

        $mform->addElement('editor', 'directions', get_string('directions', 'local_problemsection'));
        $mform->addHelpButton('directions', 'directions', 'local_problemsection');
        $mform->setType('directions', PARAM_RAW);
        $mform->addRule('directions', get_string('required'), 'required', null, 'client');

        $mform->addElement('date_time_selector', 'datefrom', get_string('allowsubmissionsfromdate', 'assign'));
        $mform->addElement('date_time_selector', 'dateto', get_string('duedate', 'assign'));

        $mform->addElement('header', 'communicationhdr', get_string('communicationtools', 'local_problemsection'));
        foreach ($this->_customdata['tools'] as $tool) {
            $picture = '<img src="'.$OUTPUT->pix_url('icon', "mod_$tool").'">';
            $mform->addElement('advcheckbox', $tool, get_string('pluginname', $tool), $picture);
        }

        $mform->addElement('header', 'groupinghdr', get_string('grouping', 'group'));

        $mform->addElement('select', 'copygrouping', get_string('copygrouping', 'local_problemsection'),
                            $this->_customdata['copygrouping']);

        $mform->addElement('text', 'nbgroups', get_string('nbgroups', 'local_problemsection'), array('size' => '2'));
        $mform->setType('nbgroups', PARAM_INT);
        $mform->setDefault('nbgroups', 1);
        $mform->disabledIf('nbgroups', 'copygrouping', 'neq', 0);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }
}
