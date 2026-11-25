# Changes

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
