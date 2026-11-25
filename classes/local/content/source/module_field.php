<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_auditenhance\local\content\source;

use local_auditenhance\local\content\content_item;
use local_auditenhance\local\content\content_source;

/**
 * HTML fields of an activity module table, for example page content or forum introductions.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_field implements content_source {
    /**
     * Constructor.
     *
     * @param string $modname Module name, for example 'page'.
     * @param string[] $fields HTML fields of the module table to read.
     */
    public function __construct(
        /** @var string Module name. */
        protected string $modname,
        /** @var string[] Fields. */
        protected array $fields,
    ) {
    }

    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'mod_' . $this->modname;
    }

    /**
     * Available when the module is installed.
     *
     * @return bool
     */
    public function is_available(): bool {
        global $DB;
        return $DB->record_exists('modules', ['name' => $this->modname])
            && $DB->get_manager()->table_exists($this->modname);
    }

    /**
     * Read the next module instances.
     *
     * @param int $afterid
     * @param int $limit
     * @return array
     */
    public function fetch(int $afterid, int $limit): array {
        global $DB;
        $fields = implode(', ', array_map(fn(string $f) => 't.' . $f, $this->fields));
        $sql = "SELECT t.id, t.name, $fields, cm.id AS cmid, cm.course, ctx.id AS contextid
                  FROM {" . $this->modname . "} t
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = t.id AND cm.deletioninprogress = 0
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                 WHERE t.id > :afterid
              ORDER BY t.id";
        $records = $DB->get_records_sql($sql, ['modname' => $this->modname, 'level' => CONTEXT_MODULE,
            'afterid' => $afterid], 0, $limit);
        $items = [];
        $lastid = 0;
        $modulename = get_string('modulename', 'mod_' . $this->modname);
        foreach ($records as $record) {
            $lastid = (int)$record->id;
            $url = $this->modname === 'label'
                ? new \moodle_url('/course/view.php', ['id' => $record->course], 'module-' . $record->cmid)
                : new \moodle_url('/mod/' . $this->modname . '/view.php', ['id' => $record->cmid]);
            $label = $modulename . ': ' . shorten_text(format_string($record->name), 60);
            foreach ($this->fields as $field) {
                if ((string)$record->$field === '') {
                    continue;
                }
                $items[] = new content_item(
                    (string)$record->$field,
                    (int)$record->contextid,
                    (int)$record->course,
                    'mod_' . $this->modname,
                    (int)$record->id,
                    $field,
                    $label,
                    $url->out(false)
                );
            }
        }
        return [$lastid, $items];
    }
}
