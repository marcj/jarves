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

function sprintf () {

    var args = arguments,
        text = this,         //buffer of origin text
        arg,                   //current argument item
        found,                 //position of current % char
        cursorPosition = 0,    //position of current position of origin text
        result = '',           //buffer of parsed text
        code = '',           //buffer for char(s) behind %
        opt = '',           //buffer for optional code parameter
        number = 0,            //buffer for the number value
        autoPosition = 0;      //position of current item position in args

    while ((found = text.indexOf('%', cursorPosition)) !== -1) {

        result += text.substring(cursorPosition, found);

        code = text.substr(found + 1).match(/(?:([0-9]+)\$|)('.+\-?|\-?[0-9\.]*|)([%bcdeEufgGosxX])/);
        // ->
        //  1 = argument numbering
        //  2 = optional parameter
        //  3 = code
        //

        if (!code) {
            cursorPosition++;
            continue;
        }

        cursorPosition = found + 1 + code[0].length;

        //if argument numbering/swapping
        if (code[1]) {
            arg = args[parseInt(code[1]) - 1];
        } else if (code[3] !== '%') {
            //default code, so we move position + 1
            arg = args[autoPosition++];
        }

        //d, f, u
        if (code[3] == 'd' || code[3] == 'f' || code[3] == 'u') {
            number = Number(arg) || 0;
            if (code[2] && (opt = parseInt(code[2]) - String(number.toFixed(0)).length) > 0) {
                result += '0'.repeat(opt);
            }
        }

        if (code[3] == 's') {
            var character = '';
            if (code[2]) {

                if (code[2].substr(0, 1) == '\'') {
                    character = code[2].substr(1, 1);
                    opt = 2;
                } else {
                    opt = code[2].search(/[\-1-9]/);
                    character = code[2].substr(0, opt) || ' ';
                }

                opt = code[2].substr(opt, code[2].length);

                //truncate
                if (parseFloat(opt) % 1) {
                    arg = String(arg).substr(0, parseInt(opt.substr(opt.indexOf('.') + 1)));
                }

                opt = parseFloat(opt);

                if (opt < 0 && (opt * -1) - arg.length > 0) {
                    result += character.repeat(parseInt((opt * -1) - arg.length));
                }
            }

            result += String(arg);

            if (opt > 0 && opt - arg.length > 0) {
                result += character.repeat(parseInt(opt - arg.length));
            }

            continue;
        }

        switch (code[3]) {
            case '%':
                result += '%';
                break;
            case 'b':
                result += (Number(arg) || 0).toString(2);
                break;
            case 'c':
                result += String.fromCharCode(Number(arg) || 0);
                break;
            case 'd':
                result += number.toFixed(0);
                break;
            case 'e':
                result += (Number(arg) || 0).toExponential();
                break;
            case 'E':
                result += (Number(arg) || 0).toExponential().toUpperCase();
                break;
            case 'u':
                result += number >>> 0;
                break;
            case 'f':
                result += number.toFixed((code[2] ? code[2].substr(code[2].indexOf('.') + 1) : 6));
                break;
            case 'o':
                result += (Number(arg) || 0).toString(8);
                break;
            case 'x':
                result += (Number(arg) || 0).toString(16);
                break;
            case 'X':
                result += (Number(arg) || 0).toString(16).toUpperCase();
                break;
        }

    }

    result += text.substr(cursorPosition);

    return result;
}

String.prototype.sprintf = sprintf;