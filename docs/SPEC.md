# Audit & Enhance (`local_auditenhance`): specification

Status: draft 1, 2026-11-26. Target: Moodle 4.5 LTS to 5.3, PHP 8.1+. Licence: GPL v3 or later.

## 1. Purpose

One report that answers "how healthy is this Moodle site, what should I fix first, and how?" Admins and Moodle
partners today check this by hand across several core reports. This plugin gives an overall score, a score per area,
and for every finding: why it matters, how bad it is, how to fix it, and a direct link to the setting or content.

## 2. Areas and checks

Severity on every finding: **critical**, **high**, **medium**, **low**, **info**. Every check is a class built on
Moodle's `\core\check\check` API, so it also shows in the matching core report where that makes sense.

### 2.1 Security and configuration
- Debug display on, cron not running or late, HTTPS not forced, weak password policy, self-registration open without
  captcha or allowed email domains, guest login on, open-to-crawlers on for a private site.
- Too many site admins, admin accounts without recent login, the default "admin" username still in use.
- Roles with risky capabilities given to authenticated user or guest.
- Moodle core version out of support; plugins with available updates.

### 2.2 Performance and storage
- Caching (MUC) on defaults only, no OPcache, session handler on the database for a large site.
- Largest courses and files, orphaned files, old automated backups kept indefinitely, unlimited log retention.
- Failed or slow scheduled tasks, ad hoc task backlog.

### 2.3 Site hygiene
- Users: never logged in, inactive for N months, suspended but still enrolled, duplicate emails.
- Courses: empty, no teacher, no access for N months, end date passed but still visible, completion tracking off.
- Unused plugins installed (no instances), disabled plugins still installed.

### 2.4 Accessibility
When the Brickfield toolkit (`tool_brickfield`, in core) is enabled and has results, read them instead of scanning
again and map them to the checks below. When it is off, scan text areas in course summaries, sections, pages, labels,
books, forum intros and quiz questions, in batches.
- Images without alt text or with filenames as alt text.
- Headings skipped or out of order; text styled as headings.
- Link text such as "click here", "read more" or bare URLs.
- Tables without header cells or captions.
- Low contrast from inline colour styles; theme brand colour contrast.
- Embedded videos with no captions track or transcript link (best effort).
- Uploaded PDFs and Office files listed for manual review.
- Site: language set on the HTML element, accessibility statement page present.

Shows counts per course and a sample of items. Editing teachers see this for their own courses.

### 2.5 Plugin risk
Scans the code of third-party plugins (core and this plugin left out by default; a setting includes core).
- **Privacy:** tables with personal data fields (email, IP, names, free text tied to a user ID) but no privacy
  provider, a `null_provider` on a plugin that stores user data, metadata that doesn't list its tables.
- **Dependencies:** plugin not marked as supporting this Moodle version, deprecated or removed core APIs in the code,
  missing required plugins.
- **Code structure:** unsafe PHP functions (`eval`, `exec`, `shell_exec`, `system`, `unserialize` on input), raw
  `$_GET`/`$_POST`, SQL built by string joins, missing `version.php`, licence header or lang strings.
- **Capabilities:** risky capabilities (XSS, config, personal data, DoS flags) given by default to students, guests or
  authenticated users; site overrides that do the same; a role able to assign a higher role.
- **Correlation:** raise severity when findings combine (personal data + no privacy provider + capability open to
  students).
- Scan on demand, from the CLI, and optionally when a plugin is installed or upgraded (setting, off by default).

Shows findings per plugin with severity.

## 3. Scoring

- Each check returns a result and a severity. Area score = 100 minus weighted penalties, floored at 0.
- Overall score = weighted average of area scores (weights in settings, sensible defaults).
- Checks that don't apply are skipped, not counted as passes.
- An admin can mute a check with a reason; muted checks are listed separately.

## 4. Screens

1. **Overview:** overall score, area scores, top 10 things to fix, "Run audit now", date of last run, scan progress.
2. **Area page:** findings grouped by severity, each with explanation, fix steps and a link.
3. **Accessibility by course:** issue counts per course and sample items.
4. **Plugin risk by plugin:** findings per plugin.
5. **Settings:** thresholds (for example "inactive" = 12 months), area weights, muted checks, include core plugins,
   scan on install.

## 5. Running audits

- Quick checks (settings, counts) run when the overview loads, cached for a short time.
- Content and code scans run as an ad hoc task in batches so large sites don't time out.
- CLI: `php local/auditenhance/cli/run_audit.php [--area=<name>]`.
- Results are stored per run.

## 6. Extensibility

Other plugins can add checks and whole areas through a hook (Moodle 4.4+ Hooks API) and a registry of check classes.
Areas, screens and scoring must not assume a fixed list of areas.

## 7. Technical notes

- Checks: one class per check under `classes/check/<area>/`, registered through the core check API callbacks and the
  plugin registry.
- Tables: `local_auditenhance_run` (id, started, finished, score, status), `local_auditenhance_result` (runid, check,
  area, severity, status, summary, details JSON), `local_auditenhance_item` (runid, check, contextid, component,
  itemid, field, issue), `local_auditenhance_mute` (check, reason, userid, timecreated).
- Capabilities: `local/auditenhance:view` and `local/auditenhance:run` (manager), `local/auditenhance:viewcourse`
  (editing teacher, own course accessibility only).
- Privacy provider: user IDs on mutes and on content items owned by a user; export and delete these.
- Content scans use recordsets in batches; nothing heavy on page load.
- Moodle coding standard (phpcs), PHPUnit tests for every check, Behat for the main screens. No GitHub Actions: tests
  run locally.

## 8. First release (1.0)

Areas 2.1 to 2.5, scoring, the five screens, ad hoc scanning, CLI, privacy provider, tests. Then a Moodle plugins
directory listing.
