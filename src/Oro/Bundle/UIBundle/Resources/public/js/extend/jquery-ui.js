define(function(require) {
    'use strict';

    var $ = require('jquery');
    var mask = require('oroui/js/dropdown-mask');
    require('jquery-ui');

    /* datepicker extend:start */
    (function() {

        var original = {
            _destroyDatepicker: $.datepicker.constructor.prototype._destroyDatepicker
        };

        var dropdownClassName = 'ui-datepicker-dialog-is-below';
        var dropupClassName = 'ui-datepicker-dialog-is-above';

        /**
         * Combines space-separated line of events with widget's namespace
         *  for handling datepicker's position change
         *
         * @returns {string}
         * @private
         */
        function getEvents(uuid) {
            var events = ['scroll', 'resize'];
            var ns = 'datepicker-' + uuid;

            events = $.map(events, function(eventName) {
                return eventName + '.' + ns;
            });

            return events.join(' ');
        }

        /**
         * Process position update for datepicker element
         */
        function updatePos() {
            var pos;
            var isFixed;
            var offset;
            // jshint -W040
            var input = this;
            var $input = $(this);

            var inst = $.datepicker._getInst(input);
            if (!inst) {
                return;
            }

            if (!$.datepicker._pos) { // position below input
                pos = $.datepicker._findPos(input);
                pos[1] += input.offsetHeight; // add the height
            }

            isFixed = false;
            $input.parents().each(function() {
                isFixed = isFixed || $(this).css('position') === 'fixed';
                return !isFixed;
            });

            offset = {left: pos[0], top: pos[1]};
            offset = $.datepicker._checkOffset(inst, offset, isFixed);
            inst.dpDiv.css({left: offset.left + 'px', top: offset.top + 'px'});

            var isBelow = offset.top - $input.offset().top > 0;
            var isActualClass = $input.hasClass(dropdownClassName) === isBelow &&
                $input.hasClass(dropupClassName) !== isBelow;

            if (!isActualClass && inst.dpDiv.is(':visible')) {
                $input.toggleClass(dropdownClassName, isBelow);
                $input.toggleClass(dropupClassName, !isBelow);
                $input.trigger('datepicker:dialogReposition', isBelow ? 'below' : 'above');
            }
        }

        var _showDatepicker = $.datepicker.constructor.prototype._showDatepicker;
        var _hideDatepicker = $.datepicker.constructor.prototype._hideDatepicker;
        var _attachments = $.datepicker.constructor.prototype._attachments;

        $.datepicker.constructor.prototype._attachments = function($input, inst) {
            $input
                .off('click', this._showDatepicker)
                .click(this._showDatepicker);
            _attachments.call(this, $input, inst);
        };

        /**
         * Bind update position method after datepicker is opened
         *
         * @param elem
         * @override
         * @private
         */
        $.datepicker.constructor.prototype._showDatepicker = function(elem) {
            _showDatepicker.apply(this, arguments);

            var input = elem.target || elem;
            var $input = $(input);
            var events = getEvents($input.id);

            var inst = $.datepicker._getInst(input);
            // set bigger zIndex difference between dropdown and input, to have place for dropdown mask
            inst.dpDiv.css('z-index', Number(inst.dpDiv.css('z-index')) + 2);

            $input
                .removeClass(dropdownClassName + ' ' + dropupClassName)
                .parents().add(window).each(function() {
                    $(this).on(events, $.proxy(updatePos, input));
                    // @TODO develop other approach than hide on scroll
                    // because on mobile devices it's impossible to open calendar without scrolling
                    /*$(this).on(events, function () {
                        // just close datepicker
                        $.datepicker._hideDatepicker();
                        input.blur();
                    });*/
                });

            updatePos.call(input);

            $input.trigger('datepicker:dialogShow');
        };

        /**
         * Remove all handlers before closing datepicker
         *
         * @param elem
         * @override
         * @private
         */
        $.datepicker.constructor.prototype._hideDatepicker = function(elem) {
            var input = elem;

            if (!elem) {
                if (!$.datepicker._curInst) {
                    return;
                }
                input = $.datepicker._curInst.input.get(0);
            }
            var events = getEvents(input.id);

            var $input = $(input);
            $input
                .removeClass(dropdownClassName + ' ' + dropupClassName)
                .parents().add(window).each(function() {
                    $(this).off(events);
                });

            _hideDatepicker.apply(this, arguments);

            $input.trigger('datepicker:dialogHide');
        };

        $.datepicker.constructor.prototype._destroyDatepicker = function() {
            if (!this._curInst) {
                return;
            }
            if (this._curInst.input) {
                this._curInst.input.datepicker('hide')
                    .off('click', this._showDatepicker);
            }
            original._destroyDatepicker.apply(this, arguments);
        };
    }());
    $(document).off('select2-open.dropdown.data-api').on('select2-open.dropdown.data-api', function() {
        if ($.datepicker._curInst && $.datepicker._datepickerShowing && !($.datepicker._inDialog && $.blockUI)) {
            $.datepicker._hideDatepicker();
        }
    });
    $(document)
        .on('datepicker:dialogShow', function(e) {
            var $input = $(e.target);
            var zIndex = $.datepicker._getInst(e.target).dpDiv.css('zIndex');
            mask.show(zIndex - 1)
                .onhide(function() {
                    $input.datepicker('hide');
                });
        })
        .on('datepicker:dialogHide', function(e) {
            mask.hide();
        });
    /* datepicker extend:end */

    /* dialog extend:start*/
    (function() {
        var oldMoveToTop = $.ui.dialog.prototype._moveToTop;
        $.widget('ui.dialog', $.ui.dialog, {
            /**
             * Replace method because some browsers return string 'auto' if property z-index not specified.
             * */
            _moveToTop: function() {
                var zIndex = this.uiDialog.css('z-index');
                var numberRegexp = /^\d+$/;
                if (typeof zIndex === 'string' && !numberRegexp.test(zIndex)) {
                    this.uiDialog.css('z-index', 910);
                }
                oldMoveToTop.apply(this);
            }
        });
    }());
    /* dialog extend:end*/
});
