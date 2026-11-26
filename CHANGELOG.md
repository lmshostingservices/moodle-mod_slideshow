## [v1.7.0] - 2026-11-26

### Added
- Bring your own AI: with an LMS Labs Bring your own AI licence and Voice set to the site's own key in AI Grader Central Config, slide narration runs on the site's own AI instead of LMS Labs credits. The slide's text is read by the site's own Vision AI when that is set up, otherwise by LMS Labs free of credits. The service's narration route (reading, tidying and speaking a slide) is ported into the plugin and was checked against the service source on 12,500 randomised cases with identical results.
- The voiceover panel and the activity settings say when narration runs on the site's own AI and drop the per-slide credit cost.

## [v1.6.34] - 2026-11-24

### Fixed
- Fixed: adding or editing the activity failed on Moodle 4.0 to 4.2 with "Call to undefined method ... get_suffix()". The completion settings now use the 4.3+ field suffix only where Moodle has it.
- Fixed: "Undefined property: stdClass::$slideimages" warning when an AI Slideshow was created or updated without the edit form (web services, course tools, data generators). Slide images are only saved when the form sends them, and existing images are left alone.
- Added a test data generator, so the activity can be created by Moodle's automated tests and by demo and course-building tools. Tests cover creating, listing, deleting, backing up and restoring the activity.

## [v1.6.33] - 2026-11-05

### Fixed
- Moodle 5.3: opening an AI Slideshow no longer fails with "Coding error detected... The course_module_viewed event must define objectid and object table". The viewed event now declares its object table.
- Added the standard activity list page (index.php) and its instance-list-viewed event, so "all slideshows in this course" links no longer return 404.

## [v1.6.32] - 2026-10-29

### Fixed
- Moodle's “View the activity” completion now works alongside slide-completion rules: the activity records its viewed event and marks the module as viewed when a permitted user opens it.

## [v1.6.31] - 2026-08-29

### Changed
- RELEASE RECOVERY: Republished the reviewed authoritative source under a new immutable tag because the historical tag contained a different source tree. No functional changes.

# Changelog - mod_slideshow

