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
 * File : selector.php
 * Class for a user selector showing the students who're not members of any of this problem section's groups.
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->dirroot.'/user/selector/lib.php');

class local_problemsection_members_selector extends group_members_selector {
    /**
     * Output this user_selector as HTML.
     *
     * @param boolean $return if true, return the HTML as a string instead of outputting it.
     * @return mixed if $return is true, returns the HTML as a string, otherwise returns nothing.
     */
    public function display($return = false) {
        // Get the list of requested users.
        $search = optional_param($this->name . '_searchtext', '', PARAM_RAW);
        if (optional_param($this->name . '_clearbutton', false, PARAM_BOOL)) {
            $search = '';
        }
        $groupedusers = $this->find_users($search);

        // Output the select.
        $name = $this->name;
        $multiselect = '';
        if ($this->multiselect) {
            $name .= '[]';
            $multiselect = 'multiple="multiple" ';
        }
        $output = '<div class="userselector" id="' . $this->name . '_wrapper">' . "\n" .
                '<select name="' . $name . '" id="' . $this->name . '" ' .
                $multiselect . 'size="' . $this->rows . '">' . "\n";

        // Populate the select.
        $output .= $this->output_options($groupedusers, $search);

        // Output the search controls.
        $output .= "</select>\n<div>\n";
        $output .= '<input type="text" name="' . $this->name . '_searchtext" id="' .
                $this->name . '_searchtext" size="15" value="' . s($search) . '" />';
        $output .= '<input type="submit" name="' . $this->name . '_searchbutton" id="' .
                $this->name . '_searchbutton" value="' . $this->search_button_caption() . '" />';
        $output .= '<input type="submit" name="' . $this->name . '_clearbutton" id="' .
                $this->name . '_clearbutton" value="' . get_string('clear') . '" />';

        $output .= $this->initialise_javascript($search);

        // Return or output it.
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }
}

class local_problemsection_nonmembers_selector extends group_non_members_selector {
    /** @var int */
    protected $groupingid;

    /**
     * Constructor.
     *
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options) {
        parent::__construct($name, $options);
        $this->groupingid = $options['groupingid'];
    }

    /**
     * Output user.
     *
     * @param stdClass $user
     * @return string
     */
    public function output_user($user) {
        return user_selector_base::output_user($user);
    }

    /**
     * Finds users to display in this control.
     *
     * @param string $search
     * @return array
     */
    public function find_users($search, $grouping = null) {
        global $DB;

        // Get list of allowed roles.
        $context = context_course::instance($this->courseid);
        if ($validroleids = groups_get_possible_roles($context)) {
            list($roleids, $roleparams) = $DB->get_in_or_equal($validroleids, SQL_PARAMS_NAMED, 'r');
        } else {
            $roleids = " = -1";
            $roleparams = array();
        }

        // We want to query both the current context and parent contexts.
        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true),
                                                                            SQL_PARAMS_NAMED, 'relatedctx');

        // Get the search condition.
        list($searchcondition, $searchparams) = $this->search_sql($search, 'u');

        // Build the SQL.
        list($enrolsql, $enrolparams) = get_enrolled_sql($context);

        $studentids = local_problemsection_get_studentids($context);
        $potentialmembers = array();
        $strstudent = get_string('potentialstudents');
        $potentialmembers[$strstudent] = array();
        $groupinggroups = $DB->get_records('groupings_groups', array('groupingid' => $this->groupingid));
        foreach ($studentids as $studentid) {
            $ingrouping = false;
            foreach ($groupinggroups as $groupinggroup) {
                $ingroup = $DB->record_exists('groups_members',
                        array('groupid' => $groupinggroup->groupid, 'userid' => $studentid));
                if ($ingroup) {
                    $ingrouping = true;
                }
            }
            reset($groupinggroups);
            $user = $DB->get_record('user', array('id' => $studentid));

            // If the user is not already somewhere in this grouping, we place him in the list.
            if ($ingrouping == false) {
                $potentialusermember = new stdClass();
                $potentialusermember->id = $user->id;
                $allnames = get_all_user_name_fields();
                foreach ($allnames as $allname) {
                    $potentialusermember->$allname = $user->$allname;
                }
                $potentialusermember->email = $user->email;
                $potentialusermember->fullname = $user->firstname.' '.$user->lastname;
                $potentialmembers[$strstudent][$potentialusermember->id] = $potentialusermember;
            }
        }
        return $potentialmembers;
    }

    /**
     * Output this user_selector as HTML.
     *
     * @param object $psgrouping, the grouping for this problem section.
     * @return mixed if $return is true, returns the HTML as a string, otherwise returns nothing.
     */
    public function display($psgrouping = 0) {
        // Get the list of requested users.
        $search = optional_param($this->name . '_searchtext', '', PARAM_RAW);
        if (optional_param($this->name . '_clearbutton', false, PARAM_BOOL)) {
            $search = '';
        }
        $groupedusers = $this->find_users($search, $psgrouping);

        // Output the select.
        $name = $this->name;
        $multiselect = '';
        if ($this->multiselect) {
            $name .= '[]';
            $multiselect = 'multiple="multiple" ';
        }
        $output = '<div class="userselector" id="' . $this->name . '_wrapper">' . "\n" .
                '<select name="' . $name . '" id="' . $this->name . '" ' .
                $multiselect . 'size="' . $this->rows . '">' . "\n";

        // Populate the select.
        $output .= $this->output_options($groupedusers, $search);

        // Output the search controls.
        $output .= "</select>\n<div>\n";
        $output .= '<input type="text" name="' . $this->name . '_searchtext" id="' .
                $this->name . '_searchtext" size="15" value="' . s($search) . '" />';
        $output .= '<input type="submit" name="' . $this->name . '_searchbutton" id="' .
                $this->name . '_searchbutton" value="' . $this->search_button_caption() . '" />';
        $output .= '<input type="submit" name="' . $this->name . '_clearbutton" id="' .
                $this->name . '_clearbutton" value="' . get_string('clear') . '" />';
        $output .= "</div>\n</div>\n\n";

        // Initialise the ajax functionality.
        $output .= $this->initialise_javascript($search);

        echo $output;
    }
}
