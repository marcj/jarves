/*
 * This file is part of Jarves.
 *
 * (c) Marc J. Schmidt <marc@marcjschmidt.de>
 *
 *     J.A.R.V.E.S - Just A Rather Very Easy [content management] System.
 *
 *     http://jarves.io
 *
 * To get the full copyright and license information, please view the
 * LICENSE file, that was distributed with this source code.
 */

System.config({
    // baseURL: './bundles',
    transpiler: 'typescript',
    packages: {
        'bundles/jarves/': {
            // format: 'register',
            defaultExtension: 'ts'
        }
    },

    typescriptOptions: {
        //"noImplicitAny": true,
        // "module": "system",
        // //"isolatedModules": true,
        // "emitDecoratorMetadata": true,

        // "target": "es5",
        "module": "system",
        // "moduleResolution": "node",
        // "sourceMap": true,
        "emitDecoratorMetadata": true,
        "experimentalDecorators": true,
        "removeComments": false,
        "noImplicitAny": false
    }
});