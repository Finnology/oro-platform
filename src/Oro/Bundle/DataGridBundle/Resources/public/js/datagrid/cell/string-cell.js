define(function(require) {
    'use strict';

    const Backgrid = require('backgrid');
    const CellFormatter = require('orodatagrid/js/datagrid/formatter/cell-formatter');

    /**
     * String column cell. Added missing behaviour.
     *
     * @export  oro/datagrid/cell/string-cell
     * @class   oro.datagrid.cell.StringCell
     * @extends Backgrid.StringCell
     */
    const StringCell = Backgrid.StringCell.extend({
        /**
         @property {(Backgrid.CellFormatter|Object|string)}
         */
        formatter: new CellFormatter(),

        /**
         * @inheritDoc
         */
        constructor: function StringCell(options) {
            StringCell.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        render: function() {
            const render = StringCell.__super__.render.call(this);

            this.enterEditMode();
            this.setAriaAttrs();

            return render;
        },

        /**
         * @inheritDoc
         */
        enterEditMode: function() {
            if (this.isEditableColumn()) {
                StringCell.__super__.enterEditMode.call(this);
            }
        },

        /**
         * @inheritDoc
         */
        exitEditMode: function() {
            if (!this.isEditableColumn()) {
                StringCell.__super__.exitEditMode.call(this);
            }
        }
    });

    return StringCell;
});
