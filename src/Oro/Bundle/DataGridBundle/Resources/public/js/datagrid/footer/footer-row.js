define(function(require) {
    'use strict';

    const FooterCell = require('./footer-cell');
    const Chaplin = require('chaplin');

    const FooterRow = Chaplin.CollectionView.extend({
        tagName: 'tr',

        className: '',

        animationDuration: 0,

        /**
         * @inheritDoc
         */
        constructor: function FooterRow(options) {
            FooterRow.__super__.constructor.call(this, options);
        },

        /** @property */
        footerCell: FooterCell,

        initialize: function(options) {
            this.columns = options.columns;
            this.dataCollection = options.dataCollection;
            this.ariaRowIndex = options.ariaRowIndex;

            // itemView function is called as new this.itemView
            // it is placed here to pass THIS within closure
            const footerRowView = this;
            // let descendants override itemView
            if (!this.itemView) {
                this.itemView = function(itemViewOptions) {
                    const column = itemViewOptions.model;
                    const FooterCell = column.get('footerCell') || options.footerCell || footerRowView.footerCell;
                    const cellOptions = {
                        column: column,
                        collection: footerRowView.dataCollection,
                        rowName: options.rowName,
                        themeOptions: {
                            className: 'grid-cell grid-footer-cell'
                        }
                    };
                    if (column.get('name')) {
                        cellOptions.themeOptions.className += ' grid-footer-cell-' + column.get('name');
                    }
                    footerRowView.columns.trigger('configureInitializeOptions', FooterCell, cellOptions);
                    return new FooterCell(cellOptions);
                };
            }
            FooterRow.__super__.initialize.call(this, options);
            this.listenTo(this.dataCollection, 'add remove reset', this.setAriaAttrs);
            this.cells = this.subviews;
        },

        render() {
            FooterRow.__super__.render.call(this);
            this.setAriaAttrs();
            return this;
        },

        setAriaAttrs() {
            if (this.disposed) {
                return;
            }

            this.$el.attr('aria-rowindex', this.ariaRowIndex);
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            delete this.cells;
            delete this.columns;
            delete this.dataCollection;
            FooterRow.__super__.dispose.call(this);
        }
    });

    return FooterRow;
});
