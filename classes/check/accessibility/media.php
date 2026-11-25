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

namespace local_auditenhance\check\accessibility;

use local_auditenhance\local\content\content_check_base;
use local_auditenhance\local\content\content_item;
use local_auditenhance\local\content\html;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Embedded video and audio without captions or a transcript (best effort).
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media extends content_check_base {
    /** @var string Pattern of video and audio services. */
    public const MEDIA_HOSTS = '#(youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com|soundcloud\.com|kaltura|panopto|vidgrid)#i';

    /** @var string Pattern of media file links. */
    public const MEDIA_FILES = '#\.(mp4|webm|ogv|mov|m4v|mp3|ogg|wav|m4a)(\?.*)?$#i';

    /**
     * Analyse content.
     *
     * @param run_context $context
     * @param content_item $item
     * @param \DOMXPath $xpath
     */
    public function analyse(run_context $context, content_item $item, \DOMXPath $xpath): void {
        $root = $this->query($xpath, '//div[@id="auditenhance-root"]');
        $hastranscript = $root && preg_match('/transcript|captions|subtitles/i', html::text($root[0]));
        foreach ($this->query($xpath, '//video') as $video) {
            if (!$this->query($xpath, './/track[@kind="captions" or @kind="subtitles"]', $video)) {
                $this->report($context, $item, 'nocaptions', severity::MEDIUM, $video);
            }
        }
        if ($hastranscript) {
            return;
        }
        foreach ($this->query($xpath, '//audio') as $audio) {
            $this->report($context, $item, 'notranscript', severity::LOW, $audio);
        }
        foreach ($this->query($xpath, '//iframe[@src]|//embed[@src]|//object[@data]') as $embed) {
            $src = $embed->getAttribute('src') ?: $embed->getAttribute('data');
            if (preg_match(self::MEDIA_HOSTS, $src)) {
                $this->report($context, $item, 'notranscript', severity::LOW, $embed);
            }
        }
        foreach ($this->query($xpath, '//a[@href]') as $link) {
            $href = $link->getAttribute('href');
            if (preg_match(self::MEDIA_FILES, $href) || preg_match(self::MEDIA_HOSTS, $href)) {
                $this->report($context, $item, 'notranscript', severity::LOW, $link);
            }
        }
    }
}
