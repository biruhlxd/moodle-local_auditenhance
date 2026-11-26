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

namespace local_auditenhance\local\plugins;

/**
 * A plugin being scanned, with lazily read code, tables and capabilities.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scanned_plugin {
    /** @var string[] Directories never scanned for code. */
    public const SKIP_DIRS = ['tests', 'vendor', 'node_modules', '.git', 'amd', 'yui', 'behat', 'fixtures', 'thirdparty'];

    /** @var int Files larger than this are not tokenised. */
    public const MAX_FILE_SIZE = 1048576;

    /** @var string[] Field names that hold personal data. */
    public const PERSONAL_FIELDS = ['userid', 'email', 'ip', 'lastip', 'ipaddress', 'firstname', 'lastname', 'username',
        'phone', 'phone1', 'phone2', 'address', 'relateduserid', 'authorid', 'teacherid', 'studentid', 'graderid',
        'senderid', 'recipientid'];

    /** @var string[]|null Relative paths of PHP files. */
    protected ?array $phpfiles = null;

    /** @var array|null Tables from install.xml. */
    protected ?array $tables = null;

    /** @var array<string, array> Tokens per file. */
    protected array $tokens = [];

    /** @var string[]|null Paths of third party libraries declared by the plugin. */
    protected ?array $thirdparty = null;

    /**
     * Constructor.
     *
     * @param \core\plugininfo\base $info
     */
    public function __construct(
        /** @var \core\plugininfo\base Plugin information. */
        public readonly \core\plugininfo\base $info,
    ) {
    }

    /**
     * Frankenstyle component.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->info->component;
    }

    /**
     * Display name with component.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->info->displayname . ' (' . $this->info->component . ')';
    }

    /**
     * Root directory.
     *
     * @return string
     */
    public function get_rootdir(): string {
        return (string)$this->info->rootdir;
    }

    /**
     * Relative paths of the plugin's PHP files, without tests, vendor code and declared third party libraries.
     *
     * @return string[]
     */
    public function get_php_files(): array {
        if ($this->phpfiles !== null) {
            return $this->phpfiles;
        }
        $this->phpfiles = [];
        $root = $this->get_rootdir();
        if (!is_dir($root)) {
            return $this->phpfiles;
        }
        $thirdparty = array_merge($this->get_thirdparty_paths(), $this->get_subplugin_paths());
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $file) use ($root, $thirdparty): bool {
                    $relative = self::relative_path($file->getPathname(), $root);
                    foreach ($thirdparty as $path) {
                        if ($relative === $path || str_starts_with($relative, $path . '/')) {
                            return false;
                        }
                    }
                    if ($file->isDir()) {
                        return !in_array($file->getFilename(), self::SKIP_DIRS, true);
                    }
                    return strtolower($file->getExtension()) === 'php';
                }
            )
        );
        foreach ($iterator as $file) {
            $this->phpfiles[] = self::relative_path($file->getPathname(), $root);
        }
        sort($this->phpfiles);
        return $this->phpfiles;
    }

    /**
     * Path of a file relative to the root, with forward slashes (Windows paths use backslashes).
     *
     * @param string $path
     * @param string $root
     * @return string
     */
    private static function relative_path(string $path, string $root): string {
        return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    }

    /**
     * Folders of this plugin's subplugin types, relative to the root: subplugins are scanned on their own.
     *
     * @return string[]
     */
    public function get_subplugin_paths(): array {
        $root = rtrim($this->get_rootdir(), '/') . '/';
        $types = \core_component::get_plugin_types();
        $paths = [];
        foreach (array_keys((array)\core_component::get_subplugins($this->get_component())) as $type) {
            if (isset($types[$type]) && str_starts_with($types[$type] . '/', $root)) {
                $paths[] = trim(substr($types[$type], strlen($root)), '/');
            }
        }
        return $paths;
    }

    /**
     * Paths declared in thirdpartylibs.xml.
     *
     * @return string[]
     */
    public function get_thirdparty_paths(): array {
        if ($this->thirdparty !== null) {
            return $this->thirdparty;
        }
        $this->thirdparty = [];
        $file = $this->get_rootdir() . '/thirdpartylibs.xml';
        if (is_readable($file)) {
            $xml = @simplexml_load_file($file);
            if ($xml) {
                foreach ($xml->library as $library) {
                    $this->thirdparty[] = trim((string)$library->location, '/');
                }
            }
        }
        return $this->thirdparty;
    }

    /**
     * Contents of a file.
     *
     * @param string $relative
     * @return string
     */
    public function read_file(string $relative): string {
        $path = $this->get_rootdir() . '/' . $relative;
        if (!is_readable($path) || filesize($path) > self::MAX_FILE_SIZE) {
            return '';
        }
        return (string)file_get_contents($path);
    }

    /**
     * PHP tokens of a file, without white space and comments.
     *
     * @param string $relative
     * @return array List of [id, text, line].
     */
    public function get_tokens(string $relative): array {
        if (!isset($this->tokens[$relative])) {
            $this->tokens[$relative] = code_analyser::tokenise($this->read_file($relative));
        }
        return $this->tokens[$relative];
    }

    /**
     * Tables declared in db/install.xml.
     *
     * @return array<string, string[]> Table name => field names.
     */
    public function get_tables(): array {
        if ($this->tables !== null) {
            return $this->tables;
        }
        $this->tables = [];
        $file = $this->get_rootdir() . '/db/install.xml';
        if (!is_readable($file)) {
            return $this->tables;
        }
        $xml = @simplexml_load_file($file);
        if (!$xml || !isset($xml->TABLES)) {
            return $this->tables;
        }
        foreach ($xml->TABLES->TABLE as $table) {
            $fields = [];
            foreach ($table->FIELDS->FIELD as $field) {
                $fields[] = strtolower((string)$field['NAME']);
            }
            $this->tables[(string)$table['NAME']] = $fields;
        }
        return $this->tables;
    }

    /**
     * Tables that hold personal data: user fields, or free text next to a user id.
     *
     * @return array<string, string[]> Table name => personal fields.
     */
    public function get_personal_tables(): array {
        $result = [];
        foreach ($this->get_tables() as $table => $fields) {
            $personal = array_values(array_filter(
                $fields,
                fn(string $f) => in_array($f, self::PERSONAL_FIELDS, true) || str_ends_with($f, 'userid')
            ));
            if ($personal) {
                $result[$table] = $personal;
            }
        }
        return $result;
    }

    /**
     * Class name of the privacy provider, if any.
     *
     * @return string|null
     */
    public function get_privacy_provider(): ?string {
        $class = $this->get_component() . '\privacy\provider';
        if (class_exists($class)) {
            return $class;
        }
        return file_exists($this->get_rootdir() . '/classes/privacy/provider.php') ? $class : null;
    }

    /**
     * Capabilities defined in db/access.php.
     *
     * @return array<string, array>
     */
    public function get_capabilities(): array {
        $file = $this->get_rootdir() . '/db/access.php';
        if (!is_readable($file)) {
            return [];
        }
        $capabilities = [];
        include($file);
        return is_array($capabilities) ? $capabilities : [];
    }
}
