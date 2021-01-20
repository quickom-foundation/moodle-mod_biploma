<?php

// This file is part of the Biploma Certificate module for Moodle - http://moodle.org/
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
 * Handles viewing a certificate
 *
 * @package    mod_biploma
 * @copyright  2020 Beowulf Blockchain.
 * @copyright  based on work by Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once "../../config.php";
require_once $CFG->dirroot . '/mod/biploma/lib.php';
require_once $CFG->dirroot . '/mod/biploma/basic_env.php';

$id = required_param('id', PARAM_INT); // Course Module ID

$cm = get_coursemodule_from_id('biploma', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$biploma_certificate = $DB->get_record('biploma', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course->id, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/biploma:view', $context);

// Initialize $PAGE, compute blocks
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/biploma/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_title(format_string($biploma_certificate->name));
$PAGE->set_heading(format_string($course->fullname));

// User has admin privileges, show table of certificates.
if (has_capability('mod/biploma:manage', $context)) {

    // Get array of certificates
    if ($biploma_certificate->groupid) {
        $certificates = biploma_get_credentials($biploma_certificate->groupid);
    }

    $table = new html_table();
    $table->head = [get_string('id', 'biploma'), get_string('recipient', 'biploma'), get_string('certificateurl', 'biploma'), get_string('datecreated', 'biploma')];

    $base_link = get_bplm_url_https();
    foreach ($certificates as $certificate) {
        $issue_date = date("M d, Y", $certificate->created_at / 1000);
        if (isset($certificate->url)) {
            $certificate_link = $certificate->url;
        } else if (!empty($certificate->transaction_id)) {
            $certificate_link = $base_link . '/tx/' . $certificate->transaction_id;
        } else {
            $certificate_link = '';
        }
        if (!empty($certificate_link)) {
            $link = "<a href='$certificate_link' target='_blank' style='word-break: break-word;'>$certificate_link</a>";
        } else {
            $link = "<a href='$base_link' target='_blank' style='word-break: break-word;'>Unpublished</a>";
        }

        if (!isset($certificate->bc_data->rcvName)) {
            $rcvName = "The name field is missing on the template";
        } else {
            $rcvName = $certificate->bc_data->rcvName;
        }
        $table->data[] = [
            $certificate->record_id,
            $rcvName,
            $link,
            $issue_date,
        ];
    }

    echo $OUTPUT->header();
    echo html_writer::tag('h3', get_string('viewheader', 'biploma', $biploma_certificate->name));
    if ($biploma_certificate->groupid) {
        echo html_writer::tag('h5', get_string('viewsubheader', 'biploma', $biploma_certificate->groupid));
    }

    echo html_writer::tag('p', get_string('gotodashboard', 'biploma', $base_link));

    echo html_writer::tag('br', null);
    echo html_writer::table($table);
    echo $OUTPUT->footer($course);
} else {
    $certificates = biploma_get_credentials($biploma_certificate->groupid, null, $USER->email);

    // Echo the page
    echo $OUTPUT->header();

    if (method_exists($PAGE->theme, 'image_url')) {
        $src = $OUTPUT->image_url('incomplete_cert', 'biploma');
    } else {
        $src = $OUTPUT->pix_url('incomplete_cert', 'biploma');
    }

    echo html_writer::start_div('text-center');
    echo html_writer::tag('br', null);
    echo html_writer::img($src, get_string('viewimgincomplete', 'biploma'), ['width' => '90%']);
    echo html_writer::end_div('text-center');

    echo $OUTPUT->footer($course);
}
