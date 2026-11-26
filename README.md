# Audit & Enhance for Moodle

A site health audit for Moodle. It checks security and configuration, performance and storage, site hygiene,
accessibility and plugin risk. You get an overall score, a score per area, and clear fix steps for every finding.

Installs as `local/auditenhance`. Works with Moodle 4.5 LTS to 5.3, PHP 8.1 or later.

## What it checks

| Area | Examples |
|---|---|
| Security and configuration | Debug display, cron, HTTPS, password policy, self-registration, guest access, search engines on a private site, site administrators, the "admin" username, risky permissions for everyone, Moodle and PHP version support, plugin updates, multi-factor authentication, web service tokens |
| Performance and storage | Cache stores, OPcache, database sessions on large sites, largest courses and files, orphaned files, backup and log retention, failing and slow tasks, ad hoc task queue, free disk space |
| Site hygiene | Accounts never used or inactive, suspended users still enrolled, duplicate emails, empty courses, courses without a teacher or visits, ended courses still visible, completion tracking, empty categories, courses without enrolments, unused or disabled add-ons |
| Accessibility | Image alternative text, headings, link text, tables, text contrast, video captions, frame titles, autoplaying media, documents to review, page language, accessibility statement, theme colour contrast |
| Plugin risk | Privacy providers and metadata, supported versions, deprecated APIs, missing dependencies, unsafe functions, raw request data, SQL built from variables, licence headers, language strings, plugins not updated for years, risky default permissions, permission overrides, role escalation, combined risks |

Every finding has a severity (critical, high, medium, low, info), says why it matters and how to fix it, and links to
the setting or content to change.

When the Brickfield accessibility toolkit is enabled and has results, the accessibility area reads its results instead
of scanning the same content again.

## Screens

Site administration > Reports > Audit & Enhance:

- **Site audit**: overall score, area scores, the top 10 things to fix, "Run audit now" and scan progress.
- **One page per area**: findings by severity, with explanations, fix steps and links. Checks that don't apply and
  muted checks are listed separately.
- **Accessibility by course**: issue counts per course and sample items.
- **Plugin risk by plugin**: findings per plugin with severity.
- **Muted checks**: checks and single items an administrator has muted, with the reason. Single items (or all items
  of one plugin for a check) are muted from the plugin and course item lists.
- **Settings**: thresholds, area weights, score penalties, which plugins to scan, scan options.

Editing teachers find **Accessibility audit** in their course's "More" menu. It shows the issues in their own course.

Quick checks also appear in Moodle's own Security checks and Performance overview reports.

## Running audits

- **From the overview**: "Run audit now" queues an ad hoc task, so cron must be running. Large sites are scanned in
  batches across several task runs. The time per run and the batch sizes are settings.
- **From the command line**:

```bash
php local/auditenhance/cli/run_audit.php
```

```bash
php local/auditenhance/cli/run_audit.php --area=security,pluginrisk
```

```bash
php local/auditenhance/cli/run_audit.php --list
```

- **After a plugin is installed or upgraded** (setting, off by default): a plugin risk scan is queued.

Results are stored per run. Old runs are deleted after the number set in "Runs to keep".

## Scoring

Each area starts at 100. Each failed check takes off points by severity (critical 30, high 15, medium 7, low 3,
info 0, all set in Settings), down to 0. Checks that don't apply, could not run or are muted are not counted. The
overall score is the weighted average of the area scores. Area weights are settings.

## Capabilities

| Capability | Default | Allows |
|---|---|---|
| `local/auditenhance:view` | Manager | Viewing the audit screens |
| `local/auditenhance:run` | Manager | Running audits and muting checks |
| `local/auditenhance:viewcourse` | Editing teacher, Manager | Viewing the accessibility audit of a course |

## Privacy

The plugin stores user ids:

- on mutes and runs (who did it);
- on content items whose owner is known (for example the author of a question).

The privacy provider exports this data. On a deletion request, mutes and runs are kept without the user, and items are
deleted.

## For developers: extending the audit

Everything the audit knows about is registered through the Hooks API, including this plugin's own areas and checks.
Add a callback in your plugin's `db/hooks.php`:

```php
$callbacks = [
    [
        'hook' => \local_auditenhance\hook\register_extensions::class,
        'callback' => [\local_example\audit::class, 'register'],
    ],
];
```

```php
public static function register(\local_auditenhance\hook\register_extensions $hook): void {
    $hook->add_area(new \local_auditenhance\local\area('example', 'local_example', 10, 60));
    $hook->add_check(\local_example\check\example\mycheck::class);
}
```

The hook takes:

- `add_area()`: a new area with its own score, weight setting, tab and page.
- `add_check()` / `add_checks()`: check classes. They extend `\local_auditenhance\check\base` (a `\core\check\check`)
  and live in `classes/check/<area>/`. Strings: `check_<id>`, `check_<id>_why`, `check_<id>_fix`.
- `remove_check()`: leave out a check.
- `add_scanner()`: a batched scanner that feeds checks implementing its interface.
- `add_content_source()`: more HTML fields for the content scan, fed to `content_check` checks.
- `add_screen()`: a new tab.
- `add_export_format()`: download buttons on screens whose scope it supports.
- `add_finding_action()`: links or buttons shown next to each finding.

To add HTML to the top or bottom of any screen, use the `\local_auditenhance\hook\extend_screen` hook.

To react to finished runs or to mutes, listen to the events `audit_started`, `audit_completed`, `check_muted` and
`check_unmuted`.

To start runs from your own code, use `\local_auditenhance\local\run_manager::queue()`.

## Tests

```bash
vendor/bin/phpunit --testsuite local_auditenhance_testsuite
```

```bash
vendor/bin/behat --config <behat dataroot>/behatrun/behat/behat.yml --tags=@local_auditenhance
```

## Licence

GNU GPL v3 or later. © 2026 Biruh Alemnew.
