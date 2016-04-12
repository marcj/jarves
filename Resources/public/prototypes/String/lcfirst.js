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

String.prototype.lcfirst = function() {
    return this.length > 0 ? this.charAt(0).toLowerCase() + (this.length > 1 ? this.substr(1) : '') : '';
};