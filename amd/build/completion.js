/*
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Handles slideshow completion tracking via AJAX.
 * v1.3.0: Added voiceover-finished gating support.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 */

define(["core/ajax", "core/notification", "jquery"], (ajax, notification, $) => {
  const init = (cmid, slideinstance, initialviewed) => {

    const carouselElem = $("#nct-slideshow-carousel");
    var slidecount = document.querySelector(".nct-slideshow-carousel").dataset.slidecount;
    var activityRegion = '.activity-header[data-for="page-activity-header"]';

    slidecount = parseInt(slidecount, 10);

    if (!carouselElem.length) {
      return;
    }

    if (isNaN(slidecount) || slidecount <= 0) {
      return;
    }

    carouselElem.on("slid.bs.carousel", (e) => {
      if (!e.relatedTarget) {
        return;
      }

      const newSlideIndex = $(e.relatedTarget).data("slide-index");
      const newSlideCount = newSlideIndex + 1;

      if (newSlideCount > initialviewed) {

        ajax.call([
          {
            methodname: "mod_slideshow_view_slide",
          args: {
            slideinstance: slideinstance,
            slideindex: newSlideIndex,
          },

            done: (response) => {
              if (response.status) {
                initialviewed = response.viewed;

                if (response.completed) {
                  const region = document.querySelector(activityRegion);

                  if (region !== null) {
                    var completionEvent = new CustomEvent("core_course:manualcompletiontoggled", {
                      bubbles: true,
                      detail: {
                        cmid: cmid,
                        completed: true,
                      },
                    });

                    region.dispatchEvent(completionEvent);
                  }
                }
              }
            },

            fail: (error) => {
              notification.exception(error);
            },
          },
        ]);
      }
    });
  };

  return {
    init: init,
  };
});
