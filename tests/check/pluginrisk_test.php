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

namespace local_auditenhance\check\pluginrisk;

use local_auditenhance\fixture_plugininfo;
use local_auditenhance\local\plugins\code_analyser;
use local_auditenhance\local\plugins\scanned_plugin;
use local_auditenhance\local\registry;
use local_auditenhance\local\run_context;
use local_auditenhance\local\run_manager;
use local_auditenhance\local\scanner\plugin_scanner;
use local_auditenhance\local\severity;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fixture_plugininfo.php');

/**
 * Tests for the plugin risk checks, the code analyser and the plugin scanner.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(privacymissing::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(privacynull::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(privacymetadata::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(versionsupport::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(deprecatedapis::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(missingdeps::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(unsafefunctions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rawinput::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(sqlconcat::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(missingversion::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(licenceheader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(langstrings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(riskycapsdefault::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(riskyoverrides::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(roleescalation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(correlation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\plugins\code_analyser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\plugins\scanned_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\scanner\plugin_scanner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\task\detect_plugin_changes::class)]
final class pluginrisk_test extends \advanced_testcase {
    /**
     * Load the fixture privacy providers.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(fixture_plugininfo::path('nullfixture') . '/classes/privacy/provider.php');
        require_once(fixture_plugininfo::path('metafixture') . '/classes/privacy/provider.php');
    }

    /**
     * Reset caches.
     */
    protected function tearDown(): void {
        registry::reset();
        parent::tearDown();
    }

    /**
     * A fixture plugin.
     *
     * @param string $name
     * @return scanned_plugin
     */
    private function plugin(string $name): scanned_plugin {
        return new scanned_plugin(fixture_plugininfo::make($name));
    }

    /**
     * Items a plugin check finds in a fixture plugin.
     *
     * @param \local_auditenhance\local\plugins\plugin_check_base $check
     * @param string $name
     * @return \stdClass[]
     */
    private function items($check, string $name): array {
        $context = run_context::live();
        $check->analyse_plugin($context, $this->plugin($name));
        return $context->get_items($check->get_ref());
    }

    /**
     * Issues and extra texts a check finds.
     *
     * @param \local_auditenhance\local\plugins\plugin_check_base $check
     * @param string $name
     * @return string[] "issue: extra" entries.
     */
    private function issues($check, string $name): array {
        return array_map(function ($item) {
            $detail = json_decode($item->detail, true);
            return $item->issue . ': ' . ($detail['extra'] ?? '');
        }, $this->items($check, $name));
    }

    /**
     * Every check has its strings and belongs to the plugin risk area.
     */
    public function test_strings_and_area(): void {
        foreach (registry::instance()->get_checks(['pluginrisk']) as $check) {
            $this->assertSame('pluginrisk', $check->get_area());
            $this->assertNotEmpty($check->get_explanation(), $check->get_id());
            $this->assertNotEmpty($check->get_fix(), $check->get_id());
        }
        $this->assertCount(17, registry::instance()->get_checks(['pluginrisk']));
    }

    /**
     * The fixture plugin is read correctly.
     */
    public function test_scanned_plugin(): void {
        $plugin = $this->plugin('riskyfixture');
        $this->assertSame('local_riskyfixture', $plugin->get_component());
        $this->assertSame(['db/access.php', 'lib.php', 'version.php'], $plugin->get_php_files());
        $this->assertSame(['local_riskyfixture_log' => ['userid', 'ipaddress']], $plugin->get_personal_tables());
        $this->assertArrayHasKey('local/riskyfixture:configure', $plugin->get_capabilities());
        $this->assertNull($plugin->get_privacy_provider());
        $this->assertNotEmpty($plugin->get_tokens('lib.php'));

        // Subplugins are scanned on their own, not as part of their parent.
        $assign = new scanned_plugin(\core_plugin_manager::instance()->get_plugin_info('mod_assign'));
        $this->assertContains('submission', $assign->get_subplugin_paths());
        foreach ($assign->get_php_files() as $file) {
            $this->assertStringStartsNotWith('submission/', $file);
            $this->assertStringStartsNotWith('feedback/', $file);
        }
    }

    /**
     * Privacy checks.
     */
    public function test_privacy(): void {
        $this->assertSame(['noprovider: local_riskyfixture_log'], $this->issues(new privacymissing(), 'riskyfixture'));
        $this->assertSame([], $this->issues(new privacymissing(), 'nullfixture'));
        $this->assertSame(['nullprovider: local_nullfixture_log'], $this->issues(new privacynull(), 'nullfixture'));
        $this->assertSame([], $this->issues(new privacynull(), 'metafixture'));
        $this->assertSame(['tablenotlisted: local_metafixture_log'], $this->issues(new privacymetadata(), 'metafixture'));
        $this->assertSame([], $this->issues(new privacymetadata(), 'riskyfixture'));
    }

    /**
     * Dependencies and version support.
     */
    public function test_dependencies(): void {
        $this->assertSame(['unsupported: 400–401'], $this->issues(new versionsupport(), 'riskyfixture'));
        $this->assertSame([], $this->issues(new versionsupport(), 'nullfixture'));
        $this->assertSame(['missingdependency: local_doesnotexist'], $this->issues(new missingdeps(), 'riskyfixture'));
        $this->assertSame([], $this->issues(new missingdeps(), 'nullfixture'));
        $issues = $this->issues(new deprecatedapis(), 'riskyfixture');
        $this->assertContains('removedapi: print_error()', $issues);
        $this->assertContains('print_error', array_map('strval', deprecatedapis::REMOVED));
    }

    /**
     * Code structure checks.
     */
    public function test_code(): void {
        $issues = $this->issues(new unsafefunctions(), 'riskyfixture');
        sort($issues);
        $this->assertSame(['eval: eval()', 'shell: shell_exec()', 'unserializeinput: unserialize()'], $issues);

        $this->assertSame(['rawinput: $_POST', 'rawinput: $_GET'], $this->issues(new rawinput(), 'riskyfixture'));

        $items = $this->items(new sqlconcat(), 'riskyfixture');
        $this->assertCount(2, $items);
        $detail = json_decode($items[0]->detail, true);
        $this->assertSame('lib.php', $detail['file']);
        $this->assertGreaterThan(0, $detail['line']);

        $this->assertSame([], $this->issues(new missingversion(), 'riskyfixture'));
        $this->assertSame(['nolicence: Files: 1'], $this->issues(new licenceheader(), 'riskyfixture'));
        $this->assertSame([], $this->issues(new licenceheader(), 'nullfixture'));
        $this->assertSame(['nolangfile: '], $this->issues(new langstrings(), 'riskyfixture'));
        $this->assertSame([], $this->issues(new langstrings(), 'nullfixture'));
    }

    /**
     * Default capabilities of plugins.
     */
    public function test_riskycapsdefault(): void {
        $items = $this->items(new riskycapsdefault(), 'riskyfixture');
        $this->assertCount(1, $items);
        $this->assertSame(severity::CRITICAL, $items[0]->severity);
        $this->assertStringContainsString('local/riskyfixture:configure: student', json_decode($items[0]->detail, true)['extra']);
    }

    /**
     * Code analyser helpers.
     */
    public function test_code_analyser(): void {
        $tokens = code_analyser::tokenise('<?php $a->exec(1); exec(2); \system(3); function exec() {} new exec();');
        $calls = array_column(code_analyser::function_calls($tokens), 'name');
        $this->assertSame(['exec', 'system'], $calls);

        $tokens = code_analyser::tokenise('<?php foo($a, [1, 2], bar(3, 4));');
        $call = code_analyser::function_calls($tokens)[0];
        $this->assertCount(3, code_analyser::arguments($tokens, $call['open']));

        $sql = fn(string $code) => code_analyser::sql_has_injected_value(
            code_analyser::arguments(code_analyser::tokenise('<?php f(' . $code . ');'), 1)[0]
        );
        $this->assertTrue($sql('"SELECT * FROM {t} WHERE id = $id"'));
        $this->assertTrue($sql('"SELECT * FROM {t} WHERE name = \'{$name}\'"'));
        $this->assertTrue($sql('\'SELECT * FROM {t} WHERE id = \' . $id'));
        $this->assertTrue($sql('\'SELECT * FROM {t} WHERE id = \' . required_param("id", PARAM_INT)'));
        $this->assertFalse($sql('\'SELECT * FROM {t} WHERE id = :id\''));
        $this->assertFalse($sql('"SELECT * FROM {t} $where"'));
        $this->assertFalse($sql('\'SELECT * FROM {t} WHERE \' . $where'));
        $this->assertFalse($sql('"SELECT * FROM {t} WHERE id $insql"'));
        $this->assertFalse($sql('"choiceid IN ($subsql)"'));
    }

    /**
     * Risky overrides on the site.
     */
    public function test_riskyoverrides(): void {
        global $DB;
        $this->resetAfterTest();
        $run = run_manager::create_run(null, 'test');
        $check = new riskyoverrides();
        $this->assertTrue((new riskyoverrides())->evaluate(new run_context($run))->is_applicable());
        $this->assertSame('pass', $check->evaluate(new run_context($run))->status);

        $course = $this->getDataGenerator()->create_course();
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('moodle/course:update', CAP_ALLOW, $studentrole, \context_course::instance($course->id)->id);
        $run = run_manager::create_run(null, 'test');
        $finding = $check->evaluate(new run_context($run));
        // Course update has the cross-site scripting risk.
        $this->assertSame(severity::HIGH, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Role escalation.
     */
    public function test_roleescalation(): void {
        global $DB;
        $this->resetAfterTest();
        $check = new roleescalation();
        $run = run_manager::create_run(null, 'test');
        $this->assertSame('pass', $check->evaluate(new run_context($run))->status);

        $teacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $manager = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        core_role_set_assign_allowed($teacher, $manager);
        $run = run_manager::create_run(null, 'test');
        $finding = $check->evaluate(new run_context($run));
        $this->assertSame(severity::HIGH, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Correlation raises the severity when findings combine.
     */
    public function test_correlation(): void {
        $this->resetAfterTest();
        $run = run_manager::create_run(null, 'test');
        $context = new run_context($run);
        $plugin = $this->plugin('riskyfixture');
        (new privacymissing())->analyse_plugin($context, $plugin);
        (new riskycapsdefault())->analyse_plugin($context, $plugin);
        (new unsafefunctions())->analyse_plugin($context, $plugin);
        $nullplugin = $this->plugin('nullfixture');
        (new privacynull())->analyse_plugin($context, $nullplugin);
        $context->flush();

        $finding = (new correlation())->evaluate($context);
        $this->assertSame(severity::CRITICAL, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
        $items = $context->get_items('local_auditenhance_correlation');
        $this->assertSame('local_riskyfixture', $items[0]->component);
        $this->assertSame('skip', (new correlation())->evaluate(run_context::live())->status);
    }

    /**
     * Plugin scanner component list and batches.
     */
    public function test_plugin_scanner(): void {
        $this->resetAfterTest();
        $scanner = new plugin_scanner();
        $this->assertNotContains('local_auditenhance', $scanner->get_components());
        $this->assertNotContains('mod_forum', $scanner->get_components());
        set_config('includecore', 1, 'local_auditenhance');
        set_config('excludeplugins', 'mod_page', 'local_auditenhance');
        set_config('pluginbatchsize', 50, 'local_auditenhance');
        $scanner->reset();
        $components = $scanner->get_components();
        $this->assertContains('mod_forum', $components);
        $this->assertNotContains('mod_page', $components);

        $checks = [new langstrings()];
        $context = run_context::live();
        $cursor = $scanner->process_batch($context, $checks, null);
        $this->assertSame('50', $cursor);
        $this->assertEqualsWithDelta(50 / count($components), $scanner->get_progress($cursor), 0.001);
        // Standard plugins all have language files.
        $this->assertSame(0, $context->count_items('local_auditenhance_langstrings'));
    }

    /**
     * A full plugin risk run on a test site completes.
     */
    public function test_full_run(): void {
        $this->resetAfterTest();
        set_config('includecore', 1, 'local_auditenhance');
        set_config('pluginbatchsize', 100, 'local_auditenhance');
        $run = run_manager::create_run(['pluginrisk'], 'test');
        $this->assertTrue(run_manager::execute((int)$run->id));
        $findings = \local_auditenhance\local\results::get_for_run((int)$run->id);
        $this->assertCount(17, $findings);
        foreach ($findings as $ref => $finding) {
            $this->assertNotSame('error', $finding->status, $ref . ': ' . $finding->summary);
        }
    }

    /**
     * Detecting installed or upgraded plugins queues a scan when the setting is on.
     */
    public function test_detect_plugin_changes(): void {
        $this->resetAfterTest();
        unset_config('pluginversions', 'local_auditenhance');
        $this->assertSame([], \local_auditenhance\task\detect_plugin_changes::detect_changes());
        $this->assertSame([], \local_auditenhance\task\detect_plugin_changes::detect_changes());

        $versions = \local_auditenhance\task\detect_plugin_changes::get_versions();
        $versions['mod_page'] = 1;
        unset($versions['mod_forum']);
        set_config('pluginversions', json_encode($versions), 'local_auditenhance');
        $this->assertEqualsCanonicalizing(
            ['mod_page', 'mod_forum'],
            \local_auditenhance\task\detect_plugin_changes::detect_changes()
        );

        set_config('pluginversions', json_encode($versions), 'local_auditenhance');
        set_config('scanoninstall', 1, 'local_auditenhance');
        $task = new \local_auditenhance\task\detect_plugin_changes();
        ob_start();
        $task->execute();
        ob_end_clean();
        $active = run_manager::get_active_run();
        $this->assertNotNull($active);
        $this->assertSame('pluginrisk', $active->areas);
        $this->assertSame('install', $active->origin);
    }
}
