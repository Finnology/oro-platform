define(function(require) {
    'use strict';

    var _ = require('underscore');

    function isPlainObject(obj) {
        return typeof obj === 'object' && obj !== null && obj.constructor === Object;
    }

    /**
     * Simplified validator for data structure
     * based on idea of http://json-schema.org/
     * partially supports https://spacetelescope.github.io/understanding-json-schema/reference/index.html
     */
    return {
        /**
         * Validates data against schema
         *
         * @param {Object} schema
         * @param {*} value
         *
         * @return {boolean}
         */
        validate: function(schema, value) {
            switch (schema.type) {
                case 'object': // "dependencies" and "patternProperties" are not supported
                    return isPlainObject(value) &&
                        this.checkMinMaxProperties(schema, value) &&
                        this.checkRequiredProperties(schema, value) &&
                        this.checkProperties(schema, value) &&
                        this.checkAdditionalProperties(schema, value);

                case 'array':
                    return _.isArray(value) &&
                        this.checkMinItems(schema, value) &&
                        this.checkMaxItems(schema, value) &&
                        this.checkUniqueItems(schema, value) &&
                        this.checkItemsSchema(schema, value) &&
                        this.checkItemsTuple(schema, value);

                case 'string': // "format" is not supported
                    return _.isString(value) &&
                        this.checkMinLength(schema, value) &&
                        this.checkMaxLength(schema, value) &&
                        this.checkEnum(schema, value) &&
                        this.checkPattern(schema, value);

                case 'integer': // "multipleOf" is not supported
                    return _.isNumber(value) &&
                        parseInt(value) === value &&
                        this.checkMinimum(schema, value) &&
                        this.checkMaximum(schema, value) &&
                        this.checkEnum(schema, value);

                case 'number': // "multipleOf" is not supported
                    return _.isNumber(value) &&
                        this.checkMinimum(schema, value) &&
                        this.checkMaximum(schema, value) &&
                        this.checkEnum(schema, value);

                case 'boolean':
                    return _.isBoolean(value) &&
                        this.checkEnum(schema, value);

                case 'null':
                    return value === null;

                default:
                    if (_.isArray(schema.type)) {
                        return this.checkAnyOfType(schema, value);
                    } else if (!schema.type) {
                        return this.checkEnum(schema, value);
                    }
                    throw new Error('Can not validate JSON of unknown or incorrect type');
            }
        },

        /**
         * Checks if the value within enum (in case enum is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @return {boolean}
         * @protected
         */
        checkEnum: function(schema, value) {
            return !schema.enum || schema.enum.indexOf(value) !== -1;
        },

        /**
         * Checks if the value has greater or equal to minLength (in case minLength is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @return {boolean}
         * @protected
         */
        checkMinLength: function(schema, value) {
            return !('minLength' in schema) || value.length >= schema.minLength;
        },

        /**
         * Checks if the value has less or equal to maxLength (in case maxLength is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @return {boolean}
         * @protected
         */
        checkMaxLength: function(schema, value) {
            return !('maxLength' in schema) || value.length <= schema.maxLength;
        },

        /**
         * Checks if the value matches the pattern (in case a pattern is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @return {boolean}
         * @protected
         */
        checkPattern: function(schema, value) {
            return !schema.pattern || (new RegExp(schema.pattern)).test(value);
        },

        /**
         * Checks if the value is greater or equals (in case minimum and exclusiveMinimum are defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @return {boolean}
         * @protected
         */
        checkMinimum: function(schema, value) {
            return !('minimum' in schema) ||
                !schema.exclusiveMinimum && value >= schema.minimum || value > schema.minimum;
        },

        /**
         * Checks if the value is less or equals (in case maximum and exclusiveMaximum are defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @return {boolean}
         * @protected
         */
        checkMaximum: function(schema, value) {
            return !('maximum' in schema) ||
                !schema.exclusiveMaximum && value <= schema.maximum || value < schema.maximum;
        },

        /**
         * Checks if the value has greater or equals items of minItems (in case minItems is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkMinItems: function(schema, value) {
            return !('minItems' in schema) || value.length >= schema.minItems;
        },

        /**
         * Checks if the value has less or equals items of maxItems (in case maxItems is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkMaxItems: function(schema, value) {
            return !('maxItems' in schema) || value.length <= schema.maxItems;
        },

        /**
         * Checks if the value has unique items (in case uniqueItems is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkUniqueItems: function(schema, value) {
            return !schema.uniqueItems || _.uniq(value).length === value.length;
        },

        /**
         * Checks if all items of the value matches its schema (in case items is defined as pain object of JSON schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkItemsSchema: function(schema, value) {
            return !schema.items || !isPlainObject(schema.items) ||
                _.every(value, _.partial(this.validate.bind(this), schema.items));
        },

        /**
         * Checks if sequence of items in the value matches sequence items in schema
         * (in case items is defined as array of JSON schema objects)
         * takes in account restriction for additionalItems
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkItemsTuple: function(schema, value) {
            return !schema.items || !_.isArray(schema.items) ||
                (schema.additionalItems !== false || schema.items.length >= value.length)
                && _.every(value, function(val, i) {
                    return !schema.items[i] || this.validate(schema.items[i], val);
                }, this);
        },

        /**
         * Checks if the value object has proper number of properties
         * (in case minProperties or/and maxProperties are defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkMinMaxProperties: function(schema, value) {
            var number;
            return !('minProperties' in schema || 'maxProperties' in schema) ||
                (number = _.keys(value).length) !== null &&
                (!('minProperties' in schema) || number >= schema.minProperties) &&
                (!('maxProperties' in schema) || number <= schema.maxProperties);
        },

        /**
         * Checks if all required properties are in the value object
         * (in case required is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkRequiredProperties: function(schema, value) {
            return !schema.required || _.difference(schema.required, _.keys(value)).length === 0;
        },

        /**
         * Checks if every property of the value object matches its schema
         * (in case properties is defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkProperties: function(schema, value) {
            return !schema.properties || _.every(value, function(val, prop) {
                return !(prop in schema.properties) || this.validate(schema.properties[prop], val);
            }, this);
        },

        /**
         * Checks if additional properties of the value object are allowed
         * and if they match additionalProperties if it represents the schema
         * (in case properties and additionalProperties are defined in schema)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkAdditionalProperties: function(schema, value) {
            var addProps;
            return !('additionalProperties' in schema && schema.properties) ||
                (addProps = _.difference(_.keys(value), _.keys(schema.properties))) !== null &&
                (
                    schema.additionalProperties === false && addProps.length === 0 ||
                    isPlainObject(schema.additionalProperties) && _.every(addProps, function(prop) {
                        return this.validate(schema.additionalProperties, value[prop]);
                    }, this)
                );
        },

        /**
         * Checks if the value matches to any of types
         * (in case type is defined as array)
         *
         * @param {Object} schema
         * @param {*} value
         * @returns {boolean}
         */
        checkAnyOfType: function(schema, value) {
            return !_.isArray(schema.type) || _.any(schema.type, function(type) {
                return this.validate({type: type}, value);
            }, this);
        }
    };
});
