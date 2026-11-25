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

namespace local_auditenhance\hook;

use local_auditenhance\check\base;
use local_auditenhance\local\area;
use local_auditenhance\local\content\content_source;
use local_auditenhance\local\export_format;
use local_auditenhance\local\finding_action;
use local_auditenhance\local\scanner\scanner;
use local_auditenhance\local\screen;

/**
 * Collects audit areas, checks, scanners, content sources, screens, export formats and finding actions.
 *
 * Plugins add a callback for this hook in their db/hooks.php and call the add_ methods.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Register audit areas, checks, scanners, screens, export formats and finding actions.')]
#[\core\attribute\tags('local_auditenhance', 'report')]
final class register_extensions {
    /** @var area[] Areas by id. */
    private array $areas = [];

    /** @var string[] Check class names. */
    private array $checks = [];

    /** @var string[] Check class names removed by a callback. */
    private array $removedchecks = [];

    /** @var scanner[] Scanners by id. */
    private array $scanners = [];

    /** @var screen[] Screens by id. */
    private array $screens = [];

    /** @var export_format[] Export formats by id. */
    private array $exportformats = [];

    /** @var finding_action[] Finding actions. */
    private array $findingactions = [];

    /** @var content_source[] Content sources by id. */
    private array $contentsources = [];

    /**
     * Add an area. An area with the same id replaces the earlier one.
     *
     * @param area $area
     * @return self
     */
    public function add_area(area $area): self {
        $this->areas[$area->get_id()] = $area;
        return $this;
    }

    /**
     * Add a check class. It must extend {@see base}.
     *
     * @param string $classname
     * @return self
     */
    public function add_check(string $classname): self {
        $classname = ltrim($classname, '\\');
        if (!is_subclass_of($classname, base::class)) {
            throw new \coding_exception("Audit check {$classname} must extend " . base::class);
        }
        $this->checks[$classname] = $classname;
        return $this;
    }

    /**
     * Add several check classes.
     *
     * @param string[] $classnames
     * @return self
     */
    public function add_checks(array $classnames): self {
        foreach ($classnames as $classname) {
            $this->add_check($classname);
        }
        return $this;
    }

    /**
     * Remove a check class added earlier or later by another callback.
     *
     * @param string $classname
     * @return self
     */
    public function remove_check(string $classname): self {
        $this->removedchecks[ltrim($classname, '\\')] = true;
        return $this;
    }

    /**
     * Add a scanner.
     *
     * @param scanner $scanner
     * @return self
     */
    public function add_scanner(scanner $scanner): self {
        $this->scanners[$scanner->get_id()] = $scanner;
        return $this;
    }

    /**
     * Add a screen (tab).
     *
     * @param screen $screen
     * @return self
     */
    public function add_screen(screen $screen): self {
        $this->screens[$screen->get_id()] = $screen;
        return $this;
    }

    /**
     * Add an export format.
     *
     * @param export_format $format
     * @return self
     */
    public function add_export_format(export_format $format): self {
        $this->exportformats[$format->get_id()] = $format;
        return $this;
    }

    /**
     * Add a finding action.
     *
     * @param finding_action $action
     * @return self
     */
    public function add_finding_action(finding_action $action): self {
        $this->findingactions[] = $action;
        return $this;
    }

    /**
     * Add a source of HTML content for the content scanner.
     *
     * @param content_source $source
     * @return self
     */
    public function add_content_source(content_source $source): self {
        $this->contentsources[$source->get_id()] = $source;
        return $this;
    }

    /**
     * Areas.
     *
     * @return area[]
     */
    public function get_areas(): array {
        return $this->areas;
    }

    /**
     * Check classes.
     *
     * @return string[]
     */
    public function get_checks(): array {
        return array_values(array_diff_key($this->checks, $this->removedchecks));
    }

    /**
     * Scanners.
     *
     * @return scanner[]
     */
    public function get_scanners(): array {
        return $this->scanners;
    }

    /**
     * Screens.
     *
     * @return screen[]
     */
    public function get_screens(): array {
        return $this->screens;
    }

    /**
     * Export formats.
     *
     * @return export_format[]
     */
    public function get_export_formats(): array {
        return $this->exportformats;
    }

    /**
     * Finding actions.
     *
     * @return finding_action[]
     */
    public function get_finding_actions(): array {
        return $this->findingactions;
    }

    /**
     * Content sources.
     *
     * @return content_source[]
     */
    public function get_content_sources(): array {
        return $this->contentsources;
    }
}
