/**
 * PowerPoint Import handler for mod_slideshow
 *
 * v1.6.23: Fixed refreshFileManager()  -  was using wrong instance key ('f-slideimages'
 *          instead of integer draftItemId). File manager now refreshes after upload.
 *          Fixed progress text truncation (removed white-space:nowrap + min-width via JS).
 *          Fixed grammar (1 slide vs N slides). Increased success message timeout to 20s.
 *          Added "Scroll down to check Slideshow Images" guidance in success message.
 * v1.6.10: Course ID detection uses 4 fallbacks: URL param, M.cfg, form input, body class.
 * v1.6.9: Fixed missing closing brace in upload_draft_file.php + dropzone alignment fix.
 * v1.6.8: Rewrote file upload to use mod_slideshow_upload_draft_file web service
 * instead of hacking into Moodle's repository system. Files are stored directly
 * into the draft area via file_storage API  -  no repository ID discovery needed.
 *
 * @module     mod_slideshow/pptximport
 * @package    mod_slideshow
 * @copyright  2026 National Corporate Training Pty Ltd
 */
define(['core/ajax'], function(Ajax) {
    return {
        init: function() {
            var container = document.getElementById('slideshow-pptx-import');
            if (!container) return;

            var fitemWrap = container.closest('.fitem, [id^="fitem_id_"]');
            if (fitemWrap) {
                var labelCol = fitemWrap.querySelector('.col-md-3');
                var contentCol = fitemWrap.querySelector('.col-md-9');
                if (labelCol) { labelCol.style.display = 'none'; }
                if (contentCol) { contentCol.style.flex = '0 0 100%'; contentCol.style.maxWidth = '100%'; }
            }

            var dropzone = document.getElementById('slideshow-pptx-dropzone');
            var fileInput = document.getElementById('slideshow-pptx-file-input');
            var progressWrap = document.getElementById('slideshow-pptx-progress');
            var progressBar = document.getElementById('slideshow-pptx-progress-bar');
            var progressText = document.getElementById('slideshow-pptx-progress-text');
            var resultDiv = document.getElementById('slideshow-pptx-result');

            // Allow progress text to grow freely so full messages are always readable.
            if (progressText) {
                progressText.style.whiteSpace = 'normal';
                progressText.style.minWidth = '0';
                progressText.style.flex = '1';
            }

            var siteId = container.dataset.siteid;
            var apiKey = container.dataset.apikey;
            var serverUrl = container.dataset.serverurl || 'https://lms-labs.com';
            var urlParams = new URLSearchParams(window.location.search);

            var cmid = parseInt(container.dataset.cmid || '0', 10);
            if (!cmid) {
                cmid = parseInt(urlParams.get('update') || '0', 10);
            }
            if (!cmid) {
                var cmInput = document.querySelector('input[name="coursemodule"]');
                if (cmInput) {
                    cmid = parseInt(cmInput.value, 10) || 0;
                }
            }

            var courseid = 0;
            if (!cmid) {
                courseid = parseInt(urlParams.get('course') || '0', 10);

                if (!courseid && typeof M !== 'undefined' && M.cfg && M.cfg.courseId) {
                    courseid = parseInt(M.cfg.courseId, 10) || 0;
                }

                if (!courseid) {
                    var courseInput = document.querySelector('input[name="course"]');
                    if (courseInput) {
                        courseid = parseInt(courseInput.value, 10) || 0;
                    }
                }

                if (!courseid) {
                    var bodyEl = document.querySelector('body[class*="course-"]');
                    if (bodyEl) {
                        var m = bodyEl.className.match(/course-(\d+)/);
                        if (m) {
                            courseid = parseInt(m[1], 10) || 0;
                        }
                    }
                }
            }

            var resultHideTimer = null;

            function showProgress(text, pct) {
                progressWrap.style.display = 'flex';
                progressText.textContent = text;
                if (typeof pct === 'number') {
                    progressBar.style.width = pct + '%';
                }
            }

            function hideProgress() {
                progressWrap.style.display = 'none';
                progressBar.style.width = '0%';
            }

            function showResult(msg, isError) {
                if (resultHideTimer) {
                    clearTimeout(resultHideTimer);
                    resultHideTimer = null;
                }
                resultDiv.style.display = 'block';
                resultDiv.textContent = msg;
                resultDiv.className = 'slideshow-pptx-result ' + (isError ? 'error' : 'success');
                // Success messages stay for 20s; error messages for 12s.
                var timeout = isError ? 12000 : 20000;
                resultHideTimer = setTimeout(function() {
                    resultDiv.style.display = 'none';
                    resultHideTimer = null;
                }, timeout);
            }

            function getFileManagerDraftItemId() {
                var el = document.querySelector('input[name="slideimages"]');
                return el ? el.value : null;
            }

            /**
             * Refresh the Moodle file manager UI after files are injected into the draft area.
             *
             * Moodle stores file-manager YUI instances in M.form_filemanager.instances
             * keyed by the integer draftItemId (NOT the HTML element ID).
             * Falls back to field-name key, then a DOM click on the refresh button.
             */
            function refreshFileManager() {
                var draftItemId = getFileManagerDraftItemId();
                var itemIdInt = draftItemId ? parseInt(draftItemId, 10) : 0;

                if (typeof M !== 'undefined' && M.form_filemanager && M.form_filemanager.instances) {
                    // Primary: keyed by integer draft item ID (Moodle 3.x / 4.x standard).
                    var fm = M.form_filemanager.instances[itemIdInt]
                          || M.form_filemanager.instances[String(itemIdInt)]
                          || M.form_filemanager.instances['slideimages'];
                    if (fm && typeof fm.refresh === 'function') {
                        fm.refresh('/');
                        return;
                    }
                }

                // Fallback: click the file manager's own refresh button in the DOM.
                var fmElem = document.getElementById('ffilemanager-slideimages')
                          || document.querySelector('[id$="filemanager-slideimages"]')
                          || document.querySelector('.filemanager');
                if (fmElem) {
                    var refreshBtn = fmElem.querySelector('.fp-btn-refresh');
                    if (refreshBtn) {
                        refreshBtn.click();
                    }
                }
            }

            function slideWord(n) {
                return n === 1 ? 'slide' : 'slides';
            }

            function uploadViaDraftService(filename, base64data) {
                var draftItemId = getFileManagerDraftItemId();
                if (!draftItemId) {
                    return Promise.reject(new Error('No file manager draft ID found'));
                }
                if (!cmid && !courseid) {
                    return Promise.reject(new Error('No course module ID or course ID available'));
                }

                return Ajax.call([{
                    methodname: 'mod_slideshow_upload_draft_file',
                    args: {
                        cmid: cmid,
                        courseid: courseid,
                        draftitemid: parseInt(draftItemId, 10),
                        filename: filename,
                        filedata: base64data
                    }
                }])[0].then(function(result) {
                    if (!result.success) {
                        throw new Error(result.error || 'Upload failed');
                    }
                    return result;
                });
            }

            function uploadFileViaDraftService(file) {
                return new Promise(function(resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function() {
                        var base64 = reader.result.split(',')[1];
                        uploadViaDraftService(file.name, base64).then(resolve).catch(reject);
                    };
                    reader.onerror = function() {
                        reject(new Error('Failed to read file'));
                    };
                    reader.readAsDataURL(file);
                });
            }

            function addImageFilesToDraft(imageFiles) {
                showProgress('Uploading images to file manager...', 0);
                var total = imageFiles.length;
                var done = 0;
                var errors = 0;

                function processNext(idx) {
                    if (idx >= total) {
                        hideProgress();
                        if (errors > 0) {
                            showResult(done + ' of ' + total + ' ' + slideWord(total) + ' added (' + errors + ' failed) \u2014 scroll down to check Slideshow Images', errors === total);
                        } else {
                            showResult(total + ' image' + (total === 1 ? '' : 's') + ' added \u2014 scroll down to check Slideshow Images', false);
                        }
                        refreshFileManager();
                        return;
                    }
                    showProgress('Uploading ' + imageFiles[idx].name + '...', Math.round((idx / total) * 100));

                    uploadFileViaDraftService(imageFiles[idx]).then(function() {
                        done++;
                        processNext(idx + 1);
                    }).catch(function(err) {
                        console.error('[pptximport] Upload failed:', imageFiles[idx].name, err);
                        errors++;
                        done++;
                        processNext(idx + 1);
                    });
                }
                processNext(0);
            }

            var MAX_FILE_SIZE = 30 * 1024 * 1024;

            function convertPptx(file) {
                if (!siteId || !apiKey) {
                    showResult('Error: AI Grader Site ID and API Key not configured. Please configure in plugin settings.', true);
                    return;
                }

                if (file.size > MAX_FILE_SIZE) {
                    var sizeMB = (file.size / (1024 * 1024)).toFixed(1);
                    showResult('Error: File is too large (' + sizeMB + ' MB). Maximum size is 30 MB. To reduce: in PowerPoint, click any image > Picture Format > Compress Pictures > uncheck "Apply only to this picture" > select Web (150 ppi) > OK, then save.', true);
                    return;
                }

                showProgress('Sending PowerPoint to AI Grader for conversion...', 10);

                var formData = new FormData();
                formData.append('file', file);
                formData.append('siteId', siteId);
                formData.append('apiKey', apiKey);

                fetch(serverUrl + '/api/moodle/slideshow/convert-pptx', {
                    method: 'POST',
                    body: formData
                }).then(function(resp) {
                    return resp.json();
                }).then(function(data) {
                    if (!data.success) {
                        hideProgress();
                        showResult('Error: ' + (data.error || 'Conversion failed'), true);
                        return;
                    }

                    var slides = data.slides;
                    if (!slides || !slides.length) {
                        hideProgress();
                        showResult('Error: Conversion returned no slides. Try a different file.', true);
                        return;
                    }

                    var total = slides.length;
                    var uploaded = 0;
                    var errors = 0;

                    showProgress('Conversion done \u2014 uploading ' + total + ' ' + slideWord(total) + ' to Slideshow Images...', 30);

                    function uploadNext(idx) {
                        if (idx >= total) {
                            hideProgress();
                            if (errors > 0) {
                                var msg = uploaded + ' of ' + total + ' ' + slideWord(total) + ' uploaded';
                                if (errors > 0) msg += ' (' + errors + ' failed)';
                                msg += ' \u2014 scroll down to check Slideshow Images';
                                showResult(msg, uploaded === 0);
                            } else {
                                showResult(total + ' ' + slideWord(total) + ' successfully added \u2014 scroll down to check Slideshow Images, then save', false);
                            }
                            refreshFileManager();
                            return;
                        }

                        var slide = slides[idx];
                        var pct = 30 + Math.round(((idx + 1) / total) * 70);
                        showProgress('Uploading ' + slideWord(1) + ' ' + (idx + 1) + ' of ' + total + '...', pct);

                        uploadViaDraftService(slide.filename, slide.data).then(function() {
                            uploaded++;
                            uploadNext(idx + 1);
                        }).catch(function(err) {
                            console.error('[pptximport] Failed to upload slide ' + slide.filename + ':', err);
                            errors++;
                            uploadNext(idx + 1);
                        });
                    }
                    uploadNext(0);

                }).catch(function(err) {
                    hideProgress();
                    showResult('Error: ' + (err.message || 'Network error during conversion'), true);
                });
            }

            function handleFiles(files) {
                var pptxFiles = [];
                var imageFiles = [];

                for (var i = 0; i < files.length; i++) {
                    var f = files[i];
                    var ext = f.name.split('.').pop().toLowerCase();
                    if (ext === 'pptx' || ext === 'ppt') {
                        pptxFiles.push(f);
                    } else if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].indexOf(ext) !== -1) {
                        imageFiles.push(f);
                    }
                }

                if (pptxFiles.length > 0) {
                    convertPptx(pptxFiles[0]);
                }

                if (imageFiles.length > 0) {
                    addImageFilesToDraft(imageFiles);
                }

                if (pptxFiles.length === 0 && imageFiles.length === 0) {
                    showResult('No supported files found. Use .pptx, .ppt, or image files.', true);
                }
            }

            dropzone.addEventListener('click', function() {
                fileInput.click();
            });

            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    handleFiles(fileInput.files);
                    fileInput.value = '';
                }
            });

            dropzone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });

            dropzone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });

            dropzone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
                if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                    handleFiles(e.dataTransfer.files);
                }
            });
        }
    };
});
