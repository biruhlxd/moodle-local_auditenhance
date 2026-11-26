# Changes

## 1.2.2 (2026-11-26)

- Plugin risk: file paths are shown with forward slashes on Windows servers too (they started with a backslash),
  so findings and skipped third-party folders match.
- Test for unused add-ons no longer assumes a site with only standard plugins.

## 1.2.1 (2026-12-02)

- "Fix this" is only shown on failed findings, not on passed or skipped ones.

## 1.2.0 (2026-12-01)

Nine new checks (70 in total):

- Security: multi-factor authentication, web service tokens (administrator tokens that never expire, unused
  tokens), PHP version support.
- Performance and storage: free disk space of the Moodle data folder.
- Site hygiene: empty course categories, visible courses without enrolments.
- Accessibility: embedded frames without a title, video and audio that play on their own.
- Plugin risk: add-on plugins whose version is many years old.

New thresholds: unused token (days), low disk space (%), old plugin version (years).

## 1.1.0 (2026-11-28)

- Mute a single item (for example one image or one line of plugin code) or every item of one plugin for a check,
  with a reason. Muted items are left out of counts and scores from the next audit on, and listed under Muted
  checks > Muted items, where they can be unmuted.
- Findings show how many items were left out because they are muted.
- Each stored item has a stable key (new `itemkey` column), so the same item can be recognised across runs.
- New events: audit item muted and unmuted. The privacy provider covers item mutes.

## 1.0.1 (2026-11-27)

- Finding summaries are stored as language string references and shown in the reader's language. Summaries stored
  by 1.0.0 still show as they were.
- Count messages read correctly for any number ("Images without useful alternative text: 1").
- New setting "Runs with item details" (default 5): individual items are kept for the latest runs of each area only.
  Scores and findings of older runs stay for the history.
- Runs stuck in the queue are now marked failed even when "Runs to keep" is 0.
- Test fixture plugins with deliberately unsafe code are stored as .php.txt files and copied when the tests run.

## 1.0.0 (2026-11-27)

First stable release.

- Five audit areas with 61 checks: security and configuration, performance and storage, site hygiene, accessibility
  and plugin risk.
- Severity, explanation, fix steps and a link for every finding.
- Scores per area and overall, with area weights and score penalties set by the administrator.
- Muting checks with a reason; muted checks are listed separately.
- Screens:
  - overview;
  - one page per area;
  - accessibility by course;
  - plugin risk by plugin;
  - muted checks;
  - settings;
  - an accessibility view for editing teachers.
- Accessibility results are taken from the Brickfield toolkit when it is enabled and has results. Otherwise the
  plugin scans content itself, in batches.
- Plugin code scan of add-on plugins (standard plugins optional), and a scan after a plugin is installed or upgraded.
- Audits run as a batched ad hoc task, from the overview, and from the command line.
- Quick checks are also shown in Moodle's Security checks and Performance overview reports.
- Extensible through the Hooks API: areas, checks, scanners, content sources, screens, export formats, finding
  actions and screen content.
- Privacy provider, PHPUnit and Behat tests.
