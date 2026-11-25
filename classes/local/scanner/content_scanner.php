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

namespace local_auditenhance\local\scanner;

use local_auditenhance\local\content\brickfield;
use local_auditenhance\local\content\brickfield_mapped;
use local_auditenhance\local\content\content_check;
use local_auditenhance\local\content\content_source;
use local_auditenhance\local\content\html;
use local_auditenhance\local\registry;
use local_auditenhance\local\run_context;

/**
 * Walks HTML content from all content sources in batches and feeds it to content checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_scanner implements scanner {
    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'content';
    }

    /**
     * Interface of the checks fed.
     *
     * @return string
     */
    public function get_check_interface(): string {
        return content_check::class;
    }

    /**
     * Checks this scanner must feed: those not covered by Brickfield results.
     *
     * @param \local_auditenhance\check\base[] $checks
     * @return content_check[]
     */
    public function filter_checks(array $checks): array {
        if (!brickfield::in_use()) {
            return $checks;
        }
        return array_filter($checks, fn($c) => !($c instanceof brickfield_mapped && $c->get_brickfield_checks()));
    }

    /**
     * Needed when a check is left after Brickfield.
     *
     * @param array $checks
     * @return bool
     */
    public function is_needed(array $checks): bool {
        return (bool)$this->filter_checks($checks);
    }

    /**
     * Available sources.
     *
     * @return content_source[]
     */
    protected function get_sources(): array {
        return array_values(registry::instance()->get_content_sources());
    }

    /**
     * Process one batch of one source.
     *
     * @param run_context $context
     * @param array $checks
     * @param string|null $cursor
     * @return string|null
     */
    public function process_batch(run_context $context, array $checks, ?string $cursor): ?string {
        $checks = $this->filter_checks($checks);
        $sources = $this->get_sources();
        $state = $cursor ? json_decode($cursor, true) : ['source' => 0, 'after' => 0];
        if (!$checks || $state['source'] >= count($sources)) {
            return null;
        }
        $batchsize = max(1, (int)$context->get_config('batchsize', 200));
        [$lastid, $items] = $sources[$state['source']]->fetch((int)$state['after'], $batchsize);
        foreach ($items as $item) {
            if (strpos($item->html, '<') === false) {
                continue;
            }
            $xpath = html::parse($item->html);
            if (!$xpath) {
                continue;
            }
            foreach ($checks as $check) {
                $check->analyse($context, $item, $xpath);
            }
        }
        if ($lastid === 0) {
            $state = ['source' => $state['source'] + 1, 'after' => 0];
        } else {
            $state['after'] = $lastid;
        }
        return $state['source'] >= count($sources) ? null : json_encode($state);
    }

    /**
     * Progress.
     *
     * @param string|null $cursor
     * @return float
     */
    public function get_progress(?string $cursor): float {
        $count = count($this->get_sources());
        if (!$cursor || !$count) {
            return 0;
        }
        $state = json_decode($cursor, true);
        return min(1, ($state['source'] ?? 0) / $count);
    }
}
