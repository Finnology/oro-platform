define(function(require) {
    'use strict';

    var ExpressionEditorUtil;
    var _ = require('underscore');
    var BaseClass = require('oroui/js/base-class');
    var Token = require('oroexpressionlanguage/js/extend/token');
    var ExpressionLanguage = require('oroexpressionlanguage/js/extend/expression-language');
    var ExpressionOperandTypeValidator = require('oroform/js/expression-operand-type-validator');

    /**
     * @typedef {Object} AutocompleteData
     * @poperty {string} expression - full expression
     * @poperty {number} position - cursor position
     * @poperty {number} replaceFrom - start part of expression that will be replaced by selected item,
     * @poperty {number} replaceTo - end part of expression that will be replaced by selected item,
     * @poperty {string} query - part of expression before cursor that recognized like part of an autocomplete item
     * @poperty {string} itemsType - one of: `entities`, `operations`, `datasource`
     * @poperty {Object} items - list of items for autocomplete
     * @poperty {string} dataSourceKey -  key of data source if item is data source
     * @poperty {string} dataSourceValue - value of data source if item is data source
     */

    /**
     * @typedef {Object} FieldChain
     * @poperty {Token} entity - Token with name type that contains entity name
     * @poperty {Token} [dataSourceOpenBracket] - Token with punctuation type, is presented when entity has a datasource
     * @poperty {Token} [dataSourceValue] - Token with constant type
     * @poperty {Token} [dataSourceCloseBracket] - Token with punctuation type
     * @poperty {Array.<Token>} fields
     * @poperty {Token} lastToken
     */

    ExpressionEditorUtil = BaseClass.extend({
        /**
         * Constants, used for expression build
         */
        strings: {
            childSeparator: '.',
            itemSeparator: ' '
        },

        /**
         * Default options
         */
        defaultOptions: {
            itemLevelLimit: 3,
            allowedOperations: ['math', 'bool', 'equality', 'compare', 'inclusion', 'like'],
            operations: {
                math: ['+', '-', '%', '*', '/'],
                bool: ['and', 'or'],
                equality: ['==', '!='],
                compare: ['>', '<', '<=', '>='],
                inclusion: ['in', 'not in'],
                like: ['matches']
            },
            rootEntities: []
        },

        /**
         * Generated list of operations autocomplete items
         */
        operationsItems: null,

        /**
         * Generated list of available data sources
         */
        dataSourceNames: null,

        /**
         * Instance of ExpressionLanguage that can parse an expression
         */
        expressionLanguage: null,

        /**
         * Instance of EntityStructureDataProvider that contains data about entity fields
         */
        entityDataProvider: null,

        /**
         * Instance of ExpressionOperandTypeValidator that can check allowed operations, type of operands, and presence
         * used fields in an entity structure
         */
        expressionOperandTypeValidator: null,

        constructor: function ExpressionEditorUtil(options) {
            ExpressionEditorUtil.__super__.constructor.call(this, options);
        },

        /**
         * Initialize util
         *
         * @param {Object} options
         */
        initialize: function(options) {
            if (!options.entityDataProvider) {
                throw new Error('Option "entityDataProvider" is required');
            }
            if ('itemLevelLimit' in options && (!_.isNumber(options.itemLevelLimit) || options.itemLevelLimit < 2)) {
                throw new Error('Option "itemLevelLimit" can\'t be smaller than 2');
            }
            _.extend(this, _.pick(options, 'entityDataProvider', 'dataSourceNames'));
            this.options = _.defaults(_.pick(options, _.keys(this.defaultOptions)), this.defaultOptions);
            this.expressionLanguage = new ExpressionLanguage();
            this._prepareOperationsItems();
            var entities = this.options.rootEntities.map(function(entityName) {
                return {
                    isCollection: this.dataSourceNames.indexOf(entityName) !== -1,
                    name: entityName,
                    fields: this.entityDataProvider.getEntityTreeNodeByPropertyPath(entityName)
                };
            }.bind(this));
            var validatorOptions = {
                entities: entities,
                itemLevelLimit: this.options.itemLevelLimit,
                operations: this.operationsItems,
                isConditionalNodeAllowed: false
            };
            this.expressionOperandTypeValidator = new ExpressionOperandTypeValidator(validatorOptions);
            ExpressionEditorUtil.__super__.initialize.call(this, options);
        },

        /**
         * Validate expression syntax
         *
         * @param {string} expression
         * @return {boolean}
         */
        validate: function(expression) {
            var isValid = true;
            try {
                var parsedExpression = this.expressionLanguage.parse(expression, this.options.rootEntities);
                this.expressionOperandTypeValidator.expectValid(parsedExpression);
            } catch (ex) {
                isValid = false;
            }

            return isValid;
        },

        /**
         * Build autocomplete data by expression and cursor position
         *
         * @param {string} expression
         * @param {number} position
         * @return {AutocompleteData}
         */
        getAutocompleteData: function(expression, position) {
            var autocompleteData = {
                expression: expression,
                position: position,
                replaceFrom: position,
                replaceTo: position,
                query: '',
                items: {},
                itemsType: '',
                dataSourceKey: '',
                dataSourceValue: ''
            };

            var tokens = this.expressionLanguage.getLexer().tokenizeForce(expression).tokens;
            var currentTokenIndex = this._findCurrentTokenIndex(tokens, position);
            if (currentTokenIndex === -1) {
                this._fillRootEntitiesOptions(autocompleteData);
                return autocompleteData;
            }
            var fieldChain = this.findFieldChain(tokens, currentTokenIndex, this.options.rootEntities);
            var currentToken = tokens[currentTokenIndex];
            if (fieldChain) {
                if (this.dataSourceNames.indexOf(fieldChain.entity.value) !== -1) {
                    var entityWithDataSource = _.values(_.pick(fieldChain, 'entity', 'dataSourceOpenBracket',
                        'dataSourceValue', 'dataSourceCloseBracket'));
                    if (entityWithDataSource.length >= 3 && entityWithDataSource.indexOf(currentToken) !== -1) {
                        this._fillDataSourceOptions(autocompleteData, fieldChain);
                        return autocompleteData;
                    }
                }
                this._fillEntityFieldOptions(autocompleteData, fieldChain);
            } else if (currentToken.test(Token.OPERATOR_TYPE)) {
                this._fillOperatorsOptions(autocompleteData, currentToken);
            } else if (currentToken.test(Token.NAME_TYPE)) {
                this._fillRootEntitiesOptions(autocompleteData, currentToken);
            } else if (currentToken.test(Token.EOF_TYPE)) {
                var prevToken = currentTokenIndex === 0 ? null : tokens[currentTokenIndex - 1];
                if (!prevToken || prevToken.test(Token.OPERATOR_TYPE) || prevToken.test(Token.PUNCTUATION_TYPE, '(')) {
                    this._fillRootEntitiesOptions(autocompleteData, currentToken);
                } else {
                    this._fillOperatorsOptions(autocompleteData);
                }
            }

            return autocompleteData;
        },

        /**
         * Fill autocomplete data with root entities items
         *
         * @param {AutocompleteData} autocompleteData
         * @param {Token} [currentToken]
         * @private
         */
        _fillRootEntitiesOptions: function(autocompleteData, currentToken) {
            autocompleteData.itemsType = 'entities';
            autocompleteData.items = {};
            _.each(this.options.rootEntities, function(alias) {
                autocompleteData.items[alias] = {
                    item: alias,
                    hasChildren: true
                };
            }, this);
            if (currentToken && currentToken.test(Token.NAME_TYPE)) {
                autocompleteData.query = currentToken.value;
                autocompleteData.replaceFrom = currentToken.cursor - 1;
                autocompleteData.replaceTo = currentToken.cursor + currentToken.length;
            }
        },

        /**
         * Fill autocomplete data with data source options
         *
         * @param {AutocompleteData} autocompleteData
         * @param {FieldChain} fieldChain
         * @private
         */
        _fillDataSourceOptions: function(autocompleteData, fieldChain) {
            autocompleteData.itemsType = 'datasource';
            autocompleteData.dataSourceKey = fieldChain.entity.value;
            if (fieldChain.dataSourceValue) {
                autocompleteData.dataSourceValue = fieldChain.dataSourceValue.value;
                autocompleteData.replaceFrom = fieldChain.dataSourceValue.cursor - 1;
                autocompleteData.replaceTo =
                    autocompleteData.replaceFrom + fieldChain.dataSourceValue.length;
            } else {
                autocompleteData.replaceFrom =
                    autocompleteData.replaceTo = fieldChain.dataSourceCloseBracket.cursor - 1;
            }
            autocompleteData.position = fieldChain.lastToken.cursor - 1 + fieldChain.lastToken.length;
        },

        /**
         * Fill autocomplete data with entity fields items
         *
         * @param {AutocompleteData} autocompleteData
         * @param {FieldChain} fieldChain
         * @private
         */
        _fillEntityFieldOptions: function(autocompleteData, fieldChain) {
            autocompleteData.itemsType = 'entities';
            if (fieldChain.lastToken.test(Token.PUNCTUATION_TYPE, '.')) {
                autocompleteData.replaceFrom = fieldChain.lastToken.cursor - 1 + fieldChain.lastToken.length;
                autocompleteData.replaceTo = autocompleteData.replaceFrom;
            } else if (fieldChain.fields.length !== 0) {
                var lastToken = fieldChain.fields.pop();
                autocompleteData.query = lastToken.value;
                autocompleteData.replaceFrom = fieldChain.lastToken.cursor - 1;
                autocompleteData.replaceTo = autocompleteData.replaceFrom + fieldChain.lastToken.length;
            }
            var parts = _.pluck(fieldChain.fields, 'value');
            parts.unshift(fieldChain.entity.value);
            var omitRelationFields = this.options.itemLevelLimit <= parts.length + 1;
            var levelLimit = this.options.itemLevelLimit - parts.length;
            var treeNode = this.entityDataProvider
                .getEntityTreeNodeByPropertyPath(parts.join(this.strings.childSeparator));
            if (levelLimit > 0 && treeNode && treeNode.__isEntity) {
                _.each(treeNode, function(node) {
                    var isEntity = node.__isEntity;
                    if (isEntity && (omitRelationFields || !node.__hasScalarFieldsInSubtree(levelLimit))) {
                        return;
                    }
                    var item = _.extend(_.pick(node.__field, 'label', 'type', 'name'), {
                        hasChildren: isEntity
                    });
                    autocompleteData.items[item.name] = item;
                }, this);
            }
        },

        /**
         * Fill autocomplete data with operations items
         *
         * @param {AutocompleteData} autocompleteData
         * @param {Token} [currentToken]
         * @private
         */
        _fillOperatorsOptions: function(autocompleteData, currentToken) {
            autocompleteData.itemsType = 'operations';
            autocompleteData.items = this.operationsItems;
            if (currentToken !== void 0) {
                autocompleteData.query = currentToken.value;
                autocompleteData.replaceFrom = currentToken.cursor - 1;
                autocompleteData.replaceTo = currentToken.cursor + currentToken.length;
            }
        },

        /**
         * Update autocomplete expression by item parts
         *
         * @param {AutocompleteData} autocompleteData
         * @param {string} value - string that is selected in autocomplete widget
         * @private
         */
        _updateAutocompleteExpression: function(autocompleteData, value) {
            autocompleteData.expression = autocompleteData.expression.substr(0, autocompleteData.replaceFrom) +
                value + autocompleteData.expression.substr(autocompleteData.replaceTo);
        },

        /**
         * Insert into autocomplete data new item
         *
         * @param {AutocompleteData} autocompleteData
         * @param {string} item - selected value in autocomplete widget
         */
        updateAutocompleteItem: function(autocompleteData, item) {
            var positionModifier = 0;
            if (autocompleteData.itemsType === 'entities') {
                var hasChildren = autocompleteData.items[item].hasChildren;
                if (this.dataSourceNames.indexOf(item) !== -1) {
                    item += '[]';
                    positionModifier = hasChildren ? -2 : -1;
                }

                item += hasChildren ? this.strings.childSeparator : this.strings.itemSeparator;
            } else {
                item += this.strings.itemSeparator;
            }

            this._updateAutocompleteExpression(autocompleteData, item);

            autocompleteData.position = autocompleteData.replaceFrom + item.length + positionModifier;
        },

        /**
         * Set new data source value into autocomplete data
         *
         * @param {AutocompleteData} autocompleteData
         * @param {string} dataSourceValue - selected value in datasource widget
         */
        updateDataSourceValue: function(autocompleteData, dataSourceValue) {
            var diff = autocompleteData.replaceTo - autocompleteData.replaceFrom - dataSourceValue.length;
            this._updateAutocompleteExpression(autocompleteData, dataSourceValue);
            autocompleteData.position -= diff;
        },

        /**
         * Generate list of operations autocomplete items from initialize options
         *
         * @private
         */
        _prepareOperationsItems: function() {
            this.operationsItems = {};
            _.each(this.options.allowedOperations, function(type) {
                _.each(this.options.operations[type], function(item) {
                    this.operationsItems[item] = {
                        type: type,
                        item: item
                    };
                }, this);
            }, this);
        },

        /**
         * Finds index of token that corresponds to current cursor position.
         *
         * @param {Array.<Token>} tokens
         * @param {number} position
         * @return {number} - if token is not found returns -1
         * @private
         */
        _findCurrentTokenIndex: function(tokens, position) {
            for (var i = 0; i < tokens.length; i++) {
                if (tokens[i].cursor -1 <= position && tokens[i].cursor + tokens[i].length > position) {
                    return i;
                }
            }
            return -1;
        },

        /**
         * Creates field chain that current token is contained in.
         *
         * @param {Array.<Token>} tokens
         * @param {number} currentTokenIndex
         * @param {Array.<string>} names - names that can be used as start of a chain
         * @return {FieldChain|null}
         */
        findFieldChain: function(tokens, currentTokenIndex, names) {
            var chain = null;
            var i = 0;
            while (i < tokens.length && i <= currentTokenIndex) {
                // looking for start of field chain
                if (!tokens[i].test(Token.NAME_TYPE) || names.indexOf(tokens[i].value) === -1) {
                    i++;
                    continue;
                }
                chain = {
                    entity: tokens[i],
                    fields: []
                };
                i++;
                if (tokens[i].test(Token.PUNCTUATION_TYPE, '[')) {
                    chain.dataSourceOpenBracket = tokens[i];
                    i++;
                    if (tokens[i].test(Token.NUMBER_TYPE)) {
                        chain.dataSourceValue = tokens[i];
                        i++;
                    }
                    if (tokens[i].test(Token.PUNCTUATION_TYPE, ']')) {
                        chain.dataSourceCloseBracket = tokens[i];
                        i++;
                    } else {
                        chain = null;
                        continue;
                    }
                }

                // collect separators and field names that should alternate one after another
                while (tokens[i].test(Token.PUNCTUATION_TYPE, '.')) {
                    i++;
                    if (tokens[i].test(Token.NAME_TYPE)) {
                        chain.fields.push(tokens[i]);
                        i++;
                    } else {
                        break;
                    }
                }

                chain.lastToken = tokens[i - 1];

                // if last chain member located before current token in the token array then try to find another chain
                if (i <= currentTokenIndex || chain.lastToken === chain.entity) {
                    chain = null;
                }
                i++;
            }

            return chain;
        }
    });

    return ExpressionEditorUtil;
});
