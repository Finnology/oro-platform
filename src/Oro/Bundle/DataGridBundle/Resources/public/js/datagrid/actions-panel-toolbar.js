define(function(require) {
    const ActionsPanel = require('orodatagrid/js/datagrid/actions-panel');

    const ActionsPanelToolbar = ActionsPanel.extend({
        constructor: function ActionsPanelToolbar(...args) {
            ActionsPanelToolbar.__super__.constructor.apply(this, args);
        }
    });

    return ActionsPanelToolbar;
});
