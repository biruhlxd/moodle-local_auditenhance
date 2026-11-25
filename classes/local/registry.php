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

namespace local_auditenhance\local;

use local_auditenhance\check\base;
use local_auditenhance\hook\register_extensions;
use local_auditenhance\local\scanner\scanner;

/**
 * Registry of everything the audit knows about, collected through the register_extensions hook.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var registry|null Instance for this request. */
    private static ?registry $instance = null;

    /** @var area[] Areas by id, sorted. */
    private array $areas;

    /** @var base[] Check instances by reference, sorted by area then evaluation order. */
    private array $checks = [];

    /** @var scanner[] Scanners by id. */
    private array $scanners;

    /** @var screen[] Screens by id, sorted. */
    private array $screens;

    /** @var export_format[] Export formats by id. */
    private array $exportformats;

    /** @var finding_action[] Finding actions. */
    private array $findingactions;

    /** @var content\content_source[] Content sources by id. */
    private array $contentsources;

    /**
     * Build the registry from a filled hook.
     *
     * @param register_extensions $hook
     */
    private function __construct(register_extensions $hook) {
        $areas = $hook->get_areas();
        uasort($areas, fn(area $a, area $b) => [$a->get_sortorder(), $a->get_id()] <=> [$b->get_sortorder(), $b->get_id()]);
        $this->areas = $areas;

        $checks = [];
        foreach ($hook->get_checks() as $classname) {
            /** @var base $check */
            $check = new $classname();
            try {
                $areaid = $check->get_area();
            } catch (\coding_exception $e) {
                debugging($e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
            if (!isset($this->areas[$areaid])) {
                debugging("Audit check {$classname} belongs to unknown area {$areaid}", DEBUG_DEVELOPER);
                continue;
            }
            $checks[$check->get_ref()] = $check;
        }
        $areaorder = array_flip(array_keys($this->areas));
        uasort($checks, fn(base $a, base $b) =>
            [$areaorder[$a->get_area()], $a->get_evaluation_order(), $a->get_ref()]
            <=> [$areaorder[$b->get_area()], $b->get_evaluation_order(), $b->get_ref()]);
        $this->checks = $checks;

        $this->scanners = $hook->get_scanners();
        $screens = $hook->get_screens();
        uasort($screens, fn(screen $a, screen $b) => [$a->get_sortorder(), $a->get_id()] <=> [$b->get_sortorder(), $b->get_id()]);
        $this->screens = $screens;
        $this->exportformats = $hook->get_export_formats();
        $this->findingactions = $hook->get_finding_actions();
        $this->contentsources = $hook->get_content_sources();
    }

    /**
     * The registry for this request.
     *
     * @return self
     */
    public static function instance(): self {
        if (self::$instance === null) {
            $hook = new register_extensions();
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            self::$instance = new self($hook);
        }
        return self::$instance;
    }

    /**
     * Build a registry from a given hook, for tests.
     *
     * @param register_extensions $hook
     * @return self
     */
    public static function from_hook(register_extensions $hook): self {
        return self::$instance = new self($hook);
    }

    /**
     * Forget the registry, so the next call collects everything again.
     */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * All areas, sorted.
     *
     * @return area[]
     */
    public function get_areas(): array {
        return $this->areas;
    }

    /**
     * An area by id.
     *
     * @param string $id
     * @return area|null
     */
    public function get_area(string $id): ?area {
        return $this->areas[$id] ?? null;
    }

    /**
     * Checks, optionally only those of some areas.
     *
     * @param string[]|null $areaids Null for all areas.
     * @return base[] By reference.
     */
    public function get_checks(?array $areaids = null): array {
        if ($areaids === null || $areaids === []) {
            return $this->checks;
        }
        return array_filter($this->checks, fn(base $c) => in_array($c->get_area(), $areaids, true));
    }

    /**
     * A check by reference.
     *
     * @param string $ref
     * @return base|null
     */
    public function get_check(string $ref): ?base {
        return $this->checks[$ref] ?? null;
    }

    /**
     * Checks that also appear in a core check report.
     *
     * @param string $report security, performance or status.
     * @return base[]
     */
    public function get_core_report_checks(string $report): array {
        $result = [];
        foreach ($this->checks as $check) {
            $checkreport = $check->get_core_report() ?? $this->areas[$check->get_area()]->get_core_report();
            if ($checkreport === $report) {
                $result[] = $check;
            }
        }
        return $result;
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
     * Screens, sorted.
     *
     * @return screen[]
     */
    public function get_screens(): array {
        return $this->screens;
    }

    /**
     * Export formats supporting a scope that the current user may use.
     *
     * @param string|null $scope Null for all formats.
     * @return export_format[]
     */
    public function get_export_formats(?string $scope = null): array {
        if ($scope === null) {
            return $this->exportformats;
        }
        $context = \context_system::instance();
        return array_filter(
            $this->exportformats,
            fn(export_format $f) => $f->supports($scope) && has_capability($f->get_capability(), $context)
        );
    }

    /**
     * An export format by id.
     *
     * @param string $id
     * @return export_format|null
     */
    public function get_export_format(string $id): ?export_format {
        return $this->exportformats[$id] ?? null;
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
     * Content sources available on this site.
     *
     * @return content\content_source[]
     */
    public function get_content_sources(): array {
        return array_filter($this->contentsources, fn(content\content_source $s) => $s->is_available());
    }
}
