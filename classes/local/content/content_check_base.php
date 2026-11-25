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

namespace local_auditenhance\local\content;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;

/**
 * Base class of checks that analyse HTML content.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class content_check_base extends base implements brickfield_mapped, content_check {
    /**
     * Content checks only run in the audit task.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Brickfield checks covering the same problem, none by default.
     *
     * @return string[]
     */
    public function get_brickfield_checks(): array {
        return [];
    }

    /**
     * Link to the issue counts per course.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/local/auditenhance/courses.php');
    }

    /**
     * Build the finding from the items found by the scan.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        return $this->finding_from_items($context);
    }

    /**
     * Record a problem in a piece of content.
     *
     * @param run_context $context
     * @param content_item $content
     * @param string $issue Issue code; its description is the string issue_<code> of the check's component.
     * @param string $severity
     * @param \DOMNode|null $node Element with the problem, shown as an extract.
     */
    protected function report(
        run_context $context,
        content_item $content,
        string $issue,
        string $severity,
        ?\DOMNode $node = null
    ): void {
        $description = $this->optional_string('issue_' . $issue) ?: $issue;
        $context->add_item($this, new item(
            $issue,
            $severity,
            $content->contextid,
            $content->courseid,
            $content->component,
            $content->itemid,
            $content->field,
            $content->userid,
            [
                'label' => $content->label . ': ' . $description,
                'url' => $content->url,
                'snippet' => $node ? html::snippet($node) : null,
            ]
        ));
    }

    /**
     * Elements matching an XPath expression.
     *
     * @param \DOMXPath $xpath
     * @param string $expression
     * @param \DOMNode|null $contextnode
     * @return \DOMElement[]
     */
    protected function query(\DOMXPath $xpath, string $expression, ?\DOMNode $contextnode = null): array {
        $result = [];
        $nodes = $contextnode ? $xpath->query($expression, $contextnode) : $xpath->query($expression);
        if ($nodes) {
            foreach ($nodes as $node) {
                if ($node instanceof \DOMElement) {
                    $result[] = $node;
                }
            }
        }
        return $result;
    }
}
