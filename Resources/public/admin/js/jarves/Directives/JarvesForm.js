jarves.Directives.JarvesForm = new Class({
    Statics: {
        $inject: ['$scope', '$element', '$attrs', '$compile']
    },
    JarvesDirective: {
        name: 'jarvesForm',
        options: {
            restrict: 'E',
            scope: {
                'fields': '=',
                'model': '='
            },
            controller: true
        }
    },

    initialize: function($scope, $element, $attributes, $compile) {
        this.$scope = $scope;
        this.$element = $element;
        this.$attributes = $attributes;
        this.$compile = $compile;
    },

    link: function() {
        this.$scope.$watch('fields', function(fields) {
            var xml = this.buildXml(fields, 'fields');
            this.$element.html(xml);
            this.$compile(this.$element.contents())(this.$scope);
        }.bind(this));
    },

    buildXml: function(fields, parentModelName, depth) {
        var xml = [];

        depth = depth || 0;

        var spacing = ' '.repeat(depth * 4);

        Object.each(fields, function(field, id) {
            field.id = field.id || id;

            var modelName = parentModelName + '.' + id;

            var line = spacing + '<jarves-field definition="%s">\n'.sprintf(modelName);
            if (field.children) {
                line += this.buildXml(field.children, modelName + '.children', depth + 1);
            }
            line += spacing + '</jarves-field>\n';
            xml.push(line);
        }.bind(this));

        return xml.join("\n");
    }
});