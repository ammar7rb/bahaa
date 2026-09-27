$(document).ready(function () {
    let hasFileSizeError = false;

    window.hasFileSizeError = function () {
        return hasFileSizeError;
    };

    window.validateRequiredImages = function () {
        let isValid = true;

        $(".multi_image_picker[data-required='true']").each(function () {
            const $picker = $(this);
            const hasImage = $picker.find(".spartan_item").length > 0 || $picker.find('input[type="file"]').val();

            if (!hasImage) {
                isValid = false;

                toastMagic.error($picker.data("required-msg") || $("#text-validate-translate").data("required"));

                $("html, body").animate(
                    { scrollTop: $picker.offset().top - 120 },
                    600
                );

                return false;
            }
        });

        return isValid;
    };

    function checkNavOverflow($picker) {
        try {
            let $btnNext = $picker.find(".imageSlide_next");
            let $btnPrev = $picker.find(".imageSlide_prev");
            let isRTL = $("html").attr("dir") === "rtl";
            let navScrollWidth = $picker[0].scrollWidth;
            let navClientWidth = $picker[0].clientWidth;
            let scrollLeft = $picker.scrollLeft();

            if (isRTL) {
                let maxScrollLeft = navScrollWidth - navClientWidth;
                let scrollRight = maxScrollLeft - scrollLeft;

                $btnNext.toggle(scrollLeft > 0);
                $btnPrev.toggle(scrollRight > 1);
            } else {
                $btnNext.toggle(
                    navScrollWidth > navClientWidth &&
                        scrollLeft + navClientWidth < navScrollWidth
                );
                $btnPrev.toggle(scrollLeft > 1);
            }
        } catch (error) {
            console.warn("Error checking nav overflow:", error);
        }
    }

    $(".multi_image_picker").each(function () {
        let $picker = $(this);
        let ratio = $picker.data("ratio");
        let fieldName = $picker.data("field-name");
        let maxCount = $picker.data("max-count") || Infinity;
        let maxSizeReadable = $picker.data("max-filesize") || 5;
        let maxFileSize =  parseFloat(maxSizeReadable) * 1024 * 1024;

        if ($picker.hasClass('spartan-initialized')) {
            return;
        }
        $picker.addClass('spartan-initialized');
        let dropFileLabel = "";
        if ($picker.hasClass("design_two")) {
            dropFileLabel = `
                <div class="drop-label text-center">
                    <p class="fs-12 text-body mb-0 mt-1">
                        Add
                    </p>
                </div>
            `;
        } else {
            dropFileLabel = `
                <div class="drop-label text-center">
                    <h6 class="mt-1 fw-medium lh-base fs-10">
                        <span class="text-info">Click to upload</span><br>
                        or drag and drop
                    </h6>
                </div>
            `;
        }
        $picker.on('change', 'input[type="file"]', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (file.size > maxFileSize) {
                        hasFileSizeError = true;
                        const message = getMultipleImageUploadSizeError($picker);
                        if (typeof toastMagic !== 'undefined' && toastMagic.error) {
                            toastMagic.error(message);
                        }
                        e.target.value = '';
                        return false;
                    }
                }
            }
        });
        $picker.spartanMultiImagePicker({
            fieldName: fieldName,
            maxCount: maxCount,
            rowHeight: "100px",
            groupClassName: "",
            maxFileSize: maxFileSize,
            allowedExt: "webp|jpg|jpeg|png",
            dropFileLabel: dropFileLabel,
            placeholderImage: {
                image: placeholderImageUrl,
                width: "30px",
                height: "30px",
            },

            onAddRow: function (index, file) {
                checkNavOverflow($picker);
                setAspectRatio($picker, ratio);
                hasFileSizeError = false;
            },
            onExtensionErr: function (index, file) {
                const message = getMultipleImageUploadExtensionError($picker);
                toastMagic.error(message);
            },
            onSizeErr: function (index, file) {
                hasFileSizeError = true;
                const message = getMultipleImageUploadSizeError($picker);
                toastMagic.error(message);
            },

        });

        /*
         * Spartan creates one native file input per preview slot.  Keep those
         * inputs (and the existing form contract of images[]) intact, but add a
         * separate multi-file chooser for the empty upload slot.  Selecting a
         * group of files then fills the individual Spartan slots in one action.
         */
        if ($picker.data("batch-selection") === true || $picker.data("batch-selection") === "true") {
            const $batchInput = $("<input>", {
                type: "file",
                accept: "image/*",
                multiple: true,
                class: "spartan_batch_image_input",
                tabindex: -1,
            }).css("display", "none");

            $picker.append($batchInput);

            const getEmptySpartanInput = function () {
                return $picker.find(".spartan_image_input").filter(function () {
                    const $input = $(this);
                    const index = $input.data("spartanindexinput");
                    const hasPreview = $picker.find('img[data-spartanindeximage="' + index + '"]').is(":visible");

                    return (!this.files || this.files.length === 0) && !hasPreview;
                }).first();
            };

            $picker.on("click.batchImageSelection", ".spartan_item_wrapper .file_upload", function (event) {
                const $slotInput = $(this).find(".spartan_image_input");

                // Replacing an existing image remains the regular single-file
                // Spartan behavior. Batch selection is only for an empty slot.
                if (!$slotInput.length || ($slotInput[0].files && $slotInput[0].files.length > 0)) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                $batchInput.trigger("click");
            });

            $batchInput.on("change", function () {
                const selectedFiles = Array.prototype.slice.call(this.files || []);
                this.value = "";

                if (!selectedFiles.length || typeof DataTransfer === "undefined") {
                    return;
                }

                const configuredMaximum = Number($picker.data("max-count"));
                const selectedCount = $picker.find(".spartan_image_input").filter(function () {
                    return this.files && this.files.length > 0;
                }).length;
                const availableSlots = Number.isFinite(configuredMaximum)
                    ? Math.max(configuredMaximum - selectedCount, 0)
                    : selectedFiles.length;
                const filesToAdd = selectedFiles.slice(0, availableSlots);

                filesToAdd.forEach(function (file) {
                    const $targetInput = getEmptySpartanInput();
                    if (!$targetInput.length) {
                        return;
                    }

                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    $targetInput[0].files = transfer.files;
                    $targetInput.trigger("change");
                });

                if (selectedFiles.length > filesToAdd.length && typeof toastMagic !== "undefined" && toastMagic.error) {
                    toastMagic.error("Maximum " + configuredMaximum + " images can be selected.");
                }
            });
        }

        function getMultipleImageUploadSizeError(picker) {
            return $(picker).data('validation-error-msg');
        }
        function getMultipleImageUploadExtensionError(picker) {
            let allowedExtensionMessage = $(picker).data('allowed-formats');
            return "Only allowed extensions are: " + allowedExtensionMessage;
        }


        function setAspectRatio($picker, ratio) {
            if (ratio) {
                $picker.find(".file_upload").css("aspect-ratio", ratio);
            }
        }

        $picker.find(".imageSlide_next").click(function () {
            let scrollWidth = $picker
                .find(".spartan_item_wrapper")
                .outerWidth(true);
            $picker.animate(
                { scrollLeft: $picker.scrollLeft() + scrollWidth },
                300,
                function () {
                    checkNavOverflow($picker);
                }
            );
        });

        $picker.find(".imageSlide_prev").click(function () {
            let scrollWidth = $picker
                .find(".spartan_item_wrapper")
                .outerWidth(true);
            $picker.animate(
                { scrollLeft: $picker.scrollLeft() - scrollWidth },
                300,
                function () {
                    checkNavOverflow($picker);
                }
            );
        });
    });

    let resizeTimeout;
    $(window).on("resize", function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
            $(".multi_image_picker").each(function () {
                checkNavOverflow($(this));
            });
        }, 200);
    });

    $(".multi_image_picker").on("scroll", function () {
        checkNavOverflow($(this));
    });

});
