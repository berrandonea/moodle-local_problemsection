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
 * File : settings.php
 * Plugin global settings.
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_problemsection', get_string('pluginname', 'local_problemsection'));

    $settings->add(new admin_setting_heading('potentialtools', get_string('potentialtools', 'local_problemsection'),
                                                               get_string('choosepotentialtools', 'local_problemsection')));

    $enabledmods = $DB->get_records('modules', array('visible' => 1));
    $commontools = array('chat', 'etherpadlite', 'forum', 'publication', 'wiki');
    foreach ($enabledmods as $mod) {
        if (in_array($mod->name, $commontools)) {
            $modcheckbox = new admin_setting_configcheckbox("local_problemsection/$mod->name",
                                                        get_string('pluginname',
                                                        "mod_$mod->name"),
                                                        '', true);
            $settings->add($modcheckbox);
        }
    }
    $ADMIN->add('localplugins', $settings);
}
