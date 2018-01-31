define(function(require) {
    'use strict';

    var ArgumentsNode = require('oroexpressionlanguage/js/library/node/arguments-node');
    var ArrayNode = require('oroexpressionlanguage/js/library/node/array-node');
    var BinaryNode = require('oroexpressionlanguage/js/library/node/binary-node');
    var ConditionalNode = require('oroexpressionlanguage/js/library/node/conditional-node');
    var ConstantNode = require('oroexpressionlanguage/js/library/node/constant-node');
    var FunctionNode = require('oroexpressionlanguage/js/library/node/function-node');
    var GetAttrNode = require('oroexpressionlanguage/js/library/node/get-attr-node');
    var NameNode = require('oroexpressionlanguage/js/library/node/name-node');
    var Node = require('oroexpressionlanguage/js/library/node/node');
    var UnaryNode = require('oroexpressionlanguage/js/library/node/unary-node');
    var ExpressionSyntaxError = require('oroexpressionlanguage/js/library/expression-syntax-error');
    var Token = require('oroexpressionlanguage/js/library/token');

    /**
     * @param {Object.<string, ExpressionFunction>} functions an list of declared functions
     */
    function Parser(functions) {
        this.functions = functions;
    }

    Object.defineProperties(Parser.prototype, {
        OPERATOR_LEFT: {value: 1},
        OPERATOR_RIGHT: {value: 2}
    });

    Object.assign(Parser.prototype, {
        constructor: Parser,

        unaryOperators: {
            'not': {precedence: 50},
            '!': {precedence: 50},
            '-': {precedence: 500},
            '+': {precedence: 500}
        },

        binaryOperators: {
            'or': {precedence: 10, associativity: Parser.prototype.OPERATOR_LEFT},
            '||': {precedence: 10, associativity: Parser.prototype.OPERATOR_LEFT},
            'and': {precedence: 15, associativity: Parser.prototype.OPERATOR_LEFT},
            '&&': {precedence: 15, associativity: Parser.prototype.OPERATOR_LEFT},
            '|': {precedence: 16, associativity: Parser.prototype.OPERATOR_LEFT},
            '^': {precedence: 17, associativity: Parser.prototype.OPERATOR_LEFT},
            '&': {precedence: 18, associativity: Parser.prototype.OPERATOR_LEFT},
            '==': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '===': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '!=': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '!==': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '<': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '>': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '>=': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '<=': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            'not in': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            'in': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            'matches': {precedence: 20, associativity: Parser.prototype.OPERATOR_LEFT},
            '..': {precedence: 25, associativity: Parser.prototype.OPERATOR_LEFT},
            '+': {precedence: 30, associativity: Parser.prototype.OPERATOR_LEFT},
            '-': {precedence: 30, associativity: Parser.prototype.OPERATOR_LEFT},
            '~': {precedence: 40, associativity: Parser.prototype.OPERATOR_LEFT},
            '*': {precedence: 60, associativity: Parser.prototype.OPERATOR_LEFT},
            '/': {precedence: 60, associativity: Parser.prototype.OPERATOR_LEFT},
            '%': {precedence: 60, associativity: Parser.prototype.OPERATOR_LEFT},
            '**': {precedence: 200, associativity: Parser.prototype.OPERATOR_RIGHT}
        },

        /**
         * Converts a token stream to a node tree.
         *
         * The valid names is a hash object where the values
         * are the names that the user can use in an expression.
         *
         * If the variable name in the compiled JS code must be
         * different, define it as the key.
         *
         * For instance, {'this': 'container'} means that the
         * variable 'container' can be used in the expression
         * but the compiled code will use 'this'.
         *
         * @param {TokenStream} stream a token stream instance
         * @param {Object|Array} [names] hash object or array of valid names
         *
         * @return {Node} a node tree
         *
         * @throws {ExpressionSyntaxError}
         */
        parse: function(stream, names) {
            this.stream = stream;
            this.names = names || [];

            var node = this.parseExpression();
            if (!stream.isEOF()) {
                var message = 'Unexpected token "' + stream.current.type + '" of value "' + stream.current.value + '"';
                throw new ExpressionSyntaxError(message, stream.current.cursor, stream.getExpression());
            }

            return node;
        },

        /**
         * @param {number} [precedence]
         * @return {Node}
         */
        parseExpression: function(precedence) {
            precedence = precedence || 0;
            var expr = this.getPrimary();
            var token = this.stream.current;

            while (
                token.test(Token.OPERATOR_TYPE) &&
                token.value in this.binaryOperators &&
                this.binaryOperators[token.value].precedence >= precedence
            ) {
                var operator = this.binaryOperators[token.value];
                this.stream.next();
                var precedence1 = this.OPERATOR_LEFT === operator.associativity ?
                    operator.precedence + 1 : operator.precedence;
                var expr1 = this.parseExpression(precedence1);
                expr = new BinaryNode(token.value, expr, expr1);

                token = this.stream.current;
            }

            if (0 === precedence) {
                return this.parseConditionalExpression(expr);
            }

            return expr;
        },

        /**
         * @return {Node}
         */
        getPrimary: function() {
            var operator;
            var expr;
            var token = this.stream.current;

            if (token.test(Token.OPERATOR_TYPE) && token.value in this.unaryOperators) {
                operator = this.unaryOperators[token.value];
                this.stream.next();
                expr = this.parseExpression(operator.precedence);

                return this.parsePostfixExpression(new UnaryNode(token.value, expr));
            }

            if (token.test(Token.PUNCTUATION_TYPE, '(')) {
                this.stream.next();
                expr = this.parseExpression();
                this.stream.expect(Token.PUNCTUATION_TYPE, ')', 'An opened parenthesis is not properly closed');

                return this.parsePostfixExpression(expr);
            }

            return this.parsePrimaryExpression();
        },

        /**
         * @param {Node} expr
         * @return {Node}
         */
        parseConditionalExpression: function(expr) {
            var expr2;
            var expr3;
            while (this.stream.current.test(Token.PUNCTUATION_TYPE, '?')) {
                this.stream.next();
                if (!this.stream.current.test(Token.PUNCTUATION_TYPE, ':')) {
                    expr2 = this.parseExpression();
                    if (this.stream.current.test(Token.PUNCTUATION_TYPE, ':')) {
                        this.stream.next();
                        expr3 = this.parseExpression();
                    } else {
                        expr3 = new ConstantNode(null);
                    }
                } else {
                    this.stream.next();
                    expr2 = expr;
                    expr3 = this.parseExpression();
                }

                expr = new ConditionalNode(expr, expr2, expr3);
            }

            return expr;
        },

        /**
         * @return {Node}
         */
        parsePrimaryExpression: function() {
            var node;
            var name;
            var message;
            var token = this.stream.current;
            switch (token.type) {
                case Token.NAME_TYPE:
                    this.stream.next();
                    switch (token.value) {
                        case 'true':
                        case 'TRUE':
                            return new ConstantNode(true);

                        case 'false':
                        case 'FALSE':
                            return new ConstantNode(false);

                        case 'null':
                        case 'NULL':
                            return new ConstantNode(null);

                        default:
                            if ('(' === this.stream.current.value) {
                                if (!(token.value in this.functions)) {
                                    message = 'The function "' + token.value + '" does not exist';
                                    throw new ExpressionSyntaxError(message, token.cursor, this.stream.getExpression());
                                }

                                node = new FunctionNode(token.value, this.parseArguments());
                            } else {
                                var nameIndex = Object.values(this.names).indexOf(token.value);
                                if (nameIndex === -1) {
                                    message = 'Variable "' + token.value + '" is not valid';
                                    throw new ExpressionSyntaxError(message, token.cursor, this.stream.getExpression());
                                }
                                // is the name used in the compiled code different
                                // from the name used in the expression?
                                name = Object.keys(this.names)[nameIndex];
                                if (name >= 0 && name % 1 === 0) {
                                    name = token.value;
                                }

                                node = new NameNode(name);
                            }
                    }
                    break;

                case Token.NUMBER_TYPE:
                case Token.STRING_TYPE:
                    this.stream.next();

                    return new ConstantNode(token.value);

                default:
                    if (token.test(Token.PUNCTUATION_TYPE, '[')) {
                        node = this.parseArrayExpression();
                    } else if (token.test(Token.PUNCTUATION_TYPE, '{')) {
                        node = this.parseHashExpression();
                    } else {
                        message = 'Unexpected token "' + token.value + '" of value "' + token.value + '"';
                        throw new ExpressionSyntaxError(message, token.cursor, this.stream.getExpression());
                    }
            }

            return this.parsePostfixExpression(node);
        },

        /**
         * @return {Node}
         */
        parseArrayExpression: function() {
            this.stream.expect(Token.PUNCTUATION_TYPE, '[', 'An array element was expected');

            var node = new ArrayNode();
            var first = true;
            while (!this.stream.current.test(Token.PUNCTUATION_TYPE, ']')) {
                if (!first) {
                    this.stream.expect(Token.PUNCTUATION_TYPE, ',', 'An array element must be followed by a comma');

                    // trailing ,?
                    if (this.stream.current.test(Token.PUNCTUATION_TYPE, ']')) {
                        break;
                    }
                }
                first = false;

                node.addElement(this.parseExpression());
            }
            this.stream.expect(Token.PUNCTUATION_TYPE, ']', 'An opened array is not properly closed');

            return node;
        },

        /**
         * @return {Node}
         */
        parseHashExpression: function() {
            this.stream.expect(Token.PUNCTUATION_TYPE, '{', 'A hash element was expected');

            var current;
            var key;
            var value;
            var node = new ArrayNode();
            var first = true;
            while (!this.stream.current.test(Token.PUNCTUATION_TYPE, '}')) {
                if (!first) {
                    this.stream.expect(Token.PUNCTUATION_TYPE, ',', 'A hash value must be followed by a comma');

                    // trailing ,?
                    if (this.stream.current.test(Token.PUNCTUATION_TYPE, '}')) {
                        break;
                    }
                }
                first = false;

                // a hash key can be:
                //
                //  * a number -- 12
                //  * a string -- 'a'
                //  * a name, which is equivalent to a string -- a
                //  * an expression, which must be enclosed in parentheses -- (1 + 2)
                if (
                    this.stream.current.test(Token.STRING_TYPE) ||
                    this.stream.current.test(Token.NAME_TYPE) ||
                    this.stream.current.test(Token.NUMBER_TYPE)
                ) {
                    key = new ConstantNode(this.stream.current.value);
                    this.stream.next();
                } else if (this.stream.current.test(Token.PUNCTUATION_TYPE, '(')) {
                    key = this.parseExpression();
                } else {
                    current = this.stream.current;

                    var message = 'A hash key must be a quoted string, a number, a name, or an expression enclosed ' +
                        'in parentheses (unexpected token "' + current.type + '" of value "' + current.value + '"';
                    throw new ExpressionSyntaxError(message, current.cursor, this.stream.getExpression());
                }

                this.stream.expect(Token.PUNCTUATION_TYPE, ':', 'A hash key must be followed by a colon (:)');
                value = this.parseExpression();

                node.addElement(value, key);
            }
            this.stream.expect(Token.PUNCTUATION_TYPE, '}', 'An opened hash is not properly closed');

            return node;
        },

        /**
         * @param {Node} node
         * @return {Node}
         */
        parsePostfixExpression: function(node) {
            var type;
            var attr;
            var args;
            var token = this.stream.current;
            while (Token.PUNCTUATION_TYPE === token.type) {
                if ('.' === token.value) {
                    this.stream.next();
                    token = this.stream.current;
                    this.stream.next();

                    if (
                        Token.NAME_TYPE !== token.type &&
                        // Operators like "not" and "matches" are valid method or property names,
                        //
                        // In other words, besides NAME_TYPE, OPERATOR_TYPE could also be parsed as a property or method.
                        // This is because operators are processed by the lexer prior to names. So "not" in "foo.not()" or "matches" in "foo.matches" will be recognized as an operator first.
                        // But in fact, "not" and "matches" in such expressions shall be parsed as method or property names.
                        //
                        // And this ONLY works if the operator consists of valid characters for a property or method name.
                        //
                        // Other types, such as STRING_TYPE and NUMBER_TYPE, can't be parsed as property nor method names.
                        //
                        // As a result, if token is NOT an operator OR token.value is NOT a valid property or method name, an exception shall be thrown.
                        (
                            Token.OPERATOR_TYPE !== token.type ||
                            !/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/.test(token.value)
                        )
                    ) {
                        throw new ExpressionSyntaxError('Expected name', token.cursor, this.stream.getExpression());
                    }

                    attr = new ConstantNode(token.value);

                    args = new ArgumentsNode();
                    if (this.stream.current.test(Token.PUNCTUATION_TYPE, '(')) {
                        type = GetAttrNode.METHOD_CALL;
                        var nodes = this.parseArguments().nodes;
                        for (var i = 0; i < nodes.length; i++) {
                            args.addElement(nodes[i]);
                        }
                    } else {
                        type = GetAttrNode.PROPERTY_CALL;
                    }

                    node = new GetAttrNode(node, attr, args, type);
                } else if ('[' === token.value) {
                    this.stream.next();
                    attr = this.parseExpression();
                    this.stream.expect(Token.PUNCTUATION_TYPE, ']');

                    node = new GetAttrNode(node, attr, new ArgumentsNode(), GetAttrNode.ARRAY_CALL);
                } else {
                    break;
                }

                token = this.stream.current;
            }

            return node;
        },

        /**
         * Parses arguments
         *
         * @return {Node}
         */
        parseArguments: function() {
            var args = [];
            this.stream
                .expect(Token.PUNCTUATION_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
            while (!this.stream.current.test(Token.PUNCTUATION_TYPE, ')')) {
                if (args.length) {
                    this.stream.expect(Token.PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');
                }

                args.push(this.parseExpression());
            }
            this.stream.expect(Token.PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');

            return new Node(args);
        }
    });

    return Parser;
});
