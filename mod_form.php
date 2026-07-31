<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Slideshow v1.6.26 - Activity settings form
 * Adds Video slide and Poster image slide management (AJAX-based, position-selectable).
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd
 * @author      LMSACE
 */

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_slideshow_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $PAGE, $DB;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ---- IMAGES / PPTX IMPORT ----
        $mform->addElement('header', 'contentheader', get_string('images', 'slideshow'));

        $siteid    = '';
        $apikey    = '';
        if (file_exists($CFG->dirroot . '/local/aiconfig/version.php')) {
            $siteid = get_config('local_aiconfig', 'siteid') ?: '';
            $apikey = get_config('local_aiconfig', 'apikey') ?: '';
        }
        if (empty($siteid)) { $siteid = get_config('mod_slideshow', 'siteid') ?: ''; }
        if (empty($apikey)) { $apikey = get_config('mod_slideshow', 'apikey') ?: ''; }
        $serverurl = rtrim(get_config('local_aiconfig', 'serverurl') ?: 'https://lms-labs.com', '/');

        $pptxcmid = 0;
        if (!empty($this->current->coursemodule)) {
            $pptxcmid = (int)$this->current->coursemodule;
        } elseif (!empty($this->_cm->id)) {
            $pptxcmid = (int)$this->_cm->id;
        }

        $pptxhtml = '<div id="slideshow-pptx-import" class="slideshow-pptx-import" '
            . 'data-siteid="' . s($siteid) . '" '
            . 'data-apikey="' . s($apikey) . '" '
            . 'data-serverurl="' . s($serverurl) . '" '
            . 'data-cmid="' . $pptxcmid . '">'
            . '<div class="slideshow-pptx-dropzone" id="slideshow-pptx-dropzone">'
            . '<div class="slideshow-pptx-dropzone-inner">'
            . '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>'
            . '<polyline points="14 2 14 8 20 8"/>'
            . '<path d="M12 18v-6"/><path d="m9 15 3-3 3 3"/>'
            . '</svg>'
            . '<p class="slideshow-pptx-dropzone-text">' . get_string('importpptxdrag', 'slideshow') . '</p>'
            . '<p class="slideshow-pptx-dropzone-formats">' . get_string('importpptxformats', 'slideshow') . '</p>'
            . '<p class="slideshow-pptx-dropzone-sizelimit">' . get_string('importpptxsizelimit', 'slideshow') . '</p>'
            . '<p class="slideshow-pptx-dropzone-compress">' . get_string('importpptxcompress', 'slideshow') . '</p>'
            . '<input type="file" id="slideshow-pptx-file-input" accept=".pptx,.ppt,.png,.jpg,.jpeg,.gif,.webp,.svg" multiple style="display:none">'
            . '</div>'
            . '</div>'
            . '<div class="slideshow-pptx-progress" id="slideshow-pptx-progress" style="display:none">'
            . '<div class="slideshow-pptx-progress-bar-bg">'
            . '<div class="slideshow-pptx-progress-bar" id="slideshow-pptx-progress-bar"></div>'
            . '</div>'
            . '<span class="slideshow-pptx-progress-text" id="slideshow-pptx-progress-text"></span>'
            . '</div>'
            . '<div class="slideshow-pptx-result" id="slideshow-pptx-result" style="display:none"></div>'
            . '</div>';

        $mform->addElement('static', 'pptximport', get_string('importpptx', 'slideshow'), $pptxhtml);
        $mform->addHelpButton('pptximport', 'importpptx', 'slideshow');

        $mform->addElement(
            'filemanager',
            'slideimages',
            get_string('images', 'slideshow'),
            null,
            [
                'subdirs' => 0,
                'maxfiles' => -1,
                'accepted_types' => ['image', '.svg', '.webp'],
                'return_types' => FILE_INTERNAL,
                'preservetimestamps' => true,
            ]
        );
        $mform->addHelpButton('slideimages', 'images', 'slideshow');
        $mform->addRule('slideimages', get_string('required'), 'required', null, 'client');

        // ---- SLIDE REORDER SORTER ----
        $mform->addElement('header', 'reorderheader',
            get_string('reorderslides', 'slideshow', $this->current->name ?? '')
        );

        $mform->addElement('hidden', 'orderjson', '');
        $mform->setType('orderjson', PARAM_RAW);
        $mform->setDefault('orderjson', '[]');
        $mform->getElement('orderjson')->updateAttributes(['id' => 'id_orderjson']);

        $totalslidecount = 0; // used below for position dropdown

        if (empty($this->current->id)) {
            $mform->addElement('static', 'reorderhint', '', get_string('noimages', 'slideshow'));
        } else {
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $this->context->id, 'mod_slideshow', 'slideimages', $this->current->id,
                'sortorder, id', false
            );

            // Get extra slides (video + poster) for merged sorter
            $extraslides_form = array_values($DB->get_records(
                'slideshow_extra_slides', ['slideshowid' => $this->current->id], 'sortorder, id'
            ));

            // Build merged list sorted by sortorder
            $allitems = [];
            foreach ($files as $file) {
                $allitems[] = (object)['type' => 'file', 'sortorder' => (int)$file->get_sortorder(), 'data' => $file];
            }
            foreach ($extraslides_form as $extra) {
                $allitems[] = (object)['type' => 'extra', 'sortorder' => (int)$extra->sortorder, 'data' => $extra];
            }
            usort($allitems, function ($a, $b) {
                if ($a->sortorder !== $b->sortorder) return $a->sortorder - $b->sortorder;
                return ($a->type === 'file') ? -1 : 1;
            });
            $totalslidecount = count($allitems);

            if (empty($files) && empty($extraslides_form)) {
                $mform->addElement('static', 'reorderhint', '', get_string('noimages', 'slideshow'));
            } else {
                $html = html_writer::start_div('slideshow-sorter-wrapper');
                $html .= html_writer::tag('p', get_string('dragreorderhelp', 'slideshow'),
                    ['class' => 'slideshow-sorter-help']);

                $cm = get_coursemodule_from_instance('slideshow', $this->current->id);

                $html .= html_writer::start_tag('ul', [
                    'id'         => 'slideshow-sorter',
                    'class'      => 'slideshow-sorter',
                    'data-input' => 'id_orderjson',
                    'data-cmid'  => $cm->id,
                ]);

                foreach ($allitems as $item) {
                    if ($item->type === 'file') {
                        $file     = $item->data;
                        $filename = $file->get_filename();
                        $imgurl   = moodle_url::make_pluginfile_url(
                            $this->context->id, 'mod_slideshow', 'slideimages', $this->current->id,
                            '/', $filename
                        );

                        $thumb = html_writer::empty_tag('img', [
                            'src'         => $imgurl,
                            'alt'         => s($filename),
                            'draggable'   => 'false',
                            'ondragstart' => 'return false',
                            'style'       => 'pointer-events:none;-webkit-user-drag:none;user-select:none;max-width:100%;height:auto;',
                        ]);

                        $controls = html_writer::div(
                            html_writer::tag('button', '&#8593;', ['type' => 'button', 'class' => 'ss-move ss-up', 'aria-label' => get_string('moveup')]) .
                            html_writer::tag('button', '&#8595;', ['type' => 'button', 'class' => 'ss-move ss-down', 'aria-label' => get_string('movedown')]) .
                            html_writer::tag('button', '&#10514;', ['type' => 'button', 'class' => 'ss-move ss-top', 'title' => get_string('movetotop', 'slideshow')]) .
                            html_writer::tag('button', '&#10515;', ['type' => 'button', 'class' => 'ss-move ss-bottom', 'title' => get_string('movetobottom', 'slideshow')]),
                            'slideshow-sorter-controls'
                        );

                        $handle = html_writer::tag('span', '', [
                            'class'      => 'ss-handle',
                            'aria-label' => get_string('move'),
                            'title'      => get_string('move'),
                        ]);

                        $inner = html_writer::div(
                            $handle .
                            $thumb .
                            html_writer::tag('span', s($filename), ['class' => 'slideshow-sorter-name']) .
                            $controls,
                            'slideshow-sorter-item-inner'
                        );

                        $html .= html_writer::tag('li', $inner, [
                            'class'       => 'slideshow-sorter-item',
                            'data-key'    => $file->get_pathnamehash(),
                            'data-filename' => $filename,
                            'aria-grabbed'  => 'false',
                        ]);

                    } else {
                        // Extra slide item (video or poster)
                        $extra = $item->data;
                        $isVideo  = ($extra->slidetype === 'video');
                        $cmidval  = $cm->id;
                        $extraid  = (int)$extra->id;

                        // Type badge
                        $typeIcon = $isVideo
                            ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>'
                            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
                        $typelabel = $isVideo ? 'VIDEO' : 'POSTER';
                        $typeBadge = '<span class="ss-extra-type-badge ss-type-' . $extra->slidetype . '">' . $typeIcon . ' ' . $typelabel . '</span>';

                        // Label text
                        if ($isVideo) {
                            $src = ($extra->videosource === 'youtube') ? 'YT' : 'URL';
                            $rawlabel = $extra->title ?: $extra->videourl;
                        } else {
                            $src = '';
                            $rawlabel = $extra->title ?: $extra->imagefilename;
                        }
                        $labeltext = ($src ? "[$src] " : '') . (strlen($rawlabel) > 48 ? substr($rawlabel, 0, 48) . '…' : $rawlabel);

                        // Delete button
                        $deleteBtn = html_writer::tag('button', 'Delete', [
                            'type'    => 'button',
                            'class'   => 'ss-extra-delete-inline btn btn-sm btn-outline-danger',
                            'onclick' => "ssDeleteExtraSlide($extraid, $cmidval)",
                            'title'   => 'Delete this slide',
                        ]);

                        $editBtnAttrs = [
                            'type'       => 'button',
                            'class'      => 'ss-extra-edit-inline btn btn-sm btn-outline-secondary',
                            'onclick'    => 'ssEditSlideFromBtn(this)',
                            'title'      => 'Edit this slide',
                            'data-id'    => (string)$extraid,
                            'data-type'  => $isVideo ? 'video' : 'poster',
                            'data-src'   => $isVideo ? ($extra->videosource ?? 'youtube') : '',
                            'data-url'   => $isVideo ? ($extra->videourl ?? '') : '',
                            'data-title' => $extra->title ?? '',
                            'data-mw'    => $isVideo ? (string)(int)($extra->videominwatch ?? 0) : '0',
                        ];
                        $editBtn = html_writer::tag('button', 'Edit', $editBtnAttrs);

                        $controls = html_writer::div(
                            html_writer::tag('button', '&#8593;', ['type' => 'button', 'class' => 'ss-move ss-up', 'aria-label' => get_string('moveup')]) .
                            html_writer::tag('button', '&#8595;', ['type' => 'button', 'class' => 'ss-move ss-down', 'aria-label' => get_string('movedown')]) .
                            html_writer::tag('button', '&#10514;', ['type' => 'button', 'class' => 'ss-move ss-top', 'title' => get_string('movetotop', 'slideshow')]) .
                            html_writer::tag('button', '&#10515;', ['type' => 'button', 'class' => 'ss-move ss-bottom', 'title' => get_string('movetobottom', 'slideshow')]) .
                            '&nbsp;' . $editBtn . '&nbsp;' . $deleteBtn,
                            'slideshow-sorter-controls'
                        );

                        $handle = html_writer::tag('span', '', [
                            'class'      => 'ss-handle',
                            'aria-label' => get_string('move'),
                            'title'      => get_string('move'),
                        ]);

                        $inner = html_writer::div(
                            $handle .
                            $typeBadge .
                            html_writer::tag('span', s($labeltext), ['class' => 'slideshow-sorter-name ss-extra-name']) .
                            $controls,
                            'slideshow-sorter-item-inner slideshow-sorter-extra-inner'
                        );

                        $html .= html_writer::tag('li', $inner, [
                            'class'         => 'slideshow-sorter-item ss-extra-sorter-item ss-extra-' . s($extra->slidetype),
                            'data-key'      => 'extra_' . $extraid,
                            'data-filename' => 'extra_' . $extraid,
                            'aria-grabbed'  => 'false',
                        ]);
                    }
                }

                $html .= html_writer::end_tag('ul');
                $html .= html_writer::end_div();

                $mform->addElement('static', 'reorderui', '', $html);
            }
        }

        $PAGE->requires->js_call_amd('mod_slideshow/sorter', 'init');
        $PAGE->requires->js_call_amd('mod_slideshow/pptximport', 'init');

        // ---- VIDEO & POSTER SLIDES MANAGER ----
        $mform->addElement('header', 'extraslideheader', get_string('extraslideheader', 'slideshow'));

        if (empty($this->current->id)) {
            $mform->addElement('static', 'extraslidehintnew', '',
                '<div class="alert alert-info mb-0">' . get_string('extraslide_savenewfirst', 'slideshow') . '</div>');
        } else {
            $cm = get_coursemodule_from_instance('slideshow', $this->current->id);
            $ajaxurl = (new moodle_url('/lib/ajax/service.php', ['sesskey' => sesskey()]))->out(false);

            // Build position dropdown options
            $positionopts = '<option value="-1">' . htmlspecialchars(get_string('extraslide_position_end', 'slideshow')) . '</option>'
                . '<option value="0">' . htmlspecialchars(get_string('extraslide_position_beginning', 'slideshow')) . '</option>';
            for ($pos = 1; $pos <= $totalslidecount; $pos++) {
                $positionopts .= '<option value="' . $pos . '">' . htmlspecialchars(get_string('extraslide_position_after', 'slideshow', $pos)) . '</option>';
            }

            $extrahtml = '<div class="ss-extra-manager" id="ss-extra-manager">'

            // Add buttons row
            . '<div class="ss-extra-add-btns">'
            . '<button type="button" class="btn btn-outline-primary" onclick="ssShowPanel(\'video\')">'
            . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>'
            . htmlspecialchars(get_string('extraslide_addvideo', 'slideshow')) . '</button>&nbsp;'
            . '<button type="button" class="btn btn-outline-secondary" onclick="ssShowPanel(\'poster\')">'
            . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'
            . htmlspecialchars(get_string('extraslide_addposter', 'slideshow')) . '</button>'
            . '</div>'

            // Add Video panel
            . '<div class="ss-add-panel" id="ss-add-video-panel" style="display:none">'
            . '<h5 class="ss-panel-title">' . htmlspecialchars(get_string('extraslide_addvideo', 'slideshow')) . '</h5>'
            . '<div class="ss-form-row"><label class="ss-form-label">Video Source</label>'
            . '<select class="form-select" id="ss-video-source" onchange="ssUpdateVideoSourceUI()">'
            . '<option value="youtube">' . htmlspecialchars(get_string('extraslide_source_youtube', 'slideshow')) . '</option>'
            . '<option value="url">' . htmlspecialchars(get_string('extraslide_source_url', 'slideshow')) . '</option>'
            . '</select></div>'
            . '<div class="ss-form-row"><label class="ss-form-label" id="ss-video-url-label">YouTube URL</label>'
            . '<input type="text" class="form-control" id="ss-video-url" placeholder="https://youtube.com/watch?v=..."></div>'
            . '<div class="ss-form-row"><label class="ss-form-label">Title (optional)</label>'
            . '<input type="text" class="form-control" id="ss-video-title" placeholder="Shown below the video player"></div>'
            . '<div class="ss-form-row"><label class="ss-form-label">Watch Requirement</label>'
            . '<select class="form-select" id="ss-video-minwatch" onchange="ssUpdateMinwatchUI()">'
            . '<option value="0">No requirement — student can navigate away immediately</option>'
            . '<option value="-1">Must watch the full video (until end)</option>'
            . '<option value="30">At least 30 seconds</option>'
            . '<option value="60">At least 1 minute (60 s)</option>'
            . '<option value="120">At least 2 minutes (120 s)</option>'
            . '<option value="300">At least 5 minutes (300 s)</option>'
            . '<option value="custom">Custom (enter seconds below)...</option>'
            . '</select>'
            . '<input type="number" class="form-control mt-1" id="ss-video-minwatch-custom" placeholder="Enter seconds" min="1" style="display:none;max-width:200px"></div>'
            . '<div class="ss-form-row" id="ss-video-position-row"><label class="ss-form-label">Insert Position</label>'
            . '<select class="form-select" id="ss-video-position">' . $positionopts . '</select></div>'
            . '<div class="ss-form-actions">'
            . '<button type="button" class="btn btn-primary" id="ss-add-video-btn" onclick="ssAddSlide(\'video\')">Add Video Slide</button>'
            . '&nbsp;<button type="button" class="btn btn-light" onclick="ssHidePanel(\'video\')">Cancel</button>'
            . '</div>'
            . '<div id="ss-video-status" class="ss-add-status"></div>'
            . '</div>'

            // Add Poster panel
            . '<div class="ss-add-panel" id="ss-add-poster-panel" style="display:none">'
            . '<h5 class="ss-panel-title">' . htmlspecialchars(get_string('extraslide_addposter', 'slideshow')) . '</h5>'
            . '<div class="ss-form-row"><label class="ss-form-label">Image File <small class="text-muted">(PNG/JPG/WebP, max 10MB)</small></label>'
            . '<input type="file" class="form-control" id="ss-poster-file" accept="image/png,image/jpeg,image/gif,image/webp" onchange="ssPosterPreview(this)">'
            . '<div id="ss-poster-preview-wrap" style="display:none;margin-top:8px"><img id="ss-poster-preview-img" alt="Preview" style="max-height:120px;border-radius:4px;border:1px solid #dee2e6"></div>'
            . '</div>'
            . '<div class="ss-form-row"><label class="ss-form-label">Title (optional)</label>'
            . '<input type="text" class="form-control" id="ss-poster-title" placeholder="Label shown below the image"></div>'
            . '<div class="ss-form-row" id="ss-poster-position-row"><label class="ss-form-label">Insert Position</label>'
            . '<select class="form-select" id="ss-poster-position">' . $positionopts . '</select></div>'
            . '<div class="ss-form-actions">'
            . '<button type="button" class="btn btn-primary" id="ss-add-poster-btn" onclick="ssAddSlide(\'poster\')">Add Poster Slide</button>'
            . '&nbsp;<button type="button" class="btn btn-light" onclick="ssHidePanel(\'poster\')">Cancel</button>'
            . '</div>'
            . '<div id="ss-poster-status" class="ss-add-status"></div>'
            . '</div>'

            // Inline JS for extra slides manager
            . '<script>(function(){'
            . 'var ssAjaxUrl=' . json_encode($ajaxurl) . ';'
            . 'var ssCmid=' . (int)$cm->id . ';'
            . 'window.ssDeleteExtraSlide=function(extraId,cmid){'
            . 'if(!confirm("Delete this slide? This cannot be undone."))return;'
            . 'fetch(ssAjaxUrl,{method:"POST",headers:{"Content-Type":"application/json"},'
            . 'body:JSON.stringify([{index:0,methodname:"mod_slideshow_delete_extra_slide",args:{cmid:cmid,id:extraId}}])})'
            . '.then(function(r){return r.json();}).then(function(d){'
            . 'var res=d[0];if(res.error)throw new Error(res.error.message||"Error");'
            . 'if(res.data&&res.data.success)window.location.reload();'
            . 'else alert((res.data&&res.data.message)||"Failed");'
            . '}).catch(function(e){alert(e.message||"Error");});};'
            . 'var ssEditingId=0,ssEditOrigBtnText="",ssEditOrigTitle="";'
            . 'window.ssShowPanel=function(type){'
            . 'document.getElementById("ss-add-video-panel").style.display=type==="video"?"block":"none";'
            . 'document.getElementById("ss-add-poster-panel").style.display=type==="poster"?"block":"none";};'
            . 'window.ssHidePanel=function(type){'
            . 'document.getElementById("ss-add-"+type+"-panel").style.display="none";'
            . 'if(ssEditingId){'
            . 'var btn=document.getElementById("ss-add-"+type+"-btn");if(btn&&ssEditOrigBtnText)btn.textContent=ssEditOrigBtnText;'
            . 'var th=document.querySelector("#ss-add-"+type+"-panel .ss-panel-title");if(th&&ssEditOrigTitle)th.textContent=ssEditOrigTitle;'
            . 'var pr=document.getElementById("ss-"+type+"-position-row");if(pr)pr.style.display="";'
            . 'ssEditingId=0;ssEditOrigBtnText="";ssEditOrigTitle="";}};'
            . 'window.ssEditSlideFromBtn=function(b){'
            . 'var id=parseInt(b.dataset.id,10);var type=b.dataset.type||"video";'
            . 'var src=b.dataset.src||"youtube";var url=b.dataset.url||"";'
            . 'var title=b.dataset.title||"";var mw=parseInt(b.dataset.mw||"0",10);'
            . 'ssEditSlide(id,type,src,url,title,mw);};'
            . 'window.ssEditSlide=function(extraId,type,src,url,title,mw){'
            . 'ssEditingId=extraId;'
            . 'ssShowPanel(type);'
            . 'var btn=document.getElementById("ss-add-"+type+"-btn");'
            . 'var th=document.querySelector("#ss-add-"+type+"-panel .ss-panel-title");'
            . 'ssEditOrigBtnText=btn?btn.textContent:"";ssEditOrigTitle=th?th.textContent:"";'
            . 'var pr=document.getElementById("ss-"+type+"-position-row");if(pr)pr.style.display="none";'
            . 'if(btn)btn.textContent="Save Changes";'
            . 'if(th)th.textContent=(type==="video"?"Edit Video Slide":"Edit Poster Slide");'
            . 'if(type==="video"){'
            . 'var se=document.getElementById("ss-video-source");if(se)se.value=src||"youtube";ssUpdateVideoSourceUI();'
            . 'var ue=document.getElementById("ss-video-url");if(ue)ue.value=url||"";'
            . 'var te=document.getElementById("ss-video-title");if(te)te.value=title||"";'
            . 'var ms=document.getElementById("ss-video-minwatch");'
            . 'if(ms){var kv=["0","-1","30","60","120","300"];'
            . 'if(kv.indexOf(String(mw))!==-1){ms.value=String(mw);}else if(mw>0){ms.value="custom";var mc=document.getElementById("ss-video-minwatch-custom");if(mc)mc.value=mw;}else{ms.value="0";}'
            . 'ssUpdateMinwatchUI();}}'
            . 'else if(type==="poster"){var pt=document.getElementById("ss-poster-title");if(pt)pt.value=title||"";}};'
            . 'window.ssUpdateVideoSourceUI=function(){'
            . 'var src=document.getElementById("ss-video-source").value;'
            . 'var lbl=document.getElementById("ss-video-url-label");'
            . 'var inp=document.getElementById("ss-video-url");'
            . 'lbl.textContent=src==="youtube"?"YouTube URL":"Video URL (MP4/WebM/direct link)";'
            . 'inp.placeholder=src==="youtube"?"https://youtube.com/watch?v=...":"https://example.com/video.mp4";};'
            . 'window.ssUpdateMinwatchUI=function(){'
            . 'var v=document.getElementById("ss-video-minwatch").value;'
            . 'document.getElementById("ss-video-minwatch-custom").style.display=v==="custom"?"block":"none";};'
            . 'window.ssPosterPreview=function(inp){'
            . 'var wrap=document.getElementById("ss-poster-preview-wrap");'
            . 'var img=document.getElementById("ss-poster-preview-img");'
            . 'if(inp.files&&inp.files[0]){var r=new FileReader();r.onload=function(e){img.src=e.target.result;wrap.style.display="block";};r.readAsDataURL(inp.files[0]);}};'
            . 'function ssSetStatus(type,msg,isErr){'
            . 'var el=document.getElementById("ss-"+type+"-status");'
            . 'if(el)el.innerHTML="<div class=\\"alert alert-"+(isErr?"danger":"success")+" mt-2 mb-0 py-2\\">"+msg+"</div>";}' 
            . 'function ssCallAjax(m,a){return fetch(ssAjaxUrl,{method:"POST",'
            . 'headers:{"Content-Type":"application/json"},'
            . 'body:JSON.stringify([{index:0,methodname:m,args:a}])})'
            . '.then(function(r){return r.json();}).then(function(d){'
            . 'var res=d[0];if(res.error)throw new Error(res.error.message||"Error");return res.data||res;});}'
            . 'window.ssAddSlide=function(type){'
            . 'ssSetStatus(type,"Saving...",false);'
            . 'if(type==="video"){'
            . 'var src=document.getElementById("ss-video-source").value;'
            . 'var url=document.getElementById("ss-video-url").value.trim();'
            . 'var title=document.getElementById("ss-video-title").value.trim();'
            . 'var mwv=document.getElementById("ss-video-minwatch").value;'
            . 'var mw=mwv==="custom"?(parseInt(document.getElementById("ss-video-minwatch-custom").value,10)||0):parseInt(mwv,10);'
            . 'var pos=parseInt(document.getElementById("ss-video-position").value,10);'
            . 'if(!url){ssSetStatus("video","Please enter a URL.",true);return;}'
            . 'var btn=document.getElementById("ss-add-video-btn");if(btn)btn.disabled=true;'
            . 'ssCallAjax("mod_slideshow_save_extra_slide",{'
            . 'cmid:ssCmid,id:ssEditingId,slidetype:"video",title:title,videosource:src,videourl:url,'
            . 'videominwatch:mw,position:pos,imagebase64:"",imagefilename:""})'
            . '.then(function(d){if(d.success){ssSetStatus("video",(ssEditingId?"Saved":"Added")+"! Reloading...",false);'
            . 'setTimeout(function(){window.location.reload();},700);}'
            . 'else{ssSetStatus("video",d.message||"Failed.",true);if(btn)btn.disabled=false;}})'
            . '.catch(function(e){ssSetStatus("video",e.message||"Error.",true);if(btn)btn.disabled=false;});'
            . '}else{'
            . 'var file=document.getElementById("ss-poster-file").files[0];'
            . 'var title2=document.getElementById("ss-poster-title").value.trim();'
            . 'var pos2=parseInt(document.getElementById("ss-poster-position").value,10);'
            . 'var btn2=document.getElementById("ss-add-poster-btn");if(btn2)btn2.disabled=true;'
            . 'if(ssEditingId&&!file){'
            . 'ssCallAjax("mod_slideshow_save_extra_slide",{'
            . 'cmid:ssCmid,id:ssEditingId,slidetype:"poster",title:title2,videosource:"",videourl:"",'
            . 'videominwatch:0,position:pos2,imagebase64:"",imagefilename:""})'
            . '.then(function(d){if(d.success){ssSetStatus("poster","Saved! Reloading...",false);'
            . 'setTimeout(function(){window.location.reload();},700);}'
            . 'else{ssSetStatus("poster",d.message||"Failed.",true);if(btn2)btn2.disabled=false;}})'
            . '.catch(function(e){ssSetStatus("poster",e.message||"Error.",true);if(btn2)btn2.disabled=false;});'
            . 'return;}'
            . 'if(!file){ssSetStatus("poster","Please select an image.",true);if(btn2)btn2.disabled=false;return;}'
            . 'if(file.size>10*1024*1024){ssSetStatus("poster","File too large (max 10 MB).",true);if(btn2)btn2.disabled=false;return;}'
            . 'var rdr=new FileReader();rdr.onload=function(e){'
            . 'var b64=e.target.result.split(",")[1];'
            . 'ssCallAjax("mod_slideshow_save_extra_slide",{'
            . 'cmid:ssCmid,id:ssEditingId,slidetype:"poster",title:title2,videosource:"",videourl:"",'
            . 'videominwatch:0,position:pos2,imagebase64:b64,imagefilename:file.name})'
            . '.then(function(d){if(d.success){ssSetStatus("poster",(ssEditingId?"Saved":"Added")+"! Reloading...",false);'
            . 'setTimeout(function(){window.location.reload();},700);}'
            . 'else{ssSetStatus("poster",d.message||"Failed.",true);if(btn2)btn2.disabled=false;}})'
            . '.catch(function(e){ssSetStatus("poster",e.message||"Error.",true);if(btn2)btn2.disabled=false;});'
            . '};rdr.readAsDataURL(file);}};'
            . '})()</script>'

            . '</div>'; // ss-extra-manager

            $mform->addElement('static', 'extraslideui', '', $extrahtml);
        }

        // ---- VOICEOVER SETTINGS ----
        $mform->addElement('header', 'voiceoverheading', get_string('voiceoverheading', 'slideshow'));

        $mform->addElement('selectyesno', 'enablevoiceover', get_string('enablevoiceover', 'slideshow'));
        $mform->addHelpButton('enablevoiceover', 'enablevoiceover', 'slideshow');
        $mform->setDefault('enablevoiceover', 0);

        $languages = [
            'en-AU' => 'English (Australian)',
            'en-GB' => 'English (British)',
            'en-IN' => 'English (Indian)',
            'en-US' => 'English (American)',
            'es-ES' => 'Spanish (Spain)',
            'es-US' => 'Spanish (US)',
            'fr-CA' => 'French (Canadian)',
            'fr-FR' => 'French (France)',
            'de-DE' => 'German',
            'pt-BR' => 'Portuguese (Brazil)',
            'nl-BE' => 'Dutch (Belgium)',
            'nl-NL' => 'Dutch (Netherlands)',
            'da-DK' => 'Danish',
            'fi-FI' => 'Finnish',
            'nb-NO' => 'Norwegian',
            'sv-SE' => 'Swedish',
            'bg-BG' => 'Bulgarian',
            'cs-CZ' => 'Czech',
            'hr-HR' => 'Croatian',
            'hu-HU' => 'Hungarian',
            'pl-PL' => 'Polish',
            'ro-RO' => 'Romanian',
            'ru-RU' => 'Russian',
            'sk-SK' => 'Slovak',
            'sl-SI' => 'Slovenian',
            'sr-RS' => 'Serbian',
            'uk-UA' => 'Ukrainian',
            'et-EE' => 'Estonian',
            'lt-LT' => 'Lithuanian',
            'lv-LV' => 'Latvian',
            'el-GR' => 'Greek',
            'it-IT' => 'Italian',
            'cmn-CN' => 'Mandarin Chinese',
            'ja-JP' => 'Japanese',
            'ko-KR' => 'Korean',
            'id-ID' => 'Indonesian',
            'th-TH' => 'Thai',
            'vi-VN' => 'Vietnamese',
            'bn-IN' => 'Bengali',
            'gu-IN' => 'Gujarati',
            'hi-IN' => 'Hindi',
            'kn-IN' => 'Kannada',
            'ml-IN' => 'Malayalam',
            'mr-IN' => 'Marathi',
            'ta-IN' => 'Tamil',
            'te-IN' => 'Telugu',
            'ur-IN' => 'Urdu',
            'ar-XA' => 'Arabic',
            'he-IL' => 'Hebrew',
            'tr-TR' => 'Turkish',
            'sw-KE' => 'Swahili',
            'fil-PH' => 'Filipino',
        ];

        $mform->addElement('select', 'voicelanguage', get_string('voicelanguage', 'slideshow'), $languages);
        $mform->addHelpButton('voicelanguage', 'voicelanguage', 'slideshow');
        $mform->setDefault('voicelanguage', 'en-AU');
        $mform->hideIf('voicelanguage', 'enablevoiceover', 'eq', 0);

        $genders = [
            'female' => get_string('voicefemale', 'slideshow'),
            'male'   => get_string('voicemale', 'slideshow'),
        ];
        $mform->addElement('select', 'voicegender', get_string('voicegender', 'slideshow'), $genders);
        $mform->addHelpButton('voicegender', 'voicegender', 'slideshow');
        $mform->setDefault('voicegender', 'female');
        $mform->hideIf('voicegender', 'enablevoiceover', 'eq', 0);

        $voices = [
            'Aoede'  => 'Aoede (warm, female)',
            'Kore'   => 'Kore (clear, female)',
            'Leda'   => 'Leda (gentle, female)',
            'Zephyr' => 'Zephyr (bright, female)',
            'Charon' => 'Charon (deep, male)',
            'Fenrir' => 'Fenrir (strong, male)',
            'Orus'   => 'Orus (smooth, male)',
            'Puck'   => 'Puck (friendly, male)',
        ];
        $mform->addElement('select', 'voicestyle', get_string('voicestyle', 'slideshow'), $voices);
        $mform->addHelpButton('voicestyle', 'voicestyle', 'slideshow');
        $mform->setDefault('voicestyle', 'Aoede');
        $mform->hideIf('voicestyle', 'enablevoiceover', 'eq', 0);

        $mform->addElement('selectyesno', 'requirevoiceover', get_string('requirevoiceover', 'slideshow'));
        $mform->addHelpButton('requirevoiceover', 'requirevoiceover', 'slideshow');
        $mform->setDefault('requirevoiceover', 0);
        $mform->hideIf('requirevoiceover', 'enablevoiceover', 'eq', 0);

        if (!empty($this->current->id)) {
            $fs = get_file_storage();
            $slidecount = count($fs->get_area_files(
                $this->context->id, 'mod_slideshow', 'slideimages', $this->current->id,
                'sortorder, id', false
            ));
            $creditinfo = get_string('voiceovercreditinfo', 'slideshow', (object)[
                'slidecount'   => $slidecount,
                'totalcredits' => $slidecount * \mod_slideshow\external\generate_voiceover::CREDITS_PER_VOICEOVER,
            ]);
        } else {
            $creditinfo = get_string('voiceovercreditinfo_new', 'slideshow');
        }

        $credithtml = '<div style="padding:10px;background:#eff6ff;border:1px solid #3b82f6;border-radius:8px;margin-top:8px;">'
            . '<strong style="color:#1d4ed8;">Credit Information:</strong> ' . $creditinfo . '</div>';
        $mform->addElement('static', 'voiceovercreditdesc', '', $credithtml);
        $mform->hideIf('voiceovercreditdesc', 'enablevoiceover', 'eq', 0);

        // ---- DISPLAY SETTINGS ----
        $mform->addElement('header', 'displayheader', get_string('displaysettings', 'slideshow'));

        $ratios = [
            '16:9'  => '16:9 — Widescreen (PowerPoint default)',
            '16:10' => '16:10 — Widescreen (Keynote / laptop)',
            '4:3'   => '4:3 — Standard (classic PowerPoint)',
            '3:2'   => '3:2 — Photo (DSLR standard)',
            '1:1'   => '1:1 — Square (Instagram / social)',
            '2:1'   => '2:1 — Ultra-wide panoramic',
            '21:9'  => '21:9 — Cinematic ultra-wide',
        ];
        $mform->addElement('select', 'slideratio', get_string('slideratio', 'slideshow'), $ratios);
        $mform->addHelpButton('slideratio', 'slideratio', 'slideshow');
        $mform->setDefault('slideratio', '16:9');
        $mform->setType('slideratio', PARAM_TEXT);

        $mform->addElement('text', 'containerheight', get_string('containerheight', 'slideshow'), ['size' => '48']);
        $mform->addRule('containerheight', null, 'numeric', null, 'client');
        $mform->addHelpButton('containerheight', 'containerheight', 'slideshow');
        $mform->setType('containerheight', PARAM_INT);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $mform  = $this->_form;
        $suffix = $this->get_suffix();

        $mform->addElement(
            'checkbox',
            'completionallslides' . $suffix,
            get_string('completionallslides', 'slideshow')
        );
        $mform->setType('completionallslides' . $suffix, PARAM_INT);
        $mform->setDefault('completionallslides' . $suffix, 0);
        $mform->addHelpButton('completionallslides' . $suffix, 'completionallslides', 'slideshow');

        return ['completionallslides' . $suffix];
    }

    public function completion_rule_enabled($data) {
        return (!empty($data['completionallslides' . $this->get_suffix()]));
    }

    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $suffix = $this->get_suffix();
        $data->completionallslides = isset($data->{"completionallslides$suffix"}) ? 1 : 0;
    }

    public function data_preprocessing(&$defaultvalues) {
        global $CFG, $DB, $USER;

        if (isset($defaultvalues['completionallslides'])) {
            $suffix = $this->get_suffix();
            $defaultvalues["completionallslides$suffix"] = $defaultvalues['completionallslides'];
        }

        if (!empty($this->current->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('slideimages');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_slideshow',
                'slideimages',
                $this->current->instance,
                ['subdirs' => 0, 'maxfiles' => -1, 'preservetimestamps' => true]
            );
            $defaultvalues['slideimages'] = $draftitemid;

            $fs      = get_file_storage();
            $userctx = \context_user::instance($USER->id);
            $draftfiles = $fs->get_area_files(
                $userctx->id, 'user', 'draft', $draftitemid, 'id', false
            );

            if (!empty($draftfiles)) {
                $tx     = $DB->start_delegated_transaction();
                $offset = 0;
                foreach ($draftfiles as $df) {
                    $id = $df->get_id();
                    $tc = (int)$df->get_timecreated();
                    $tm = (int)$df->get_timemodified();
                    $DB->set_field('files', 'timecreated',  $tc + $offset, ['id' => $id]);
                    $DB->set_field('files', 'timemodified', $tm + $offset, ['id' => $id]);
                    $offset++;
                }
                $tx->allow_commit();
            }
        }
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            if (isset($data->name)) {
                $data->name = trim($data->name);
            }
            $this->data_postprocessing($data);
        }
        return $data;
    }

    public function set_data($defaultvalues) {
        if (is_object($defaultvalues)) {
            $defaultvalues = (array)$defaultvalues;
        }
        $this->data_preprocessing($defaultvalues);
        parent::set_data($defaultvalues);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['slideimages'])) {
            if (empty($this->current->id)) {
                $errors['slideimages'] = get_string('required');
            } else {
                $fs = get_file_storage();
                $currentfiles = $fs->get_area_files($this->context->id, 'mod_slideshow',
                    'slideimages', $this->current->id, 'filename', false);
                if (empty($currentfiles)) {
                    $errors['slideimages'] = get_string('required');
                }
            }
        }

        return $errors;
    }
}
