//import {Test, Inject, Directive} from '../Test.ts';
import {each, eachValue, normalizeObjectKey, urlEncode, urlDecode, getCroppedObjectKey, getCroppedObjectId} from '../utils.ts';
import {baseUrl, baseUrlApi} from '../config';
import {EntryPoint} from "./WindowManagement";
import {Injectable} from "angular2/core";
import Backend from "./Backend";
import JarvesSession from "./JarvesSession";
import {MenuItem} from "./JarvesSession";
import {InterfaceMenus} from "./JarvesSession";
import EmitterPromise from "../EmitterPromise";

@Injectable()
export default class Jarves {
    getObjectLabelByItemTemplates:Object = {};

    constructor(private backend:Backend, private jarvesSession:JarvesSession) {
    }

    //logout() {
    //    this.loginController.logout();
    //}

    protected entryPointOptionsCache = {};

    public loadEntryPointOptions(entryPoint:string):Promise {
        return new Promise((resolve, reject) => {

            if (this.entryPointOptionsCache[entryPoint]) {
                resolve(this.entryPointOptionsCache[entryPoint]);
            } else {
                this.backend.post(entryPoint + '/?_method=options')
                    .on('success', response => {
                        this.entryPointOptionsCache[entryPoint] = response.data;
                        resolve(response.data);
                    })
                    .catch(error => reject(error));
            }
        });
    }

    public loadInterface():EmitterPromise {
        return new EmitterPromise((resolve, reject, eventEmitter) => {
            eventEmitter('progress', 25);

            this.loadSettings()
                .then(() => {
                    console.log('outer loadSettings done');
                    eventEmitter('progress', 60);
                    this.loadMenu()
                        .then(() => {
                            eventEmitter('progress', 100);
                            resolve();
                        })
                        .catch(error => console.error(error))
                }, error => reject(error))
        });
    }

    /**
     * Loads settings from the backend to application.
     */
    loadSettings(keyLimitation?:Array = []):Promise {
        console.log('init loading settings');

        return new Promise((resolve, reject) => {
            var queryString = {lang: this.jarvesSession.getLanguage(), keys: keyLimitation};

            this.backend.get('jarves/admin/backend/settings', queryString)
                .then(response => {
                    var preparedSettings = this.prepareSettings(response.body.data);
                    console.log('preparedSettings', preparedSettings);
                    this.jarvesSession.setSettings(preparedSettings);
                    resolve();
                }, error => { reject(error); })
        });
    }

    /**
     * Loads all menu items from the backend.
     */
    loadMenu():Promise {
        return new Promise((resolve, reject) => {
            this.backend.get('jarves/admin/backend/menus')
                .then(response => {
                    console.log('loadMenu', response);
                    this.setMenus(response.body.data);
                    resolve(response.body.data);
                }, error => reject(error))

        });
    }

    /**
     * Sets menus
     *
     * @param {Array} menus
     */
    setMenus(menus) {
        var categorized:InterfaceMenus = {};

        var lastKey = '';
        var lastBundle;
        for (let menu of eachValue(menus)) {
            lastBundle = menu.fullPath.split('/')[0];

            if (!categorized[lastBundle]) {
                //changed bundle
                categorized[lastBundle] = {label: this.getBundleTitle(lastBundle), items: []};
                lastKey = lastBundle;
            }

            if ('acl' === menu.type) {
                lastKey = menu.fullPath;
            }

            if (!categorized[lastKey]) {
                menu.items = [];
                categorized[lastKey] = menu;
                continue;
            }

            categorized[lastKey].items.push(menu);
        }

        this.jarvesSession.setMenus(categorized);
    }

    /**
     * Sets settings
     *
     * @param {Object} settings
     */
    prepareSettings(settings) {
        var preparedSettings = this.jarvesSession.getSettings();

        for (let [key, val] of each(settings)) {
            preparedSettings[key] = val;
        }

        preparedSettings['images'] = ['jpg', 'jpeg', 'bmp', 'png', 'gif', 'psd'];

        preparedSettings.configsAlias = {};

        for (let [key, config] of each(preparedSettings.configs)) {
            preparedSettings.configsAlias[key.toLowerCase()] = preparedSettings.configs[key];
            preparedSettings.configsAlias[key.toLowerCase().replace(/bundle$/, '')] = preparedSettings.configs[key];
        }

        return preparedSettings;
    }


    /**
     * Returns the module title of the given module key.
     *
     * @param {String} key
     *
     * @return {String|null} Null if the module does not exist/its not activated.
     */
    getBundleTitle(key:string):string {
        var config = this.getConfig(key);
        if (!config) {
            return key;
        }

        return config.label || config.name;
    }


    /**
     * Returns the bundle configuration array.
     *
     * @param {String} bundleName
     * @returns {Object|undefined}
     */
    getConfig(bundleName:string):Object {
        if (!bundleName) return;

        return this.jarvesSession.getSettings().configs[bundleName]
            || this.jarvesSession.getSettings().configs[bundleName.toLowerCase()]
            || this.jarvesSession.getSettings().configsAlias[bundleName]
            || this.jarvesSession.getSettings().configsAlias[bundleName.toLowerCase()];
    }

    /**
     * Returns the definition of a entry point.
     */
    getEntryPoint(path:string):EntryPoint {
        var splitted = path.split('/');
        var bundleName = splitted[0];

        splitted.shift();

        var code = splitted.join('/');

        var config, notFound = false, item;
        var path = [];

        config = this.getConfig(bundleName);

        if (!config) {
            throw 'getEntryPoint: Config not found for bundleName: ' + bundleName;
        }

        var tempEntry = config.entryPoints[splitted.shift()];
        if (!tempEntry) {
            return null;
        }
        path.push(tempEntry['label']);

        while (item = splitted.shift()) {
            if (tempEntry.children && tempEntry.children[item]) {
                tempEntry = tempEntry.children[item];
                path.push(tempEntry['label']);
            } else {
                notFound = true;
                break;
            }
        }

        if (notFound) {
            return null;
        }

        tempEntry._path = path;
        tempEntry._module = bundleName;
        tempEntry._code = code;

        return tempEntry;
    }

    /**
     * executes a entry point from type function
     * @param {Object} entryPoint
     * @param {Object} options
     */
    execEntryPoint(entryPoint, options) {
        if (entryPoint.functionType == 'global') {
            if (window[entryPoint.functionName]) {
                window[entryPoint.functionName](options);
            }
        } else if (entryPoint.functionType == 'code') {
            eval(entryPoint.functionCode);
        }
    }


    /**
     * Returns the object definition properties.
     *
     * @param {String} objectKey
     * @returns {Object}
     */
    getObjectDefinition(objectKey) {
        if ('string' !== typeof objectKey) {
            throw 'objectKey is not a string: ' + objectKey;
        }

        objectKey = normalizeObjectKey(objectKey);

        var bundleName = ("" + objectKey.split('/')[0]).toLowerCase();
        var name = objectKey.split('/')[1].lcfirst();
        var config;

        if (this.getConfig(bundleName) && this.getConfig(bundleName)['objects'][name]) {
            if (config = this.getConfig(bundleName)['objects'][name]) {
                config._key = objectKey;
            }
        }

        if (!config) {
            console.log('objects available in bundle %s: '.sprintf(bundleName), Object.keys(this.getConfig(bundleName)['objects']));
            throw new Error('No definition for object %s found.'.sprintf(objectKey));
        }

        return config;
    }

    /**
     * Return only the primary key values of a object.
     *
     * @param {String} objectKey
     * @param {Object} item Always a object with the primary key => value pairs.
     *
     * @return {Object}
     */
    getObjectPk(objectKey:string, item:Object):Object {
        var pks = this.getObjectPrimaryList(objectKey);
        var result = {};
        for (let pk of pks) {
            result[pk] = item[pk];
        }
        return result;
    }


    /**
     * Returns a list of the primary keys.
     *
     * @param {String} objectKey
     *
     * @return {Array}
     */
    getObjectPrimaryList(objectKey) {
        var def = this.getObjectDefinition(objectKey);

        var res = [];
        for (let [key, field] of each(def.fields)) {
            if (field.primaryKey) {
                res.push(key);
            }
        }

        return res;
    }

    /**
     * Returns the primaryKey name.
     *
     * @param {String} objectKey
     *
     * @returns {String}
     */
    getObjectPrimaryKey(objectKey) {
        var pks = this.getObjectPrimaryList(objectKey);
        return pks[0];
    }

    /**
     *
     * Return the id or array of internal url id.
     *
     * Example:
     *
     *    3 => 3
     *    %252Fadmin%252Fimages%252Fhi.jpg => /admin/images/hi.jpg
     *    idValue1/idValue2 => {id1: idValue1, id2: idValue2}
     *
     * @param {String} objectKey
     * @param {String} urlId
     * @returns {Object}
     */
    getObjectPkFromUrlId(objectKey:string, urlId:string):Object {
        var pks = this.getObjectPrimaryList(objectKey);

        var def = this.getObjectDefinition(objectKey);

        var res = [];
        var values = urlDecode(urlId.split('/'));
        var result = {};
        var idx = 0;
        for (let [key, field] of each(def.fields)) {
            if (field.primaryKey) {
                let value = values[idx];
                if ('number' == field.type) {
                    value = value * 1;
                }
                result[key] = value;
                idx++;
            }
        }

        return result;
    }

    /**
     * Return the internal representation (id) of object primary keys.
     *
     * @param {String} objectKey
     * @param {Object} item
     *
     * @return {String} url encoded string
     */
    getObjectUrlId(objectKey, item) {
        var pks = this.getObjectPrimaryList(objectKey);

        if (1 < pks.length) {
            var values = [];
            for (let pk of pks) {
                values = urlEncode(item[pk]);
            }
            return values.join('/');
        }

        if (!(pks[0] in item)) {
            throw pks[0] + ' does not exist in item.';
        }

        return urlEncode(item[pks[0]]);
    }

    /**
     * Return the origin id of object primary keys. If the object has multiple pks, we return only the first.
     *
     * @param {String} objectKey
     * @param {Object} item
     *
     * @return {String|Number}
     */
    getObjectId(objectKey, item) {
        var pks = this.getObjectPrimaryList(objectKey);

        return item[pks[0]];
    }

    /**
     * Returns the correct escaped id part of the object url (object://<objectName>/<id>).
     *
     * @param {String} objectKey
     * @param {String} id String from jarves.getObjectUrlId or jarves.getObjectIdFromUrl e.g.
     */
    getObjectUrlIdFromId(objectKey, id) {
        return this.hasCompositePk(objectKey) ? id : urlEncode(id);
    }

    /**
     * Returns true if objectKey as more than one primary key.
     *
     * @param {String} objectKey
     * @returns {boolean}
     */
    hasCompositePk(objectKey) {
        return 1 < this.getObjectPrimaryList(objectKey).length;
    }

    /**
     * Return the internal representation (id) of a internal object url.
     *
     * Examples:
     *
     *  url = object://jarves/user/1
     *  => 1
     *
     *  url = object://jarves/file/%252Fadmin%252Fimages%252Fhi.jpg
     *  => /admin/images/hi.jpg
     *
     *  url = object://jarves/test/pk1/pk2
     *  => pk1/pk2
     *
     * @param {String} url
     *
     * @return {String} encoded id
     */
    getObjectIdFromUrl(url) {
        var pks = this.getObjectPrimaryList(getCroppedObjectKey(url));

        var pkString = getCroppedObjectId(url);

        //    if (1 < pks.length) {
        //        return pkString; //already correct formatted
        //    }

        return urlDecode(pkString);
    }

    ///**
    // * Returns the object label, based on a label field or label template (defined
    // * in the object definition).
    // * This function calls perhaps the REST API to get all information.
    // * If you already have an item object, you should probably use jarvesSevice.getObjectLabelByItem();
    // *
    // * You can call this function really fast consecutively, since it queues all and fires
    // * only one REST API call that receives all items at once per object key.(at least after 50ms of the last call).
    // *
    // * @param {String} uri
    // * @param {Function} callback the callback function.
    // *
    // */
    //getObjectLabel: function(uri, callback) {
    //    var objectKey = normalizeObjectKey(jarves.getCroppedObjectKey(uri));
    //    var pkString = jarves.getCroppedObjectId(uri);
    //    var normalizedUrl = 'object://' + objectKey + '/' + pkString;
    //
    //    if (this.getObjectLabelBusy[objectKey]) {
    //        this.getObjectLabel.delay(10, this.getObjectLabel, [normalizedUrl, callback]);
    //        return;
    //    }
    //
    //    if (this.getObjectLabelQTimer[objectKey]) {
    //        clearTimeout(this.getObjectLabelQTimer[objectKey]);
    //    }
    //
    //    if (!this.getObjectLabelQ[objectKey]) {
    //        this.getObjectLabelQ[objectKey] = {};
    //    }
    //
    //    if (!this.getObjectLabelQ[objectKey][normalizedUrl]) {
    //        this.getObjectLabelQ[objectKey][normalizedUrl] = [];
    //    }
    //
    //    this.getObjectLabelQ[objectKey][normalizedUrl].push(callback);
    //
    //    this.getObjectLabelQTimer[objectKey] = (function() {
    //
    //        this.getObjectLabelBusy = true;
    //
    //        var uri = 'object://' + normalizeObjectKey(objectKey) + '/';
    //        Object.each(this.getObjectLabelQ[objectKey], function(cbs, requestedUri) {
    //            uri += this.getCroppedObjectId(requestedUri) + '/';
    //        });
    //        if (uri.substr(-1) == '/') {
    //            uri = uri.substr(0, uri.length - 1);
    //        }
    //
    //        new Request.JSON({url: _pathAdmin + 'admin/objects',
    //            noCache: 1, noErrorReporting: true,
    //            onComplete: function(pResponse) {
    //                var result, fullId, cb;
    //
    //                Object.each(pResponse.data, function(item, pk) {
    //                    if (item === null) {
    //                        return;
    //                    }
    //
    //                    fullId = 'object://' + objectKey + '/' + pk;
    //                    result = this.getObjectLabelByItem(objectKey, item, 'field');
    //
    //                    if (this.getObjectLabelQ[objectKey][fullId]) {
    //                        while ((cb = this.getObjectLabelQ[objectKey][fullId].pop())) {
    //                            cb(result, item);
    //                        }
    //                    }
    //
    //                }.bind(this));
    //
    //                //call the callback of invalid requests with false argument.
    //                Object.each(this.getObjectLabelQ[objectKey], function(cbs) {
    //                    cbs.each(function(cb) {
    //                        cb.attempt(false);
    //                    });
    //                });
    //
    //                this.getObjectLabelBusy[objectKey] = false;
    //                this.getObjectLabelQ[objectKey] = {};
    //
    //            }.bind(this)}).get({url: uri, returnKeyAsRequested: 1});
    //
    //    }.bind(this)).delay(50);
    //},
    //
    //getObjectLabelQ: {},
    //getObjectLabelBusy: {},
    //getObjectLabelQTimer: {},

    /**
     * Returns the rest entry-point of our API for object access.
     *
     * Default is jarves/object/<bundleName>/<objectName>,
     * but the object has the ability to define its own entry point.
     *
     * @param {String} objectKey
     * @returns {String}
     */
    getObjectApiUrl(objectKey) {
        var definition = this.getObjectDefinition(objectKey);
        if (!definition) {
            throw 'Definition not found ' + objectKey;
        }

        if (definition.objectRestEntryPoint) {
            return 'jarves/' + definition.objectRestEntryPoint;
        }

        return 'jarves/' + normalizeObjectKey(objectKey);

    }

    ///**
    // * Returns the object label, based on a label field or label template (defined
    // * in the object definition).
    // *
    // * @param {String} objectKey
    // * @param {Object} item
    // * @param {String} [mode] 'default', 'field' or 'tree'. Default is 'default'
    // * @param {Object} [overwriteDefinition] overwrite definitions stored in the objectKey
    // *
    // * @return {String}
    // */
    //getObjectLabelByItem(objectKey, item, mode, overwriteDefinition) {
    //
    //    var definition = this.getObjectDefinition(objectKey);
    //    if (!definition) {
    //        throw 'Definition not found ' + objectKey;
    //    }
    //
    //    var template = definition.treeTemplate ? definition.treeTemplate : definition.labelTemplate;
    //    var label = definition.treeLabel ? definition.treeLabel : definition.labelField;
    //
    //    if (overwriteDefinition) {
    //        for (let map of 'fieldTemplate', 'fieldLabel', 'treeTemplate', 'treeLabel') {
    //            if (map in overwriteDefinition) {
    //                definition[map] = overwriteDefinition[map];
    //            }
    //        });
    //    }
    //
    //    /* field ui */
    //    if (mode == 'field' && definition.fieldTemplate) {
    //        template = definition.fieldTemplate;
    //    }
    //
    //    if (mode == 'field' && definition.singleItemLabelField) {
    //        label = definition.singleItemLabelField;
    //    }
    //
    //    /* tree */
    //    if (mode == 'tree' && definition.treeTemplate) {
    //        template = definition.treeTemplate;
    //    }
    //
    //    if (mode == 'tree' && definition.treeLabel) {
    //        label = definition.treeLabel;
    //    }
    //
    //    if (!template) {
    //        //we only have an label field, so return it
    //        return item[label];
    //    }
    //
    //    template = this.getObjectLabelByItemTemplates[template] || nunjucks.compile(template);
    //    return template.render(item);
    //}

    /**
     * Returns all labels for a object item.
     *
     * @param {Object}  fields  The array of fields definition, that defines /how/ you want to show the data. limited range of 'type' usage.
     * @param {Object}  item
     * @param {String}  objectKey
     * @param {Boolean} [relationsAsArray] Relations would be returned as arrays/origin or as string(default).
     *
     * @return {Object}
     */
    getObjectLabels(fields, item, objectKey, relationsAsArray) {

        var data = item, dataKey;
        for (let [fieldId, field] of each(fields)) {
            dataKey = fieldId;
            if (relationsAsArray && dataKey.indexOf('.') > 0) {
                dataKey = dataKey.split('.')[0];
            }

            data[dataKey] = this.getObjectFieldLabel(item, field, fieldId, objectKey, relationsAsArray);
        }

        return data;
    }

    /**
     * Returns a single label for a field of a object item.
     *
     * @param {Object} value
     * @param {Object} field The array of fields definition, that defines /how/ you want to show the data. limited range of 'type' usage.
     * @param {String} fieldId
     * @param {String} objectKey
     * @param {Boolean} [relationsAsArray]
     *
     * @return {String} Safe HTML. Escaped with jarves.htmlEntities()
     */
    getObjectFieldLabel(value, field, fieldId, objectKey, relationsAsArray) {

        var oriFields = this.getObjectDefinition(objectKey);
        if (!oriFields) {
            throw 'Object not found ' + objectKey;
        }

        var oriFieldId = fieldId;
        if (angular.isString(fieldId) && fieldId.indexOf('.') > 0) {
            oriFieldId = fieldId.split('.')[0];
        }

        oriFields = oriFields['fields'];
        var oriField = oriFields[oriFieldId];

        var showAsField = Object.clone(field || oriField);
        if (!showAsField.type) {
            for (let [i, v] of each(oriFields)) {
                if (!showAsField[i]) {
                    showAsField[i] = v;
                }
            }
        }

        value = Object.clone(value);

        if (showAsField.type == 'predefined') {
            if (this.getObjectDefinition(showAsField.object)) {
                showAsField = this.getObjectDefinition(showAsField.object).fields[showAsField.field];
            }
        }

        showAsField.type = showAsField.type || 'text';
        if (oriField) {
            oriField.type = oriField.type || 'text';
        }

        var clazz = showAsField.type.ucfirst();
        if (!jarves.LabelTypes[clazz]) {
            clazz = 'Text';
        }

        if (relationsAsArray) {
            showAsField.options = showAsField.options || {};
            showAsField.options.relationsAsArray = true;
        }

        var instance = this.$injector.instantiate(jarves.LabelTypes[clazz], {
            oriField: oriField,
            showAsField: showAsField,
            fieldId: fieldId,
            objectKey: objectKey
        });

        return instance.render(value);
    }

}