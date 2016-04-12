#!/bin/sh

# rm -rf Resources/public/libraries/systemjs; cp -r node_modules/systemjs/dist Resources/public/libraries/systemjs;
rm -rf Resources/public/libraries/typescript; cp -r node_modules/typescript/lib Resources/public/libraries/typescript;
rm -rf Resources/public/libraries/ladda; cp -r node_modules/ladda/dist Resources/public/libraries/ladda;

rm -rf Resources/public/libraries/angular2;
mkdir Resources/public/libraries/angular2;

cp -r node_modules/angular2/bundles/angular2.dev.js Resources/public/libraries/angular2/;
cp -r node_modules/angular2/bundles/angular2.js Resources/public/libraries/angular2/;
cp -r node_modules/angular2/bundles/angular2-polyfills.js Resources/public/libraries/angular2/;
cp -r node_modules/angular2/bundles/http.dev.js Resources/public/libraries/angular2/;


cp node_modules/es6-shim/es6-shim.min.js Resources/public/libraries/
cp node_modules/systemjs/dist/system-polyfills.js Resources/public/libraries/
cp node_modules/angular2/es6/dev/src/testing/shims_for_IE.js Resources/public/libraries/


cp node_modules/systemjs/dist/system.js Resources/public/libraries/
cp node_modules/rxjs/bundles/Rx.js Resources/public/libraries/

#rm -rf Resources/public/libraries/zone.js;
#mkdir Resources/public/libraries/zone.js;
#cp -r node_modules/zone.js/dist/zone.min.js Resources/public/libraries/zone.js/;