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

namespace local_auditenhance\check\security;

use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Tests for the security and configuration checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(debugdisplay::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cronrunning::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(forcehttps::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(passwordpolicy::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(selfregistration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(guestlogin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(crawlers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(siteadmins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(adminlogin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(defaultadmin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(riskycaps::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(coreversion::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(pluginupdates::class)]
final class security_test extends \advanced_testcase {
    /**
     * Evaluate a check in a live context.
     *
     * @param \local_auditenhance\check\base $check
     * @return finding
     */
    private function run_check(\local_auditenhance\check\base $check): finding {
        return $check->evaluate(run_context::live());
    }

    /**
     * Every check has its strings and belongs to the security area.
     */
    public function test_strings_and_area(): void {
        $checks = [new debugdisplay(), new cronrunning(), new forcehttps(), new passwordpolicy(), new selfregistration(),
            new guestlogin(), new crawlers(), new siteadmins(), new adminlogin(), new defaultadmin(), new riskycaps(),
            new coreversion(), new pluginupdates()];
        foreach ($checks as $check) {
            $this->assertSame('security', $check->get_area());
            $this->assertNotEmpty($check->get_name());
            $this->assertNotEmpty($check->get_explanation(), $check->get_id());
            $this->assertNotEmpty($check->get_fix(), $check->get_id());
            $this->assertSame('local_auditenhance_' . $check->get_id(), $check->get_ref());
        }
    }

    /**
     * Debug display.
     */
    public function test_debugdisplay(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->debug = DEBUG_DEVELOPER;
        $CFG->debugdisplay = 1;
        $finding = $this->run_check(new debugdisplay());
        $this->assertSame(severity::HIGH, $finding->severity);
        $this->assertTrue($finding->is_failure());
        $CFG->debugdisplay = 0;
        $this->assertSame(severity::LOW, $this->run_check(new debugdisplay())->severity);
        $CFG->debug = DEBUG_NONE;
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new debugdisplay())->status);
    }

    /**
     * Cron running.
     */
    public function test_cronrunning(): void {
        $this->resetAfterTest();
        set_config('lastcronstart', 0, 'tool_task');
        $this->assertSame(severity::CRITICAL, $this->run_check(new cronrunning())->severity);
        set_config('lastcronstart', time() - 2 * DAYSECS, 'tool_task');
        $this->assertSame(severity::CRITICAL, $this->run_check(new cronrunning())->severity);
        set_config('lastcronstart', time() - HOURSECS, 'tool_task');
        $this->assertSame(severity::HIGH, $this->run_check(new cronrunning())->severity);
        set_config('lastcronstart', time() - 30, 'tool_task');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new cronrunning())->status);
    }

    /**
     * HTTPS and secure cookies.
     */
    public function test_forcehttps(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->wwwroot = 'http://example.com';
        $this->assertSame(severity::HIGH, $this->run_check(new forcehttps())->severity);
        $CFG->wwwroot = 'https://example.com';
        $CFG->cookiesecure = 0;
        $this->assertSame(severity::MEDIUM, $this->run_check(new forcehttps())->severity);
        $CFG->cookiesecure = 1;
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new forcehttps())->status);
    }

    /**
     * Password policy.
     */
    public function test_passwordpolicy(): void {
        $this->resetAfterTest();
        set_config('passwordpolicy', 0);
        set_config('lockoutthreshold', 5);
        $this->assertSame(severity::HIGH, $this->run_check(new passwordpolicy())->severity);
        set_config('passwordpolicy', 1);
        set_config('minpasswordlength', 6);
        $this->assertSame(severity::MEDIUM, $this->run_check(new passwordpolicy())->severity);
        set_config('minpasswordlength', 10);
        set_config('lockoutthreshold', 0);
        $this->assertSame(severity::LOW, $this->run_check(new passwordpolicy())->severity);
        set_config('lockoutthreshold', 5);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new passwordpolicy())->status);
    }

    /**
     * Self registration.
     */
    public function test_selfregistration(): void {
        $this->resetAfterTest();
        set_config('registerauth', '');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new selfregistration())->status);
        set_config('auth', 'email');
        set_config('registerauth', 'email');
        set_config('allowemailaddresses', '');
        set_config('recaptchapublickey', '');
        $this->assertSame(severity::HIGH, $this->run_check(new selfregistration())->severity);
        set_config('allowemailaddresses', 'example.com');
        $this->assertSame(severity::LOW, $this->run_check(new selfregistration())->severity);
        set_config('recaptchapublickey', 'pub');
        set_config('recaptchaprivatekey', 'priv');
        set_config('recaptcha', 1, 'auth_email');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new selfregistration())->status);
    }

    /**
     * Guest login.
     */
    public function test_guestlogin(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $this->assertSame(severity::MEDIUM, $this->run_check(new guestlogin())->severity);
        set_config('autologinguests', 0);
        set_config('guestloginbutton', 1);
        $this->assertSame(severity::LOW, $this->run_check(new guestlogin())->severity);
        set_config('guestloginbutton', 0);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new guestlogin())->status);
    }

    /**
     * Crawlers on a private site.
     */
    public function test_crawlers(): void {
        $this->resetAfterTest();
        set_config('opentowebcrawlers', 1);
        set_config('forcelogin', 1);
        $this->assertSame(severity::HIGH, $this->run_check(new crawlers())->severity);
        set_config('sitevisibility', 'public', 'local_auditenhance');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new crawlers())->status);
        set_config('sitevisibility', 'private', 'local_auditenhance');
        set_config('forcelogin', 0);
        $this->assertTrue($this->run_check(new crawlers())->is_failure());
        set_config('opentowebcrawlers', 0);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new crawlers())->status);
    }

    /**
     * Number of site admins.
     */
    public function test_siteadmins(): void {
        $this->resetAfterTest();
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new siteadmins())->status);
        $ids = [get_admin()->id];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->getDataGenerator()->create_user()->id;
        }
        set_config('siteadmins', implode(',', $ids));
        $finding = $this->run_check(new siteadmins());
        $this->assertSame(severity::MEDIUM, $finding->severity);
        $this->assertSame(4, $finding->itemcount);
        $this->assertCount(4, $finding->details['sample']);
        set_config('maxadmins', 4, 'local_auditenhance');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new siteadmins())->status);
    }

    /**
     * Admins without recent login.
     */
    public function test_adminlogin(): void {
        global $DB;
        $this->resetAfterTest();
        $admin = get_admin();
        $DB->set_field('user', 'lastaccess', time(), ['id' => $admin->id]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new adminlogin())->status);
        $DB->set_field('user', 'lastaccess', time() - 200 * DAYSECS, ['id' => $admin->id]);
        $finding = $this->run_check(new adminlogin());
        $this->assertSame(severity::MEDIUM, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Default admin username.
     */
    public function test_defaultadmin(): void {
        global $DB;
        $this->resetAfterTest();
        $admin = get_admin();
        $this->assertSame('admin', $admin->username);
        $this->assertSame(severity::MEDIUM, $this->run_check(new defaultadmin())->severity);
        $DB->set_field('user', 'username', 'siteowner', ['id' => $admin->id]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new defaultadmin())->status);
    }

    /**
     * Risky capabilities for everyone.
     */
    public function test_riskycaps(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new riskycaps())->status);
        $syscontext = \context_system::instance();
        assign_capability('moodle/site:config', CAP_ALLOW, $CFG->defaultuserroleid, $syscontext->id, true);
        $finding = $this->run_check(new riskycaps());
        $this->assertSame(severity::CRITICAL, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
        assign_capability('moodle/site:config', CAP_INHERIT, $CFG->defaultuserroleid, $syscontext->id, true);
        assign_capability('moodle/course:managefiles', CAP_ALLOW, $CFG->guestroleid, $syscontext->id, true);
        $this->assertSame(severity::HIGH, $this->run_check(new riskycaps())->severity);
    }

    /**
     * Core version support.
     */
    public function test_coreversion(): void {
        $this->assertSame('ended', coreversion::get_support_status('401', strtotime('2026-01-01')));
        $this->assertSame('ended', coreversion::get_support_status('311', strtotime('2026-01-01')));
        $this->assertSame('securityonly', coreversion::get_support_status('405', strtotime('2026-01-01')));
        $this->assertSame('ending', coreversion::get_support_status('405', strtotime('2027-09-01')));
        $this->assertSame('supported', coreversion::get_support_status('503', strtotime('2026-11-01')));
        $this->assertSame('newer', coreversion::get_support_status('999', strtotime('2026-11-01')));
        $finding = $this->run_check(new coreversion());
        $this->assertNotSame(finding::STATUS_ERROR, $finding->status);
        $this->assertNotEmpty($finding->summary);
    }

    /**
     * Plugin updates.
     */
    public function test_pluginupdates(): void {
        $this->resetAfterTest();
        set_config('updateautocheck', 0);
        $finding = $this->run_check(new pluginupdates());
        $this->assertSame(severity::INFO, $finding->severity);
        $this->assertTrue($finding->is_failure());
        set_config('updateautocheck', 1);
        $finding = $this->run_check(new pluginupdates());
        $this->assertNotSame(finding::STATUS_ERROR, $finding->status);
    }
}
