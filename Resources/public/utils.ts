import {baseUrl, baseUrlApi} from './config';
// import angular from './angular';

/**
 * Is true if the current browser has a mobile user agent.
 * @return {Boolean}
 */
export function isMobile() {
    return navigator.userAgent.match(/Android/i) || navigator.userAgent.match(/iPhone/i) || navigator.userAgent.match(/webOS/i) || navigator.userAgent.match(/iPad/i) || navigator.userAgent.match(/iPod/i) || navigator.userAgent.match(/BlackBerry/i) || navigator.userAgent.match(/Windows Phone/i);
}

/**
 * Function to iterate over array and object key,value pairs.
 *
 * Use it like:
 *
 * for (let [key, value] of each(myObject)) {
 * }
 *
 */
export function each(obj) {
    var result = [];

    for (let key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            //yield [key, obj[key]];
            result.push([key, obj[key]]);
        }
    }

    return result;
}

/**
 * Function to iterate over array and object values.
 */
export function eachValue(obj) {
    var result = [];
    for (let key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            //yield obj[key];
            result.push(obj[key]);
        }
    }

    return result;
}
/**
 * Function to iterate over array and object keys.
 */
export function eachKey(obj) {
    var result = [];
    for (let key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            //yield key;
            result.push(key);
        }
    }

    return result;
}

/**
 * Just for console hacking.
 */
window.applyRootScope = function () {
    angular.element(document).scope().$apply();
};

export function isDifferent(a, b) {
    var changed;
    if (typeof a === 'undefined' && typeof b !== 'undefined') return true;
    if (typeof a !== 'undefined' && typeof b === 'undefined') return true;

    if (angular.isObject(a)) {
        changed = false;
        if (Object.keys(a).length !== Object.keys(b).length) {
            return true;
        }

        for (let [k, v] of each(a)) {
            if (changed) return false;
            changed = isDifferent(v, b[k]);
        }
        return changed;
    }

    if (angular.isArray(a)) {
        changed = false;
        if (a.length !== b.length) {
            return true;
        }
        for (let [k, v] of each(a)) {
            if (changed) return false;
            changed = isDifferent(v, b[k]);
        }
        return changed;
    }

    return !angular.equals(a, b);
}

export function logger(...args) {
    args.unshift('Logger: ');
    console.log.apply(console, args);
}

// /**
// * Replaces all <, >, & and " with html so you can use it in safely innerHTML.
// *
// * @param {String}   value
// * @returns {string} Safe for innerHTML usage.
// */
// export function htmlEntities(value) {
//     if (!value) return '';
//     if (angular.isArray(value)) {

//         Array.each(value, function(v, k) {
//             value[k] = htmlEntities(v);
//         });
//         return value;
//     }
//     if ('object' === typeOf(value)) {
//         Object.each(value, function(v, k) {
//             value[k] = htmlEntities(v);
//         });
//         return value;
//     }
//     if ('element' === typeOf(value)) {
//         return value;
//     }
//     return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
// }

/**
 *
 * @param path
 */
export function getPublicPath(path) {
    var matches = path.match(/@([a-zA-Z_]*)\/(.*)/);
    if (matches && matches[1]) {
        path = 'bundles/' + getShortBundleName(matches[1]) + '/' + matches[2];
    }
    return baseUrl + path;
}

window.countWatchers = function () {
    var root = angular.element(document);
    var watchers = [];

    var f = function (element) {
        if (element.data().hasOwnProperty('$scope')) {
            angular.forEach(element.data().$scope.$$watchers, function (watcher) {
                watchers.push(watcher);
            });
        }

        angular.forEach(element.children(), function (childElement) {
            f(angular.element(childElement));
        });
    };

    f(root);

    return watchers.length;
}

/**
 * Adds a prefix to the keys of pFields.
 * Good to group some values of fields of jarves.FieldForm.
 *
 * Example:
 *
 *   fields = {
*      field1: {type: 'text', label: 'Field 1'},
*      field2: {type: 'checkbox', label: 'Field 2'}
*   }
 *
 *   prefix = 'options'
 *
 *   fields will be changed to:
 *   {
*      'options[field1]': {type: 'text', label: 'Field 1'},
*      'options[field2]': {type: 'checkbox', label: 'Field 2'}
*   }
 *
 * @param {Array} fields Reference to object.
 * @param {String} prefix
 */
export function addFieldKeyPrefix(fields, prefix) {
    for (let [key, field] of each(fields)) {
        fields[prefix + '[' + key + ']'] = field;
        delete fields[key];
        if (fields.children) {
            addFieldKeyPrefix(field.children, prefix);
        }
    }
}

/**
 * Resolve path notations and returns the appropriate class.
 *
 * @param {String} classPath
 * @return {Class|Function}
 */
export function getClass(classPath) {
    classPath = classPath.replace('[\'', '.');
    classPath = classPath.replace('\']', '.');

    if (classPath.indexOf('.') > 0) {
        var path = classPath.split('.');
        var clazz = null;
        Array.each(path, function (item) {
            clazz = clazz ? clazz[item] : window[item];
        });
        return clazz;
    }

    return window[classPath];
}

/**
 * Encodes a value from url usage.
 * If Array, it encodes the whole array an implodes it with comma.
 * If Object, it encodes the whole object and implodes the <key>=<value> pairs with a comma.
 *
 * @param {String} value
 *
 * @return {String}
 */
export function urlEncode(value) {
    var result;
    if (angular.isString(value)) {
        return encodeURIComponent(value).replace(/\%2F/g, '%252F'); //fix apache default setting
    } else if (angular.isArray(value)) {
        result = '';
        Array.each(value, function (item) {
            result += urlEncode(item) + ',';
        });
        return result.substr(0, result.length - 1);
    } else if (angular.isObject(value)) {
        result = '';
        Array.each(value, function (item, key) {
            result += key + '=' + urlEncode(item) + ',';
        });
        return result.substr(0, result.length - 1);
    }

    return value;
}

/**
 * Decodes a value for url usage.
 *
 * @param {String} value
 *
 * @return {String}
 */
export function urlDecode(value) {
    if (!angular.isString(value)) {
        return value;
    }

    try {
        return decodeURIComponent(value.replace(/%252F/g, '%2F'));
    } catch (e) {
        return value;
    }
}

/**
 * Normalizes a objectKey.
 *
 * @param {String} objectKey
 *
 * @returns {String|Null}
 */
export function normalizeObjectKey(objectKey) {
    objectKey = objectKey.replace('\\', '/').replace('.', '/').replace(':', '/');
    var bundleName = objectKey.split('/')[0].toLowerCase().replace(/bundle$/, '');
    var objectName = objectKey.split('/')[1];

    if (!bundleName || !objectName) {
        return null;
    }

    return bundleName + '/' + objectName.lcfirst();
}

/**
 * Normalizes a entryPoint path.
 *
 * Example
 *
 *   JarvesBundle/entry/point/path
 *   => jarves/entry/point/path
 *
 *
 * @param {String} path
 *
 * @returns {String}
 */
export function normalizeEntryPointPath(path) {
    var slash = path.indexOf('/');

    return getShortBundleName(path.substr(0, slash)) + path.substr(slash);
}

/**
 * Returns a absolute path.
 * If path begins with # it returns path
 * if path is not a string it returns path
 * if path contains http:// on the beginning it returns path
 *
 * @param {String} path
 *
 * @return {String}
 */
export function mediaPath(path) {

    if (!angular.isString(path)) {
        return path;
    }

    if (path.substr(0, 1) == '#') {
        return path;
    }

    if (path.substr(0, 1) == '/') {
        return baseUrl + path.substr(1);
    } else if (path.substr(0, 7) == 'http://') {
        return path;
    } else {
        return baseUrl + '' + path;
    }
}

/**
 * Just converts arguments into a new string :
 *
 *    object://<objectKey>/<id>
 *
 * @param {String} objectKey
 * @param {String} id        Has to be urlEncoded (use urlEncode or jarves.getObjectUrlId)
 * @return {String}
 */
export function getObjectUrl(objectKey, id) {
    return 'object://' + normalizeObjectKey(objectKey) + '/' + id;
}

/**
 * This just cuts off object://<objectName>/ and returns the raw primary key part.
 *
 * @param {String} url
 *
 * @return {String}
 */
export function getCroppedObjectId(url) {
    if (!angular.isString(url)) {
        return url;
    }

    if (url.indexOf('object://') == 0) {
        url = url.substr(9);
    }

    var idx = url.indexOf('/'); //cut of bundleName
    url = -1 === idx ? url : url.substr(idx + 1);

    idx = url.indexOf('/'); //cut of objectName
    url = -1 === idx ? url : url.substr(idx + 1);

    return url;
}

export function compare(a, b) {
    return JSON.encode(a) == JSON.encode(b);
}

/**
 * This just cut anything but the full raw objectKey.
 *
 * Example:
 *
 *    jarves/file/3 => jarves/file
 *
 * @param {String} url Internal url
 *
 * @return {String} the objectKey
 */
export function getCroppedObjectKey(url) {
    if (!angular.isString(url)) {
        return url;
    }

    if (url.indexOf('object://') == 0) {
        url = url.substr(9);
    }

    var idx = url.indexOf('/'); //till bundleName/
    var nextPart = url.substr(idx + 1); // now we have <objectKey>/<id>

    var lastIdx = nextPart.indexOf('/'); //till objectKey/

    return -1 === lastIdx ? url : url.substr(0, idx + lastIdx + 1);
}

/**
 *
 * @param {Number} bytes
 * @returns {String}
 */
export function bytesToSize(bytes) {
    var sizes = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
    if (!bytes) {
        return '0 Bytes';
    }
    var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
    if (i == 0) {
        return (bytes / Math.pow(1024, i)) + ' ' + sizes[i];
    }
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
}

/**
 *
 * @param {Number} seconds
 *
 * @return {String}
 */
export function dateTime(seconds) {
    var date = new Date(seconds * 1000);
    var nowSeconds = new Date().getTime();
    var diffForThisWeek = 3600 * 24 * 7;

    var format = '%d. %B %Y, %H:%M';
    if (nowSeconds - date < diffForThisWeek) {
        //include full day name if date is within current week.
        format = '%a., ' + format;
    }

    return date.format(format);
}

/**
 * Returns the short bundleName.
 *
 * @param {String} bundleName
 *
 * @returns {string}
 */
export function getShortBundleName(bundleName) {
    return getBundleName(bundleName).toLowerCase().replace(/bundle$/, '');
}

/**
 * Returns a absolute path based on a relative one.
 *
 * @param {String} current
 * @param {String} relativePath
 * @returns {String}
 */
export function getEntryPointPathForRelative(current, relativePath) {
    if (!angular.isString(relativePath) || !relativePath) {
        return current;
    }

    if (relativePath.substr(0, 1) == '/') {
        return relativePath;
    }

    current = current + '';
    if (current.substr(current.length - 1, 1) != '/') {
        current += '/';
    }

    return current + relativePath;
}

export function simpleClone(value) {
    var result = {};
    for (let [key, val] of each(value)) {
        result[key] = val;
    }

    return result;
}

/**
 * Returns the bundle name from a PHP FQ class name.
 *
 * Jarves\JarvesBundle => JarvesBundle
 *
 * @param {String} bundleClass
 * @return {String} returns only the base bundle name
 */
export function getBundleName(bundleClass) {
    var split = bundleClass.split('\\');
    return split[split.length - 1];
}

/**
 * Quotes string to be used in a regEx.
 *
 * @param string
 *
 * @returns {String}
 */
export function pregQuote(string) {
    // http://kevin.vanzonneveld.net
    // +   original by: booeyOH
    // +   improved by: Ates Goral (http://magnetiq.com)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Onno Marsman
    // *     example 1: preg_quote("$40");
    // *     returns 1: '\$40'
    // *     example 2: preg_quote("*RRRING* Hello?");
    // *     returns 2: '\*RRRING\* Hello\?'
    // *     example 3: preg_quote("\\.+*?[^]$(){}=!<>|:");
    // *     returns 3: '\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:'

    return (string + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, "\\$1");
}

/**
 * Generates little noise at element background.
 *
 * @param {Element} element
 * @param {Number} opacity
 */
export function generateNoise(element, opacity) {
    if (!"getContent" in document.createElement('canvas')) {
        return false;
    }

    var canvas = document.createElement("canvas")
        , c2d = canvas.getContext("2d")
        , x
        , y
        , r
        , g
        , b
        , opacity = opacity || .2;

    canvas.width = canvas.height = 100;

    for (x = 0; x < canvas.width; x++) {
        for (y = 0; y < canvas.height; y++) {
            r = Math.floor(Math.random() * 80);
            g = Math.floor(Math.random() * 80);
            b = Math.floor(Math.random() * 80);

            c2d.fillStyle = "rgba(" + r + "," + g + "," + b + "," + opacity + ")";
            c2d.fillRect(x, y, 1, 1);
        }
    }

    element.style.backgroundImage = "url(" + canvas.toDataURL("image/png") + ")";
}

export function toQueryString(obj) {
    return window.$.param(obj);

    var parts = [];
    for (var i in obj) {
        if (obj.hasOwnProperty(i)) {
            parts.push(encodeURIComponent(i) + "=" + encodeURIComponent(obj[i]));
        }
    }
    return parts.join("&");
}

///**
// * Prepares a class constructor for angular depency injection.
// *
// * Searches for annotations and creates a decorator which creates the instance
// * and injects all necesarry deps.
// *
// * @param  {Function} controller
// * @return {Function}
// */
//export function getPreparedConstructor(controller) {
//    var parser = new Parser(controller);
//    var annotations = parser.getAnnotations(InjectAnnotation).reverse();
//    var $inject = [];
//    for (let annotation of annotations) {
//        $inject = $inject.concat(annotation.deps);
//    }
//
//    var injectsViaInjectCount = $inject.length;
//
//    var injectAsProperty = parser.getAnnotations(InjectAsPropertyAnnotation);
//    var propertyMap = {};
//
//    if (!injectAsProperty.length && !annotations.length) {
//        return false;
//    }
//
//    for (let annotation of injectAsProperty) {
//        $inject.push(annotation.propertyName);
//        propertyMap[annotation.propertyName] = $inject.length - 1;
//    }
//
//    var constructor = $inject;
//
//    constructor.push((...deps) => {
//        var args = deps.slice(0, injectsViaInjectCount);
//        var instance = new controller(...args);
//        for (let name in propertyMap) {
//            instance[name] = deps[propertyMap[name]];
//        }
//        return instance;
//    });
//
//    // constructor.$inject = $inject;
//
//    return constructor;
//}
//
///**
// * Registers a new jarves field.
// *
// * @param  {AngularModule} angularModule
// * @param  {Function} controller
// */
//export function registerModuleField(angularModule, controller) {
//    var parser = new Parser(controller);
//    var annotations = parser.getAnnotations(FieldAnnotation);
//    if (!annotations.length) {
//        throw 'No Field annotations on class ' + controller
//    }
//    var FieldAnnotationInstance = annotations[0];
//    var constructor = getPreparedConstructor(controller);
//    if (!constructor) constructor = controller;
//
//    var directiveName = 'jarves' + FieldAnnotationInstance.name.ucfirst() + 'Field';
//
//    var options = FieldAnnotationInstance.options || {};
//    options = angular.extend({
//        restrict: 'A',
//        controller: constructor,
//        scope: true,
//        transclude: true,
//        require: [directiveName, 'jarvesField', '?^jarvesField', '?^jarvesForm'],
//        link: function(scope, element, attr, ctrl, transclude) {
//            var ownController = ctrl[0];
//            var fieldController = ctrl[1];
//            var parentFieldController =  ctrl[2];
//            var jarvesFormController =  ctrl[3];
//            var controllersToPass = ctrl;
//            controllersToPass.shift();
//
//            fieldController.setController(ownController);
//            ownController.setFieldDirective(fieldController);
//
//            if (parentFieldController) {
//                ownController.setParentFieldDirective(parentFieldController);
//            }
//
//            if (jarvesFormController) {
//                jarvesFormController.addField(ownController);
//            }
//
//            if (controllersToPass && 1 === controllersToPass.length) {
//                controllersToPass = controllersToPass[0];
//            }
//
//            ownController.link(...[scope, element, attr, controllersToPass, transclude]);
//        }
//    }, options);
//
//    angularModule.directive(
//        directiveName,
//        function() {
//            return options;
//        }
//    );
//}
//
//export function registerModuleFilter(angularModule, controller) {
//    var parser = new Parser(controller);
//    var annotations = parser.getAnnotations(FilterAnnotation);
//    if (!annotations.length) {
//        throw 'No Filter annotations on class ' + controller
//    }
//    var filterAnnotationInstance = annotations[annotations.length-1];
//
//    var constructor = getPreparedConstructor(controller);
//
//    if (!constructor) {
//        constructor = function() {
//            let instance = new controller();
//            return instance.filter;
//        }
//        angularModule.filter(filterAnnotationInstance.name, constructor);
//    } else {
//        var diFunction = constructor[constructor.length - 1];
//        var overwrittenConstructor = constructor;
//
//        overwrittenConstructor[overwrittenConstructor.length - 1] = function(...deps) {
//            var instance = diFunction(...deps);
//            return instance.filter.bind(instance);
//        }
//
//        angularModule.filter(filterAnnotationInstance.name, overwrittenConstructor);
//    }
//}
//
///**
// * Registers a new jarves label.
// *
// * @param  {AngularModule} angularModule
// * @param  {Function} controller
// */
//export function registerModuleLabel(angularModule, controller) {
//
//
//    var parser = new Parser(controller);
//    var annotations = parser.getAnnotations(LabelAnnotation);
//    if (!annotations.length) {
//        throw 'No Field annotations on class ' + controller
//    }
//    var labelAnnotationInstance = annotations[0];
//    var constructor = getPreparedConstructor(controller);
//    if (!constructor) constructor = controller;
//
//    var directiveName = 'jarves' + labelAnnotationInstance.name.ucfirst() + 'Label';
//
//
//    var options = labelAnnotationInstance.options || {};
//    options = angular.extend({
//        restrict: 'A',
//        controller: constructor,
//        scope: true,
//        require: [directiveName, '?^jarvesForm'],
//        link: function(scope, element, attr, ctrl, transclude) {
//            var ownController = ctrl[0];
//            var controllersToPass = ctrl;
//            controllersToPass.shift();
//
//            if (controllersToPass && 1 === controllersToPass.length) {
//                controllersToPass = controllersToPass[0];
//            }
//
//            ownController.link.apply(ownController, [scope, element, attr, controllersToPass, transclude]);
//        }
//    }, options);
//
//    angularModule.directive(
//        directiveName,
//        function() {
//            return options;
//        }
//    );
//}