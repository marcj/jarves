// import ObjectCollection from '../ObjectCollection.ts';
import {each, normalizeObjectKey, toQueryString} from '../utils.ts';
import ObjectCollection from "../ObjectCollection";
import {Injectable} from "angular2/core";

@Injectable()
export default class ObjectRepository {
    private changesCallback = {};

    constructor(private $rootScope, private $q, private $injector, private $timeout, private backend, private jarves) {
        jarves.fireObjectChange = (objectKey) => {
            this.fireObjectChange(normalizeObjectKey(objectKey));
        };
    }

    onObjectChange(objectKey, cb) {
        if (!(objectKey in this.changesCallback)) {
            this.changesCallback[objectKey] = [];
        }
        this.changesCallback[objectKey].push(cb);
    }

    offObjectChange(cb) {
        for (let [objectKey, cbs] of each(this.changesCallback)) {
            for (let [idx, cb] of each(cbs)) {
                if (cb === cb) {
                    cbs.splice(idx, 1);
                }
            }
        }
    }

    fireObjectChange(objectKey) {
        if (this.changesCallback[objectKey]) {
            for (let cb of this.changesCallback[objectKey]) {
                cb();
            }
        }
    }

    newCollection(objectKey:string) : ObjectCollection {
        var collection = this.$injector.instantiate(ObjectCollection);
        collection.setObjectKey(objectKey);

        return collection;
    }

    //getItems: function(objectKey, options) {
    //    var deferred = this.$q.defer();
    //
    //    if (!this.instancePool[objectKey]) {
    //        this.instancePool[objectKey] = {};
    //    }
    //
    //    options = options || {};
    //
    //    var queryString = Object.toQueryString(options);
    //
    //    this.backend.get('jarves/object/' + jarves.normalizeObjectKey(objectKey) + '/?' + queryString).success(function(response) {
    //        this.mapData(objectKey, response.data);
    //        deferred.resolve(this.instancePool[objectKey]);
    //    }.bind(this));
    //
    //    return deferred.promise;
    //},

    loadItems(objectKey, entryPoint, queryOptions) {
        var deferred = this.$q.defer();

        this.backend.get(entryPoint + '/?' + toQueryString(queryOptions))
            .success(function(response) {
                //this.mapData(objectKey, response.data);
                //deferred.resolve(this.instancePool[objectKey]);
                deferred.resolve(this.response.data);
            });

        return deferred.promise;
    }

    //mapData: function(objectKey, items, asArray) {
    //    var changes = false;
    //
    //    if (!this.instancePool[objectKey]) {
    //        this.instancePool[objectKey] = {};
    //    }
    //
    //    if (items.length > Object.getLength(this.instancePool[objectKey])) {
    //        changes = true;
    //    }
    //
    //    var mappedData = asArray ? [] : {};
    //
    //    Object.each(items, function(item) {
    //        var id = this.jarves.getObjectId(objectKey, item);
    //
    //        if (this.mapItem(objectKey, item)) {
    //            changes = true;
    //        }
    //
    //        if (asArray) {
    //            mappedData.push(this.instancePool[objectKey][id]);
    //        } else {
    //            mappedData[id] = this.instancePool[objectKey][id];
    //        }
    //    }, this);
    //
    //    if (changes) {
    //        this.$timeout(function(){
    //            this.$rootScope.$apply();
    //        }.bind(this));
    //    }
    //
    //    return mappedData;
    //},
    //
    //mapItem: function(objectKey, item) {
    //    var changes = false;
    //    var id = this.jarves.getObjectId(objectKey, item);
    //    if (!this.instancePool[objectKey]) {
    //        this.instancePool[objectKey] = {};
    //    }
    //
    //    if (!this.instancePool[objectKey][id]) {
    //        changes = true;
    //        this.instancePool[objectKey][id] = item;
    //    } else {
    //        Object.each(item, function(value, fieldKey) {
    //            if (!(fieldKey in this.instancePool[objectKey][id])) {
    //                this.instancePool[objectKey][id][fieldKey] = value;
    //                changes = true;
    //            } else if (this.instancePool[objectKey][id][fieldKey] != value) {
    //                this.instancePool[objectKey][id][fieldKey] = value;
    //                changes = true;
    //            }
    //        }, this);
    //    }
    //
    //    if (changes) {
    //        this.fireObjectChange(objectKey, item);
    //    }
    //
    //    return changes;
    //}

}