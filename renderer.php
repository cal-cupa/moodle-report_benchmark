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
 * HTML rendering methods are defined here
 *
 * @package    report_benchmark
 * @copyright  2016 onwards Mickaël Pannequin {@link mailto:mickael.pannequin@gmail.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Overview benchmark renderer
 *
 * @copyright  2016 onwards Mickaël Pannequin {@link mailto:mickael.pannequin@gmail.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_benchmark_renderer extends plugin_renderer_base {

    /**
     * Disclaimer
     *
     * @return string First page, disclaimer
     * @throws coding_exception
     */
    public function launcher() {

        // Header.
        $out  = $this->output->header();
        $out .= $this->output->heading(get_string('adminreport', 'report_benchmark'));

        // Welcome message.
        $out .= html_writer::tag('p', get_string('info', 'report_benchmark'));
        $out .= html_writer::tag('p', get_string('infoaverage', 'report_benchmark'));
        $out .= html_writer::tag('p', get_string('infodisclaimer', 'report_benchmark'));

        // Get the list of available tests without running them.
        $tests = report_benchmark::get_available_tests_static();
        
        // Form to select tests.
        $out .= html_writer::start_tag('form', [
            'action' => new moodle_url('/report/benchmark/index.php', ['step' => 'run']),
            'method' => 'post'
        ]);
        
        $out .= html_writer::start_div('benchmarktests');
        $out .= html_writer::tag('h4', get_string('selecttests', 'report_benchmark'));
        
        $out .= html_writer::start_tag('div', ['class' => 'form-check']);
        $selectallcheckbox = html_writer::checkbox('selectall', '1', true, get_string('selectall', 'report_benchmark'), [
            'class' => 'form-check-input',
            'id' => 'selectall',
            'onclick' => 'toggleAllTests(this)'
        ]);
        $out .= html_writer::tag('label', $selectallcheckbox, ['class' => 'form-check-label']);
        $out .= html_writer::end_tag('div');
        
        $out .= html_writer::tag('hr', '');
        
        foreach ($tests as $test) {
            $out .= html_writer::start_tag('div', ['class' => 'form-check']);
            $testcheckbox = html_writer::checkbox('tests[]', $test, true, get_string($test.'name', 'report_benchmark'), [
                'class' => 'form-check-input test-checkbox'
            ]);
            $out .= html_writer::tag('label', $testcheckbox, ['class' => 'form-check-label']);
            $out .= html_writer::end_tag('div');
        }
        
        $out .= html_writer::end_div();

        // Button to start the test.
        $out .= html_writer::start_div('continuebutton');
        $out .= html_writer::tag('button', get_string('start', 'report_benchmark'), [
            'type' => 'submit',
            'class' => 'btn btn-primary'
        ]);
        $out .= html_writer::end_div();
        
        $out .= html_writer::end_tag('form');
        
        // JavaScript for select all functionality and localStorage.
        $out .= html_writer::script('
            // Toggle all test checkboxes
            function toggleAllTests(selectAllCheckbox) {
                var checkboxes = document.querySelectorAll(".test-checkbox");
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                saveCheckboxStates();
            }
            
            // Save checkbox states to localStorage
            function saveCheckboxStates() {
                var checkboxes = document.querySelectorAll(".test-checkbox");
                var states = {};
                checkboxes.forEach(function(checkbox) {
                    states[checkbox.value] = checkbox.checked;
                });
                localStorage.setItem("benchmark_test_selection", JSON.stringify(states));
            }
            
            // Restore checkbox states from localStorage
            function restoreCheckboxStates() {
                var saved = localStorage.getItem("benchmark_test_selection");
                if (saved) {
                    var states = JSON.parse(saved);
                    var checkboxes = document.querySelectorAll(".test-checkbox");
                    var anyUnchecked = false;
                    
                    checkboxes.forEach(function(checkbox) {
                        if (states.hasOwnProperty(checkbox.value)) {
                            checkbox.checked = states[checkbox.value];
                            if (!states[checkbox.value]) {
                                anyUnchecked = true;
                            }
                        }
                    });
                    
                    // Update select all checkbox
                    var selectAll = document.getElementById("selectall");
                    if (selectAll) {
                        selectAll.checked = !anyUnchecked;
                    }
                }
            }
            
            // Add change listeners to save state
            document.addEventListener("DOMContentLoaded", function() {
                restoreCheckboxStates();
                
                var checkboxes = document.querySelectorAll(".test-checkbox");
                checkboxes.forEach(function(checkbox) {
                    checkbox.addEventListener("change", function() {
                        saveCheckboxStates();
                        
                        // Update select all checkbox
                        var allChecked = true;
                        checkboxes.forEach(function(cb) {
                            if (!cb.checked) {
                                allChecked = false;
                            }
                        });
                        var selectAll = document.getElementById("selectall");
                        if (selectAll) {
                            selectAll.checked = allChecked;
                        }
                    });
                });
            });
        ');

        // Footer.
        $out .= $this->output->footer();

        return $out;

    }

    /**
     * Display the result
     *
     * @return string Display all data and score
     * @throws coding_exception
     */
    public function display() {

        // Get selected tests from POST data.
        $selectedtests = optional_param_array('tests', null, PARAM_TEXT);

        // Load the benchmark class with selected tests.
        $bench = new report_benchmark($selectedtests);

        // Header.
        $out  = $this->output->header();
        $out .= $this->output->heading(get_string('adminreport', 'report_benchmark'));
        $out .= html_writer::start_div(null, ['id' => 'benchmark']);

        // Header string table.
        $strdesc    = get_string('description', 'report_benchmark');
        $strduring  = get_string('during', 'report_benchmark');
        $strlimit   = get_string('limit', 'report_benchmark');
        $strover    = get_string('over', 'report_benchmark');

        // Get benchmark data.
        $results    = $bench->get_results();
        $totals     = $bench->get_total();

        // Display the big header score.
        $out .= html_writer::start_div('text-center');

        $out .= html_writer::start_tag('h3');
        $out .= get_string('scoremsg', 'report_benchmark') . ' ';

        $out .= html_writer::start_tag('span');
        $out .= get_string('points', 'report_benchmark', $totals['score']);

        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_tag('h3');

        $out .= html_writer::end_div();

        // Display all tests with details in table.
        $table = new html_table();
        $table->head  = ['#', $strdesc, $strduring, $strlimit, $strover];
        $table->attributes = ['class' => 'admintable benchmarkresult generaltable'];
        $table->id = 'benchmarkresult';

        foreach ($results as $result) {

            $row = new html_table_row();
            $rowclass = 'bench_'.$result['name'];
            
            // Add class for non-executed tests.
            if (!$result['executed']) {
                $rowclass .= ' benchmark-not-executed';
            }
            
            $row->attributes['class'] = $rowclass;

            $cell = new html_table_cell($result['id']);
            $row->cells[] = $cell;
            $cell = new html_table_cell($result['name']);
            $text = $result['name'];
            $text .= html_writer::start_div();
            $text .= html_writer::tag('small', $result['info']);
            $text .= html_writer::end_div();
            $cell->text = $text;
            $row->cells[] = $cell;
            
            if ($result['executed']) {
                $cell = new html_table_cell(number_format($result['during'], 3, '.', null));
                $cell->attributes['class'] = $result['class'];
            } else {
                $cell = new html_table_cell('-');
                $cell->attributes['class'] = 'text-muted';
            }
            $row->cells[] = $cell;
            
            $cell = new html_table_cell($result['executed'] ? $result['limit'] : '-');
            if (!$result['executed']) {
                $cell->attributes['class'] = 'text-muted';
            }
            $row->cells[] = $cell;
            
            $cell = new html_table_cell($result['executed'] ? $result['over'] : '-');
            if (!$result['executed']) {
                $cell->attributes['class'] = 'text-muted';
            }
            $row->cells[] = $cell;

            $table->data[] = $row;

        }

        // Display the table footer.
        $row = new html_table_row();
        $row->attributes['class'] = 'footer';
        $cell = new html_table_cell(get_string('total', 'report_benchmark'));
        $cell->colspan = 2;
        $row->cells[] = $cell;

        $cell = new html_table_cell(get_string('duration', 'report_benchmark', number_format($totals['total'], 3, '.', null)));
        $row->cells[] = $cell;

        $cell = new html_table_cell('&nbsp;');
        $cell->colspan = 2;
        $row->cells[] = $cell;

        $table->data[] = $row;

        $row = new html_table_row();
        $row->attributes['class'] = 'footer';
        $cell = new html_table_cell(get_string('score', 'report_benchmark'));
        $cell->colspan = 2;
        $row->cells[] = $cell;
        $cell = new html_table_cell(get_string('points', 'report_benchmark', $totals['score']));
        $row->cells[] = $cell;

        $cell = new html_table_cell('&nbsp;');
        $cell->colspan = 2;
        $row->cells[] = $cell;

        $table->data[] = $row;

        $out .= html_writer::table($table);

        // Contruct and return the fail array without duplicate values.
        $fails = [];
        foreach ($results as $result) {
            if ($result['executed'] && $result['during'] >= $result['limit']) {
                $fails[] = ['fail' => $result['fail'], 'url' => $result['url']];
            }
        }
        $fails = array_unique($fails, SORT_REGULAR);

        // Display the tips.
        $tips = null;
        foreach ($fails as $fail) {
            $failurl = new moodle_url($fail['url']);
            $tips .= html_writer::start_tag('h5', null);
            $tips .= get_string($fail['fail'].'label', 'report_benchmark');
            $tips .= html_writer::end_tag('h5');
            $tips .= get_string($fail['fail'].'solution', 'report_benchmark', $failurl->out());
        }

        if (empty($tips)) {
            $out .= html_writer::start_div('alert alert-success', ['role' => 'alert']);
            $out .= get_string('benchsuccess', 'report_benchmark');
            $out .= html_writer::end_div();
        } else {
            $out .= html_writer::start_div('alert alert-warning', ['role' => 'alert']);
            $out .= get_string('benchfail', 'report_benchmark');
            $out .= $tips;
            $out .= html_writer::end_div();
        }

        // Display the share and redo button.
        $out .= html_writer::start_div('continuebutton');

        $out .= html_writer::link(new moodle_url('https://moodle.org/mod/forum/discuss.php', ['d' => '335282']),
            get_string('benchshare', 'report_benchmark'), ['class' => 'btn btn-default', 'target' => '_blank']);

        $out .= html_writer::link(new moodle_url('/report/benchmark/index.php'),
            get_string('back', 'report_benchmark'), ['class' => 'btn btn-secondary']);

        $out .= html_writer::link(new moodle_url('/report/benchmark/index.php', ['step' => 'run']),
            get_string('redo', 'report_benchmark'), ['class' => 'btn btn-primary']);

        $out .= html_writer::end_div();

        // Footer.
        $out .= html_writer::end_div();
        $out .= $this->output->footer();

        return $out;

    }

}
