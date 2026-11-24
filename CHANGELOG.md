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

