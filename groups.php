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
 * File : groups.php
 * Manage groups for this problem section.
 */

require_once('../../config.php');
require_once('lib.php');
require_once('selector.php');
require_once($CFG->dirroot.'/user/selector/lib.php');

$psid = required_param('psid', PARAM_INT);
$courseid = required_param('id', PARAM_INT);
$paramgroupid = optional_param('groupid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$changedgroupid = optional_param('changed', 0, PARAM_INT);

$groupsurl = new moodle_url('/local/problemsection/groups.php', array('id' => $courseid, 'psid' => $psid));
$groupsurlstring = "$CFG->wwwroot/local/problemsection/groups.php?id=$courseid&psid=$psid";

$course = $DB->get_record('course', array('id' => $courseid));
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/problemsection/groups.php', array('id' => $courseid, 'psid' => $psid));
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $context);
require_capability('local/problemsection:addinstance', $context);

// Print the page and form.
$strgroups = get_string('groups');
$strparticipants = get_string('participants');
$stradduserstogroup = get_string('adduserstogroup', 'group');
$strusergroupmembership = get_string('usergroupmembership', 'group');
$PAGE->requires->js('/group/clientlib.js');

// Problem section, grouping and number of groups.
$problemsection = $DB->get_record('local_problemsection', array('id' => $psid), '*', MUST_EXIST);
$psgrouping = $DB->get_record('groupings', array('id' => $problemsection->groupingid));
$psgroupinggroups = $DB->get_records('groupings_groups', array('groupingid' => $psgrouping->id));
$nbpsgroups = count($psgroupinggroups);

if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'cleanallmembers':
            foreach ($psgroupinggroups as $psgroupinggroup) {
                $DB->delete_records('groups_members', array('groupid' => $psgroupinggroup->groupid));
            }
            reset($psgroupinggroups);
            break;

        case 'creategroup':
            $nbpsgroups++;
            $newgroupid = local_problemsection_creategroup($courseid, $problemsection->name, $nbpsgroups, $psgrouping->id);
            header("Location: $groupsurlstring");
            break;

        case 'cleargroup':
            $DB->delete_records('groups_members', array('groupid' => $paramgroupid));
            break;

        default: // ERROR.
            print_error('unknowaction', '', $groupsurl);
            break;
    }
}

$pagetitle = "$problemsection->name : $strgroups";
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->navbar->add(get_string('manage', 'local_problemsection'),
        new moodle_url('/local/problemsection/manage.php', array('id' => $courseid)));
$PAGE->navbar->add(get_string('managegroups', 'local_problemsection'), $groupsurl);

echo $OUTPUT->header();
echo "<div style='text-align:center'>";
echo "<a href='$groupsurlstring&action=cleanallmembers&sesskey=".s(sesskey())."'>";
echo "<input type='submit' style='width:200px' value='".get_string('clearallgroups', 'local_problemsection')."' />";
echo "</a>";
echo "<a href='$groupsurlstring&action=creategroup&sesskey=".s(sesskey())."'>";
echo "<input type='submit' style='width:200px' value='".get_string('creategroup', 'local_problemsection')."' />";
echo "</a>";
echo "</div>";

foreach ($psgroupinggroups as $psgroupinggroup) {
    $group = $DB->get_record('groups', array('id' => $psgroupinggroup->groupid));
    if (!$group) {
        continue;
    }
    $groupmembersselector = new local_problemsection_members_selector('removeselect', array('groupid' => $group->id,
                                                                                            'courseid' => $course->id));
    $potentialmembersselector = new local_problemsection_nonmembers_selector('addselect', array('groupid' => $group->id,
                                                                                                'groupingid' => $psgrouping->id,
                                                                                                'courseid' => $course->id));

    if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
        if ($changedgroupid == $group->id) {
            $userstoadd = $potentialmembersselector->get_selected_users();
            if (!empty($userstoadd)) {
                foreach ($userstoadd as $user) {
                    if (!groups_add_member($group->id, $user->id)) {
                        print_error('erroraddremoveuser', 'group', $returnurl);
                    }
                    $addedusersid[] = $user->id;
                    $groupmembersselector->invalidate_selected_users();
                }
            }
        }
        $potentialmembersselector->invalidate_selected_users();
    }

    if (optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
        $userstoremove = $groupmembersselector->get_selected_users();
        if (!empty($userstoremove)) {
            foreach ($userstoremove as $user) {
                if (!groups_remove_member_allowed($group->id, $user->id)) {
                    print_error('errorremovenotpermitted', 'group', $returnurl,
                            $user->fullname);
                }
                if (!groups_remove_member($group->id, $user->id)) {
                    print_error('erroraddremoveuser', 'group', $returnurl);
                }
                $removedusersid[] = $user->id;
                $groupmembersselector->invalidate_selected_users();
                $potentialmembersselector->invalidate_selected_users();
            }
        }
    }
}

foreach ($psgroupinggroups as $psgroupinggroup) {
    $group = $DB->get_record('groups', array('id' => $psgroupinggroup->groupid));
    if (!$group) {
        continue;
    }
    $groupmembersselector = new local_problemsection_members_selector('removeselect', array('groupid' => $group->id,
                                                                                            'courseid' => $course->id));
    $potentialmembersselector = new local_problemsection_nonmembers_selector('addselect', array('groupid' => $group->id,
                                                                                                'groupingid' => $psgrouping->id,
                                                                                                'courseid' => $course->id));

    // Store the rows we want to display in the group info.
    $groupinforow = array();

    // Check if there is a picture to display.
    if (!empty($group->picture)) {
        $picturecell = new html_table_cell();
        $picturecell->attributes['class'] = 'left side picture';
        $picturecell->text = print_group_picture($group, $course->id, true, true, false);
        $groupinforow[] = $picturecell;
    }

    // Check if we have something to show.
    if (!empty($groupinforow)) {
        $groupinfotable = new html_table();
        $groupinfotable->attributes['class'] = 'groupinfobox';
        $groupinfotable->data[] = new html_table_row($groupinforow);
        echo html_writer::table($groupinfotable);
    }

    echo "<h3>$group->name</h3>";
    echo '<table><tr>';
    // Other problem sections using this group.
    echo '<td>';
    $othergroupings = $DB->get_records('groupings_groups', array('groupid' => $group->id));
    if (count($othergroupings) > 1) {
        echo get_string('sharedgroup', 'local_problemsection').'<ul>';
        foreach ($othergroupings as $othergrouping) {
            $otherps = $DB->get_record('local_problemsection', array('groupingid' => $othergrouping->groupingid));
            if ($otherps->id != $psid) {
                echo "<li>$otherps->name</li>";
            }
        }
        echo '</ul>'.get_string('sharedchanges', 'local_problemsection').'<br><br>';
    }
    echo '</td>';
    echo '<td>';
    echo "<a href='$groupsurlstring&groupid=$group->id&action=cleargroup&sesskey=".s(sesskey())."'>";
    echo '<button>'.get_string('cleargroup', 'local_problemsection').'</button>';
    echo '</a>';
    echo '</td>';
    echo '</tr></table>';

    // Print the editing form.
    $addmembersurl = "groups.php?id=$courseid&psid=$psid&groupid=$psgroupinggroup->groupid";
    ?>
    <div id="addmembersform">
        <form id="assignform" method="post" action="<?php echo $groupsurlstring; ?>">
        <div>
            <input type="hidden" name="sesskey" value="<?php p(sesskey()); ?>" />
            <input type="hidden" name="changed" value="<?php echo $group->id; ?>" />
            <table class="generaltable generalbox groupmanagementtable boxaligncenter" summary="">
                <tr>
                    <td id='existingcell'>
                        <p>
                            <label for="removeselect">
                                <?php
                                print_string('groupmembers', 'group');
                                echo ' '.$group->name;
                                ?>
                            </label>
                        </p>
                        <?php $groupmembersselector->display(); ?>
                    </td>
                    <td id='buttonscell'>
                        <p class="arrow_button">
                            <input name="add" id="add" type="submit" style="width:200px"
                                   value="<?php echo $OUTPUT->larrow().'&nbsp;'.get_string('add'); ?>"
                                   title="<?php print_string('add'); ?>"
                            />
                            <br>
                            <input name="remove" id="remove" type="submit" style="width:200px"
                                   value="<?php echo get_string('remove').'&nbsp;'.$OUTPUT->rarrow(); ?>"
                                   title="<?php print_string('remove'); ?>"
                            />
                        </p>
                    </td>
                    <td id='potentialcell'>
                        <p>
                            <label for="addselect"><?php print_string('potentialmembs', 'group'); ?></label>
                        </p>
                        <?php $potentialmembersselector->display($psgrouping); ?>
                    </td>
                </tr>
            </table>
        </div>
        </form>
    </div>
    <?php
}
echo "<a href='manage.php?id=$courseid'><button>".get_string('back')."</button></a>";

$potentialmembersselector->print_user_summaries($course->id);
$PAGE->requires->js_init_call('init_add_remove_members_page', null, false, $potentialmembersselector->get_js_module());

echo $OUTPUT->footer();
